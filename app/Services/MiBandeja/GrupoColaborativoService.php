<?php

namespace App\Services\MiBandeja;

use App\Events\MiBandeja\Grupos\DocumentoBloqueado;
use App\Events\MiBandeja\Grupos\DocumentoLiberado;
use App\Events\MiBandeja\Grupos\MiembroCumplido;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempArchivoVersion;
use App\Models\MiBandeja\MiBandejaTempAuditLog;
use App\Models\MiBandeja\MiBandejaTempGrupoAprobador;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\MiBandeja\MiBandejaTempGrupoProyector;
use App\Models\MiBandeja\MiBandejaTempGrupoRevisor;
use App\Models\OfiArchivo\OfiArchivoPlantillaDocumento;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoArchivos;
use App\Models\VentanillaUnica\Recibidos\RadicadoRespuesta;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciArchivo;
use App\Services\Firma\FirmaService;
use App\Services\OfiArchivo\PlantillaDocumentoService;
use App\Services\VentanillaUnica\RadicadoEstadoTrabajoService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Servicio para la gestión de ciclo de vida, versionado y colaboración de grupos de trabajo temporales.
 *
 * Administra el bloqueo (check-out) y liberación (check-in) concurrente de documentos,
 * la sincronización con plantillas institucionales, el registro de cumplimiento de tareas
 * por cada rol asignado (revisores, proyectores, aprobadores, firmantes) y el despacho final.
 */
class GrupoColaborativoService
{
    private const DISCO = 'plantillas_grupos';

    private const HORAS_BLOQUEO = 24;

    public function __construct(
        private readonly FirmaService $firmaService,
    ) {}

    /**
     * Transmite un evento en tiempo real de forma segura, capturando excepciones de broadcasting.
     *
     * @param  object  $event  Instancia del evento a transmitir vía Laravel Reverb / WebSockets
     */
    private function dispatchSafely($event): void
    {
        try {
            Event::dispatch($event);
        } catch (\Exception $e) {
            Log::warning('Evento no se pudo transmitir: '.$e->getMessage(), [
                'event' => get_class($event),
            ]);
        }
    }

    /**
     * Realiza el check-out de un documento para edición exclusiva por el usuario.
     *
     * Si no existe ninguna versión previa, genera la versión inicial 1.0 a partir de la
     * plantilla asociada reemplazando variables institucionales. Si ya existe versión,
     * aplica bloqueo optimista evitando modificaciones concurrentes mientras dure la sesión.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo al que pertenece el documento
     * @param  User  $user  Usuario funcionario que solicita el bloqueo de edición
     * @return array{archivo: string, nombre: string, mime: string, version: string}
     *
     * @throws \RuntimeException Si el archivo está bloqueado por otro usuario o no se encuentra
     */
    public function checkOut(MiBandejaTemp $grupo, User $user): array
    {
        $version = $grupo->ultimaVersion;

        if (! $version) {
            if (! $grupo->plantilla_id) {
                throw new \RuntimeException('El grupo no tiene ninguna versión del documento ni plantilla asociada');
            }

            $variables = $this->obtenerVariablesPlantilla($grupo, $user);
            $valores = [];
            foreach ($variables as $v) {
                $valores[$v['clave']] = $v['valor_defecto'];
            }

            return $this->crearVersionDesdePlantilla($grupo, $user, $valores);
        }

        return DB::transaction(function () use ($version, $user, $grupo) {
            $version->lockForUpdate();

            if ($version->bloqueoExpirado()) {
                $version->update([
                    'bloqueado_por_user_id' => null,
                    'fecha_bloqueo' => null,
                ]);
            }

            if ($version->bloqueado_por_user_id !== null && $version->bloqueado_por_user_id !== $user->id) {
                $bloqueador = User::find($version->bloqueado_por_user_id);
                $nombre = $bloqueador ? "{$bloqueador->nombres} {$bloqueador->apellidos}" : 'otro usuario';
                throw new \RuntimeException("El documento está bloqueado por {$nombre}");
            }

            $version->update([
                'bloqueado_por_user_id' => $user->id,
                'fecha_bloqueo' => Carbon::now(),
            ]);

            $this->dispatchSafely(new DocumentoBloqueado(
                $grupo->id,
                $user->id,
                "{$user->nombres} {$user->apellidos}",
                Carbon::now()->toISOString(),
            ));

            $path = $version->ruta_completa;
            if (! Storage::disk(self::DISCO)->exists($path)) {
                throw new \RuntimeException('El archivo no se encuentra en el servidor');
            }

            $diskPath = Storage::disk(self::DISCO)->path($path);
            $hashActual = hash_file('sha256', $diskPath);

            if ($hashActual !== $version->hash_seguridad) {
                throw new \RuntimeException(
                    'Integridad del archivo comprometida: el hash SHA-256 no coincide. '
                    .'El archivo pudo haber sido modificado en el servidor.'
                );
            }

            $this->reemplazarVariablesSiTienePlaceholders($diskPath, $grupo, $user);

            $this->marcarDescargaPlantilla($grupo, $user);

            return [
                'archivo' => $diskPath,
                'nombre' => $version->nombre_original,
                'mime' => $version->mime_type,
                'version' => $version->version,
            ];
        });
    }

    /**
     * Clona y prepara el archivo físico de la plantilla asociada, creando la versión inicial 1.0.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo al que se asociará la versión inicial
     * @param  User  $user  Usuario funcionario que inicializa la plantilla
     * @param  array  $variables  Diccionario clave-valor con variables a sustituir en el documento
     * @return array{archivo: string, nombre: string, mime: string, version: string}
     *
     * @throws \RuntimeException Si la plantilla no existe en el sistema o disco
     */
    private function crearVersionDesdePlantilla(MiBandejaTemp $grupo, User $user, array $variables = []): array
    {
        return DB::transaction(function () use ($grupo, $user, $variables) {
            $plantilla = OfiArchivoPlantillaDocumento::find($grupo->plantilla_id);

            if (! $plantilla) {
                throw new \RuntimeException('La plantilla asociada al grupo no se encuentra en el servidor');
            }

            if (! Storage::disk('plantillas')->exists($plantilla->ruta_completa)) {
                throw new \RuntimeException('El archivo de la plantilla no se encuentra en el servidor');
            }

            $extension = $plantilla->extension;
            $uuid = Str::uuid().'.'.$extension;
            $radicado = $grupo->radicado;
            $numRadicado = $radicado?->num_radicado ?? 'sin-radicado';
            $rutaRelativa = "{$numRadicado}/{$uuid}";

            Storage::disk(self::DISCO)->put(
                $rutaRelativa,
                Storage::disk('plantillas')->get($plantilla->ruta_completa)
            );

            $rutaCompleta = Storage::disk(self::DISCO)->path($rutaRelativa);

            if (! empty($variables)) {
                $this->reemplazarVariablesEnArchivo($rutaCompleta, $variables);
            }

            $hash = hash_file('sha256', $rutaCompleta);

            $nuevoRegistro = MiBandejaTempArchivoVersion::create([
                'grupo_id' => $grupo->id,
                'version' => '1.0',
                'nombre_original' => $plantilla->nombre_original,
                'nombre_archivo' => $uuid,
                'ruta_completa' => $rutaRelativa,
                'peso' => $plantilla->peso,
                'extension' => $extension,
                'mime_type' => $plantilla->mime_type,
                'hash_seguridad' => $hash,
                'user_subio_id' => $user->id,
                'bloqueado_por_user_id' => $user->id,
                'fecha_bloqueo' => Carbon::now(),
                'comentario_version' => 'Versión inicial desde plantilla',
            ]);

            $grupo->update(['plantilla_cargada' => true]);
            $this->marcarDescargaPlantilla($grupo, $user);

            $this->dispatchSafely(new DocumentoBloqueado(
                $grupo->id,
                $user->id,
                "{$user->nombres} {$user->apellidos}",
                Carbon::now()->toISOString(),
            ));

            return [
                'archivo' => $rutaCompleta,
                'nombre' => $nuevoRegistro->nombre_original,
                'mime' => $nuevoRegistro->mime_type,
                'version' => $nuevoRegistro->version,
            ];
        });
    }

    /**
     * Sube y registra una nueva versión del documento colaborativo (check-in) y libera el bloqueo.
     *
     * Incrementa la versión menor (ej. 1.0 -> 1.1) y calcula el hash de integridad SHA-256.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo al que pertenece el archivo
     * @param  User  $user  Usuario funcionario que sube la nueva versión
     * @param  UploadedFile  $archivo  Archivo físico cargado
     * @param  string|null  $comentario  Descripción o resumen de los cambios efectuados
     * @return MiBandejaTempArchivoVersion Registro de la nueva versión creada
     *
     * @throws \RuntimeException Si el usuario no tiene el bloqueo activo o el grupo carece de versiones
     */
    public function checkIn(MiBandejaTemp $grupo, User $user, UploadedFile $archivo, ?string $comentario = null): MiBandejaTempArchivoVersion
    {
        return DB::transaction(function () use ($grupo, $user, $archivo, $comentario) {
            $ultimaVersion = $grupo->ultimaVersion;

            if (! $ultimaVersion) {
                throw new \RuntimeException('El grupo no tiene documento que actualizar');
            }

            if (! $ultimaVersion->estaBloqueadoPor($user->id) && ! $ultimaVersion->bloqueoExpirado()) {
                if ($ultimaVersion->bloqueado_por_user_id !== null) {
                    throw new \RuntimeException('No tienes el documento bloqueado');
                }
            }

            $extension = $archivo->getClientOriginalExtension();
            $pesoKB = round($archivo->getSize() / 1024, 2);
            $uuid = Str::uuid().'.'.$extension;

            $radicado = $grupo->radicado;
            $numRadicado = $radicado?->num_radicado ?? 'sin-radicado';

            $rutaRelativa = Storage::disk(self::DISCO)->putFileAs($numRadicado, $archivo, $uuid);
            $rutaCompleta = Storage::disk(self::DISCO)->path($rutaRelativa);
            $hash = hash_file('sha256', $rutaCompleta);

            $partes = explode('.', $ultimaVersion->version);
            $patch = (int) ($partes[1] ?? 0) + 1;
            $nuevaVersion = "{$partes[0]}.{$patch}";

            $nuevoRegistro = MiBandejaTempArchivoVersion::create([
                'grupo_id' => $grupo->id,
                'version' => $nuevaVersion,
                'nombre_original' => $archivo->getClientOriginalName(),
                'nombre_archivo' => $uuid,
                'ruta_completa' => $rutaRelativa,
                'peso' => $pesoKB,
                'extension' => $extension,
                'mime_type' => $archivo->getMimeType(),
                'hash_seguridad' => $hash,
                'user_subio_id' => $user->id,
                'bloqueado_por_user_id' => null,
                'fecha_bloqueo' => null,
                'comentario_version' => $comentario,
            ]);

            $this->dispatchSafely(new DocumentoLiberado(
                $grupo->id,
                $nuevaVersion,
                $user->id,
                "{$user->nombres} {$user->apellidos}",
            ));

            return $nuevoRegistro;
        });
    }

    /**
     * Libera de forma manual el bloqueo de edición de un documento sin subir una nueva versión.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo
     * @param  User  $user  Usuario funcionario que tenía el bloqueo adquirido
     *
     * @throws \RuntimeException Si no hay documento o el usuario no tiene el bloqueo activo
     */
    public function liberarBloqueo(MiBandejaTemp $grupo, User $user): void
    {
        $version = $grupo->ultimaVersion;

        if (! $version) {
            throw new \RuntimeException('No hay documento que liberar');
        }

        if (! $version->estaBloqueadoPor($user->id)) {
            throw new \RuntimeException('No tienes un bloqueo activo en este documento');
        }

        DB::transaction(function () use ($version, $user) {
            $version->update([
                'bloqueado_por_user_id' => null,
                'fecha_bloqueo' => null,
            ]);

            $this->dispatchSafely(new DocumentoLiberado(
                $version->grupo_id,
                $version->version,
                $user->id,
                "{$user->nombres} {$user->apellidos}",
            ));
        });
    }

    /**
     * Marca la tarea asignada al usuario como cumplida dentro del grupo colaborativo.
     *
     * Si el usuario posee múltiples roles en el grupo (ej. revisor y firmante),
     * actualiza el estado de tarea y valida si todos los miembros completaron su labor
     * para transicionar el grupo a 'listo_envio'. Para el rol firmante, valida la identidad
     * mediante OTP institucional o re-autenticación con contraseña (ISO 27001).
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo
     * @param  User  $user  Usuario funcionario autenticado
     * @param  array{metodo?: string, otp?: string, password?: string}  $datosVerificacion  Datos de autenticación del firmante
     * @return array{rol: string, todos_cumplidos: bool, nuevo_estado: string|null}
     */
    public function marcarCumplido(MiBandejaTemp $grupo, User $user, array $datosVerificacion = []): array
    {
        return DB::transaction(function () use ($grupo, $user, $datosVerificacion) {
            $rolesCompletados = [];
            $userId = $user->id;

            $completarEntrada = function ($entrada, string $nombreRol) use (&$rolesCompletados) {
                if ($entrada && $entrada->estado_tarea !== 'cumplido') {
                    $entrada->update([
                        'estado_tarea' => 'cumplido',
                        'fechor_terminado' => Carbon::now(),
                        'descargo_plantilla' => true,
                    ]);
                    $rolesCompletados[] = $nombreRol;
                }
            };

            $revisor = $grupo->revisores()->where('user_id', $userId)->first();
            $completarEntrada($revisor, 'revisor');

            $proyector = $grupo->proyectores()->where('user_id', $userId)->first();
            $completarEntrada($proyector, 'proyector');

            $aprobador = $grupo->aprobadores()->where('user_id', $userId)->first();
            if ($aprobador && $aprobador->estado_tarea !== 'cumplido') {
                $otrosProyectoresPendientes = $grupo->proyectores()
                    ->where('user_id', '!=', $userId)
                    ->where('estado_tarea', '!=', 'cumplido')
                    ->exists();
                $otrosRevisoresPendientes = $grupo->revisores()
                    ->where('user_id', '!=', $userId)
                    ->where('estado_tarea', '!=', 'cumplido')
                    ->exists();
                if ($otrosProyectoresPendientes || $otrosRevisoresPendientes) {
                    throw new \RuntimeException('Deben completar su tarea todos los proyectores y revisores antes de que los aprobadores puedan marcar cumplido');
                }
                $completarEntrada($aprobador, 'aprobador');
            }

            $firmante = $grupo->firmantes()->where('user_id', $userId)->first();
            if ($firmante && $firmante->estado_tarea !== 'cumplido') {
                $otrosAprobadoresPendientes = $grupo->aprobadores()
                    ->where('user_id', '!=', $userId)
                    ->where('estado_tarea', '!=', 'cumplido')
                    ->exists();
                if ($otrosAprobadoresPendientes) {
                    throw new \RuntimeException('Deben completar su tarea todos los aprobadores antes de que los firmantes puedan marcar cumplido');
                }

                $otpUtilizado = null;
                if (! $firmante->fechor_firmado) {
                    $metodo = $datosVerificacion['metodo'] ?? null;
                    if ($metodo === 'otp') {
                        $otp = (string) ($datosVerificacion['otp'] ?? '');
                        if (empty($otp) || strlen($otp) !== 6) {
                            throw new \RuntimeException('Debe ingresar un código OTP válido de 6 dígitos.');
                        }
                        $valido = $this->firmaService->validarOtpPorDocumento($otp, 'grupo_colaborativo', $grupo->id, $user->id);
                        if (! $valido) {
                            throw new \RuntimeException('El código de seguridad (OTP) es incorrecto o ha expirado.');
                        }
                        $otpUtilizado = $otp;
                    } elseif ($metodo === 'password') {
                        $password = (string) ($datosVerificacion['password'] ?? '');
                        if (empty($password) || ! Hash::check($password, $user->password)) {
                            throw new \RuntimeException('La contraseña ingresada es incorrecta.');
                        }
                        $otpUtilizado = 'PASSWORD';
                    } else {
                        throw new \RuntimeException('Debe verificar su identidad mediante código OTP o su contraseña para firmar el documento.');
                    }
                }

                $completarEntrada($firmante, 'firmante');
                if (! $firmante->fechor_firmado) {
                    $firmante->update(['fechor_firmado' => now()]);
                    try {
                        $this->estamparFirmaFirmante($grupo, $user, $firmante, $otpUtilizado);
                    } catch (\Throwable $e) {
                        Log::warning('No se pudo estampar firma al marcar cumplido: '.$e->getMessage());
                    }
                }
            }

            if (empty($rolesCompletados)) {
                throw new \RuntimeException('No eres miembro de este grupo');
            }

            $rolPrincipal = $rolesCompletados[0];

            $grupo->refresh();
            $todosCumplidos = $grupo->todosTerminados();

            $nuevoEstado = null;
            if ($todosCumplidos) {
                $grupo->update(['estado' => 'listo_envio']);
                $nuevoEstado = 'listo_envio';
            }

            $this->dispatchSafely(new MiembroCumplido(
                $grupo->id,
                $user->id,
                "{$user->nombres} {$user->apellidos}",
                $rolPrincipal,
                $todosCumplidos,
                $nuevoEstado,
            ));

            return [
                'rol' => $rolPrincipal,
                'todos_cumplidos' => $todosCumplidos,
                'nuevo_estado' => $grupo->estado,
            ];
        });
    }

    /**
     * Finaliza el grupo colaborativo formalizando el envío de la respuesta al trámite.
     *
     * Valida que exista al menos un firmante, que todos los miembros asignados hayan
     * cumplido sus tareas, cambia el estado a 'finalizado' y marca como tramitado
     * el radicado vinculado si éste posee fecha de vencimiento.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo a despachar
     * @param  string|null  $respuestaFinal  Texto u observaciones finales del envío
     * @return MiBandejaTemp Grupo colaborativo actualizado
     *
     * @throws \RuntimeException Si faltan firmantes o miembros por terminar su labor
     */
    public function enviarTramite(MiBandejaTemp $grupo, ?string $respuestaFinal = null): MiBandejaTemp
    {
        return DB::transaction(function () use ($grupo, $respuestaFinal) {
            if ($grupo->firmantes()->count() === 0) {
                throw new \RuntimeException('El grupo colaborativo debe tener al menos un firmante asignado para enviar la respuesta');
            }

            if (! $grupo->todosTerminados()) {
                throw new \RuntimeException('Todos los miembros deben cumplir su tarea antes de enviar');
            }

            if ($grupo->estado_grupo !== 'activo') {
                throw new \RuntimeException('Solo grupos activos pueden enviarse a trámite');
            }

            $data = [
                'estado_grupo' => 'finalizado',
                'estado' => 'finalizado',
            ];

            if ($respuestaFinal) {
                $data['respuesta_final'] = $respuestaFinal;
            }

            $grupo->update($data);

            // Actualizar estado del radicado a TRAMITADO si tiene fecha de vencimiento
            $radicado = $grupo->radicado;
            if ($radicado && $radicado->fec_venci) {
                $radicado->updateQuietly(['estado_trabajo' => RadicadoEstadoTrabajoService::ESTADO_TRAMITADO]);
            }

            // Si es radicado recibido, vincular automáticamente la respuesta en ventanilla_radica_reci_respuestas
            if ($radicado && $grupo->radicado_tipo === 'recibido') {
                try {
                    RadicadoRespuesta::create([
                        'radicado_id' => $radicado->id,
                        'titulo' => $grupo->asunto ?? $grupo->nombre,
                        'contenido' => $respuestaFinal ?? 'Respuesta despachada desde el Grupo Colaborativo',
                        'user_crea_id' => auth()->id() ?? $grupo->usua_crea_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('No se pudo registrar RadicadoRespuesta al enviar trámite: '.$e->getMessage());
                }
            }

            // Copiar el documento final firmado y sellado a los adjuntos oficiales del radicado
            $ultimaVersion = $grupo->ultimaVersion;
            if ($radicado && $ultimaVersion) {
                try {
                    $discoGrupo = Storage::disk(self::DISCO);
                    if ($discoGrupo->exists($ultimaVersion->ruta_completa)) {
                        $contenidoDoc = $discoGrupo->get($ultimaVersion->ruta_completa);
                        $extensionDoc = $ultimaVersion->extension ?: 'docx';
                        $nombreEnDisco = Str::random(50).'.'.$extensionDoc;
                        $nombreHumano = 'Respuesta_Grupo_'.$grupo->id.'_'.$grupo->nombre.'.'.$extensionDoc;
                        $hashDoc = hash('sha256', $contenidoDoc);

                        if ($grupo->radicado_tipo === 'recibido') {
                            Storage::disk('radicados_recibidos')->put($nombreEnDisco, $contenidoDoc);
                            VentanillaRadicaReciArchivo::create([
                                'radicado_id' => $radicado->id,
                                'subido_por' => auth()->id() ?? $grupo->usua_crea_id,
                                'archivo' => $nombreEnDisco,
                                'archivo_tipo' => $ultimaVersion->mime_type ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'nom_origi' => $nombreHumano,
                                'archivo_peso' => strlen($contenidoDoc),
                                'hash_sha256' => $hashDoc,
                            ]);
                        } elseif ($grupo->radicado_tipo === 'interno') {
                            Storage::disk('radicados_internos')->put($nombreEnDisco, $contenidoDoc);
                            VentanillaRadicaInternoArchivos::create([
                                'radicado_id' => $radicado->id,
                                'subido_por' => auth()->id() ?? $grupo->usua_crea_id,
                                'archivo' => $nombreEnDisco,
                                'archivo_tipo' => $ultimaVersion->mime_type ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'nom_origi' => $nombreHumano,
                                'archivo_peso' => strlen($contenidoDoc),
                                'hash_sha256' => $hashDoc,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('No se pudo adjuntar documento de respuesta al radicado: '.$e->getMessage());
                }
            }

            return $grupo->fresh();
        });
    }

    /**
     * Libera de forma automática los bloqueos de edición que excedieron el tiempo límite permitido.
     *
     * Registra una traza de auditoría de FORCE_RELEASE por cada archivo liberado.
     *
     * @return int Cantidad de bloqueos liberados
     */
    public function liberarBloqueosVencidos(): int
    {
        $limite = Carbon::now()->subHours(self::HORAS_BLOQUEO);

        $bloqueados = MiBandejaTempArchivoVersion::whereNotNull('bloqueado_por_user_id')
            ->where('fecha_bloqueo', '<', $limite)
            ->get(['id', 'grupo_id', 'bloqueado_por_user_id', 'fecha_bloqueo']);

        if ($bloqueados->isEmpty()) {
            return 0;
        }

        $liberados = 0;
        foreach ($bloqueados as $v) {
            $v->updateQuietly(['bloqueado_por_user_id' => null, 'fecha_bloqueo' => null]);

            MiBandejaTempAuditLog::create([
                'grupo_id' => $v->grupo_id,
                'user_id' => $v->bloqueado_por_user_id ?? 1,
                'accion' => 'FORCE_RELEASE',
                'ip_origen' => '127.0.0.1',
                'user_agent' => 'Artisan:grupos-colaborativos:liberar-bloqueos',
                'payload_old' => [
                    'bloqueado_por_user_id' => $v->bloqueado_por_user_id,
                    'fecha_bloqueo' => $v->fecha_bloqueo?->toISOString(),
                ],
                'payload_new' => [
                    'bloqueado_por_user_id' => null,
                    'fecha_bloqueo' => null,
                ],
            ]);

            $liberados++;
        }

        return $liberados;
    }

    /**
     * Carga y registra la versión inicial 1.0 de un documento proporcionado manualmente por el usuario.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo
     * @param  User  $user  Usuario funcionario que realiza la carga
     * @param  UploadedFile  $archivo  Archivo físico cargado
     * @return MiBandejaTempArchivoVersion Versión 1.0 creada
     */
    public function subirVersionInicial(MiBandejaTemp $grupo, User $user, UploadedFile $archivo): MiBandejaTempArchivoVersion
    {
        return DB::transaction(function () use ($grupo, $user, $archivo) {
            $extension = $archivo->getClientOriginalExtension();
            $pesoKB = round($archivo->getSize() / 1024, 2);
            $uuid = Str::uuid().'.'.$extension;

            $radicado = $grupo->radicado;
            $numRadicado = $radicado?->num_radicado ?? 'sin-radicado';

            $rutaRelativa = Storage::disk(self::DISCO)->putFileAs($numRadicado, $archivo, $uuid);
            $rutaCompleta = Storage::disk(self::DISCO)->path($rutaRelativa);
            $hash = hash_file('sha256', $rutaCompleta);

            return MiBandejaTempArchivoVersion::create([
                'grupo_id' => $grupo->id,
                'version' => '1.0',
                'nombre_original' => $archivo->getClientOriginalName(),
                'nombre_archivo' => $uuid,
                'ruta_completa' => $rutaRelativa,
                'peso' => $pesoKB,
                'extension' => $extension,
                'mime_type' => $archivo->getMimeType(),
                'hash_seguridad' => $hash,
                'user_subio_id' => $user->id,
                'bloqueado_por_user_id' => null,
                'fecha_bloqueo' => null,
                'comentario_version' => null,
            ]);
        });
    }

    /**
     * Anula un grupo colaborativo activo.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo
     * @param  User  $user  Usuario funcionario solicitante (debe ser el creador)
     *
     * @throws \RuntimeException Si el usuario no es el creador o si el grupo ya está anulado
     */
    public function anular(MiBandejaTemp $grupo, User $user): void
    {
        if ($grupo->usua_crea_id !== $user->id) {
            throw new \RuntimeException('Solo el creador del grupo puede anularlo');
        }

        if ($grupo->estado_grupo === 'anulado') {
            throw new \RuntimeException('El grupo ya se encuentra anulado');
        }

        $grupo->update(['estado_grupo' => 'anulado']);
    }

    /**
     * Obtiene el listado de variables dinámicas institucionales y sus valores por defecto
     * para sustitución en la plantilla del grupo colaborativo.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo con sus relaciones cargadas
     * @param  User  $user  Usuario funcionario en sesión
     * @return array<int, array{clave: string, etiqueta: string, valor_defecto: mixed}>
     */
    public function obtenerVariablesPlantilla(MiBandejaTemp $grupo, User $user): array
    {
        $plantilla = $grupo->plantilla;
        $radicado = $grupo->radicado;
        $tercero = $radicado?->tercero;
        $miembrosNombres = fn ($items) => $items->map(fn ($m) => trim(($m->user?->nombres ?? '').' '.($m->user?->apellidos ?? '')))->filter()->implode(', ');

        $defaults = [
            'NUM_RADICADO' => $radicado?->num_radicado ?? '',
            'FECHA_RADICADO' => $radicado?->created_at?->format('Y-m-d') ?? '',
            'ASUNTO' => $grupo->asunto ?? '',
            'MEDIO_RESPUESTA' => '',
            'DIAS_RESPUESTA' => '',
            'TERCERO' => $tercero?->nom_razo_soci ?? '',
            'TERCERO_TIPO_DOC' => '',
            'TERCERO_NUM_DOC' => $tercero?->num_docu_nit ?? '',
            'TERCERO_TIPO_PERSONA' => $tercero?->tipo ?? '',
            'TERCERO_DIRECCION' => $tercero?->direccion ?? '',
            'TERCERO_TELEFONO' => $tercero?->telefono ?? '',
            'TERCERO_EMAIL' => $tercero?->email ?? '',
            'TERCERO_MUNICIPIO' => $tercero?->divisionPolitica?->nombre ?? '',
            'TERCERO_DEPARTAMENTO' => '',
            'NOMBRE_ENTIDAD' => config('app.entidad_nombre', ''),
            'NIT_ENTIDAD' => config('app.entidad_nit', ''),
            'NOMBRE_DEPENDENCIA' => $user->cargo?->dependencia?->nombre ?? '',
            'SIGLA_DEPENDENCIA' => $user->cargo?->dependencia?->sigla ?? '',
            'CIUDAD' => '',
            'CLASIFICA_DOCUMENTAL' => '',
            'SERIE' => '',
            'SUB_SERIE' => '',
            'TIPO_DOCUMENTAL' => '',
            'CODIGO_SERIE' => '',
            'CODIGO_SUB_SERIE' => '',
            'RESPONSABLES' => $miembrosNombres($grupo->revisores),
            'FIRMANTES' => $miembrosNombres($grupo->firmantes),
            'PROYECTORES' => $miembrosNombres($grupo->proyectores),
            'APROBADORES' => $miembrosNombres($grupo->aprobadores),
            'DESTINATARIOS' => '',
            'FUNCIONARIO_ACTUAL' => trim(($user->nombres ?? '').' '.($user->apellidos ?? '')),
            'CARGO_ACTUAL' => $user->cargo?->nombre ?? '',
            'FECHA_DOCUMENTO' => now()->format('Y-m-d'),
            'FECHA_ACTUAL' => now()->format('Y-m-d'),
            'CIUDAD_FECHA' => '',
            'NUMERO_FOLIOS' => '',
            'ANEXOS' => '',
            'NUM_RADICADO_RESPUESTA' => '',
            'FECHA_RADICADO_RESPUESTA' => '',
            'ASUNTO_RESPUESTA' => '',
            'NUM_RADICADO_ORIGEN' => '',
            'FECHA_RADICADO_ORIGEN' => '',
            'ASUNTO_ORIGEN' => '',
            'NOTIFICACION_DIRECCION' => $tercero?->direccion ?? '',
            'NOTIFICACION_CIUDAD' => $tercero?->divisionPolitica?->nombre ?? '',
            'NOTIFICACION_FECHA' => '',
            'NOTIFICACION_MEDIO' => '',
            'FIRMA' => '',
            'FIRMAS' => '',
            'FIRMA_DIGITAL' => '',
        ];

        $variables = array_map(fn ($key, $default) => [
            'clave' => $key,
            'etiqueta' => $this->etiquetaVariable($key),
            'valor_defecto' => $default,
        ], array_keys($defaults), $defaults);

        return $variables;
    }

    /**
     * Retorna la etiqueta legible para una clave de variable de plantilla.
     *
     * @param  string  $clave  Identificador o placeholder de la variable
     * @return string Nombre amigable para mostrar en interfaces
     */
    private function etiquetaVariable(string $clave): string
    {
        $etiquetas = [
            'NUM_RADICADO' => 'Número de radicado',
            'FECHA_RADICADO' => 'Fecha de radicado',
            'ASUNTO' => 'Asunto',
            'MEDIO_RESPUESTA' => 'Medio de respuesta',
            'DIAS_RESPUESTA' => 'Días de respuesta',
            'TERCERO' => 'Tercero (razón social)',
            'TERCERO_TIPO_DOC' => 'Tipo de documento del tercero',
            'TERCERO_NUM_DOC' => 'Número de documento del tercero',
            'TERCERO_TIPO_PERSONA' => 'Tipo de persona',
            'TERCERO_DIRECCION' => 'Dirección del tercero',
            'TERCERO_TELEFONO' => 'Teléfono del tercero',
            'TERCERO_EMAIL' => 'Email del tercero',
            'TERCERO_MUNICIPIO' => 'Municipio del tercero',
            'TERCERO_DEPARTAMENTO' => 'Departamento del tercero',
            'NOMBRE_ENTIDAD' => 'Nombre de la entidad',
            'NIT_ENTIDAD' => 'NIT de la entidad',
            'NOMBRE_DEPENDENCIA' => 'Nombre de la dependencia',
            'SIGLA_DEPENDENCIA' => 'Sigla de la dependencia',
            'CIUDAD' => 'Ciudad',
            'CLASIFICA_DOCUMENTAL' => 'Clasificación documental',
            'SERIE' => 'Serie documental',
            'SUB_SERIE' => 'Subserie documental',
            'TIPO_DOCUMENTAL' => 'Tipo documental',
            'CODIGO_SERIE' => 'Código de serie',
            'CODIGO_SUB_SERIE' => 'Código de subserie',
            'RESPONSABLES' => 'Responsables',
            'FIRMANTES' => 'Firmantes',
            'PROYECTORES' => 'Proyectores',
            'DESTINATARIOS' => 'Destinatarios',
            'FUNCIONARIO_ACTUAL' => 'Funcionario actual',
            'CARGO_ACTUAL' => 'Cargo actual',
            'FECHA_DOCUMENTO' => 'Fecha del documento',
            'FECHA_ACTUAL' => 'Fecha actual',
            'CIUDAD_FECHA' => 'Ciudad y fecha',
            'NUMERO_FOLIOS' => 'Número de folios',
            'ANEXOS' => 'Anexos',
            'NUM_RADICADO_RESPUESTA' => 'Número de radicado de respuesta',
            'FECHA_RADICADO_RESPUESTA' => 'Fecha de radicado de respuesta',
            'ASUNTO_RESPUESTA' => 'Asunto de respuesta',
            'NUM_RADICADO_ORIGEN' => 'Número de radicado de origen',
            'FECHA_RADICADO_ORIGEN' => 'Fecha de radicado de origen',
            'ASUNTO_ORIGEN' => 'Asunto de origen',
            'NOTIFICACION_DIRECCION' => 'Dirección de notificación',
            'NOTIFICACION_CIUDAD' => 'Ciudad de notificación',
            'NOTIFICACION_FECHA' => 'Fecha de notificación',
            'NOTIFICACION_MEDIO' => 'Medio de notificación',
            'FIRMA' => 'Firma digital / electrónica',
            'FIRMAS' => 'Firmas de los funcionarios',
            'FIRMA_DIGITAL' => 'Estampa de firma digital (ISO 27001)',
        ];

        return $etiquetas[$clave] ?? $clave;
    }

    /**
     * Discrimina si el archivo es un contenedor ZIP (.docx) o texto plano y sustituye variables.
     *
     * @param  string  $rutaCompleta  Ruta absoluta del archivo en disco
     * @param  array  $variables  Mapeo de variables a sustituir
     *
     * @throws \RuntimeException Si el archivo no existe en la ruta especificada
     */
    private function reemplazarVariablesEnArchivo(string $rutaCompleta, array $variables): void
    {
        if (! file_exists($rutaCompleta)) {
            throw new \RuntimeException('El archivo no existe: '.$rutaCompleta);
        }

        $handle = fopen($rutaCompleta, 'rb');
        $magicBytes = fread($handle, 4);
        fclose($handle);
        $esZip = $magicBytes === "PK\x03\x04" || $magicBytes === "PK\x05\x06" || $magicBytes === "PK\x07\x08";

        if ($esZip) {
            $this->reemplazarVariablesEnZip($rutaCompleta, $variables);
        } else {
            $this->reemplazarVariablesEnTexto($rutaCompleta, $variables);
        }
    }

    /**
     * Descomprime en memoria las partes XML de un paquete Office (.docx/.xlsx) y reemplaza variables.
     *
     * @param  string  $rutaCompleta  Ruta física del archivo Office
     * @param  array  $variables  Diccionario de variables a inyectar
     *
     * @throws \RuntimeException Si el archivo ZIP no puede ser abierto
     */
    private function reemplazarVariablesEnZip(string $rutaCompleta, array $variables): void
    {
        $zip = new \ZipArchive;
        $res = $zip->open($rutaCompleta);
        if ($res !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo ZIP: '.$rutaCompleta);
        }

        $entradasVariables = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = $zip->getNameIndex($i);
            if (
                str_ends_with($nombre, '.xml') ||
                str_ends_with($nombre, '.rels')
            ) {
                $entradasVariables[] = $nombre;
            }
        }

        $plantillaService = app(PlantillaDocumentoService::class);

        foreach ($entradasVariables as $nombre) {
            $contenido = $zip->getFromName($nombre);
            if ($contenido === false) {
                continue;
            }

            $original = $contenido;
            $contenido = $plantillaService->reemplazarVariables($contenido, $variables);

            if ($contenido !== $original) {
                $zip->addFromString($nombre, $contenido);
            }
        }

        $zip->close();
    }

    /**
     * Reemplaza variables institucionales en un archivo de texto plano o HTML.
     *
     * @param  string  $rutaCompleta  Ruta física del archivo
     * @param  array  $variables  Variables a sustituir
     *
     * @throws \RuntimeException Si falla la lectura o escritura del archivo
     */
    private function reemplazarVariablesEnTexto(string $rutaCompleta, array $variables): void
    {
        $contenido = file_get_contents($rutaCompleta);
        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el archivo para reemplazar variables');
        }

        $plantillaService = app(PlantillaDocumentoService::class);
        $contenido = $plantillaService->reemplazarVariables($contenido, $variables);

        $bytes = file_put_contents($rutaCompleta, $contenido, LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException('No se pudo escribir el archivo con las variables reemplazadas');
        }
    }

    /**
     * Inspecciona si el archivo contiene placeholders pendientes de reemplazo y los procesa de ser necesario.
     *
     * @param  string  $rutaCompleta  Ruta física del archivo
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo
     * @param  User  $user  Usuario funcionario en sesión
     */
    private function reemplazarVariablesSiTienePlaceholders(string $rutaCompleta, MiBandejaTemp $grupo, User $user): void
    {
        $handle = fopen($rutaCompleta, 'rb');
        $magicBytes = fread($handle, 4);
        fclose($handle);
        $esZip = $magicBytes === "PK\x03\x04" || $magicBytes === "PK\x05\x06" || $magicBytes === "PK\x07\x08";

        if ($esZip) {
            $zip = new \ZipArchive;
            $res = $zip->open($rutaCompleta);
            if ($res !== true) {
                return;
            }

            $tienePlaceholders = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = $zip->getNameIndex($i);
                if (! str_ends_with($nombre, '.xml') && ! str_ends_with($nombre, '.rels')) {
                    continue;
                }
                $contenido = $zip->getFromName($nombre);
                if ($contenido !== false && str_contains($contenido, '${NUM_RADICADO}')) {
                    $tienePlaceholders = true;
                    break;
                }
            }

            if ($tienePlaceholders) {
                $variables = $this->obtenerVariablesPlantilla($grupo, $user);
                $valores = [];
                foreach ($variables as $v) {
                    $valores[$v['clave']] = $v['valor_defecto'];
                }
                $zip->close();
                $this->reemplazarVariablesEnZip($rutaCompleta, $valores);
            } else {
                $zip->close();
            }
        }
    }

    /**
     * Actualiza el indicador de descarga de plantilla para el funcionario según el rol asignado en el grupo.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo
     * @param  User  $user  Usuario funcionario que ejecutó la descarga
     */
    private function marcarDescargaPlantilla(MiBandejaTemp $grupo, User $user): void
    {
        $miembro = MiBandejaTempGrupoRevisor::where('grupo_id', $grupo->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $miembro) {
            $miembro = MiBandejaTempGrupoProyector::where('grupo_id', $grupo->id)
                ->where('user_id', $user->id)
                ->first();
        }

        if (! $miembro) {
            $miembro = MiBandejaTempGrupoAprobador::where('grupo_id', $grupo->id)
                ->where('user_id', $user->id)
                ->first();
        }

        if (! $miembro) {
            $miembro = MiBandejaTempGrupoFirmante::where('grupo_id', $grupo->id)
                ->where('user_id', $user->id)
                ->first();
        }

        if ($miembro && ! $miembro->descargo_plantilla) {
            $miembro->update(['descargo_plantilla' => true]);
        }
    }

    /**
     * Estampa la firma del funcionario en el documento Word/texto del grupo colaborativo
     * y genera una constancia de firma electrónica con hash SHA-256 e integridad conforme a ISO 27001.
     *
     * Inyecta tanto la imagen gráfica de la firma registrada del usuario ($user->firma)
     * como la leyenda formal de certificación electrónica con fecha, hora, método de verificación y hash criptográfico.
     *
     * @param  MiBandejaTemp  $grupo  Grupo colaborativo al que pertenece el documento
     * @param  User  $user  Usuario funcionario firmante
     * @param  MiBandejaTempGrupoFirmante  $firmante  Registro del firmante en el grupo
     * @param  string|null  $otpUtilizado  Método o código OTP utilizado para la verificación
     * @return MiBandejaTempArchivoVersion|null Nueva versión generada o null si no hay documento
     */
    public function estamparFirmaFirmante(MiBandejaTemp $grupo, User $user, MiBandejaTempGrupoFirmante $firmante, ?string $otpUtilizado = null): ?MiBandejaTempArchivoVersion
    {
        return DB::transaction(function () use ($grupo, $user, $firmante, $otpUtilizado) {
            $ultimaVersion = $grupo->ultimaVersion;
            $diskPath = $ultimaVersion ? Storage::disk(self::DISCO)->path($ultimaVersion->ruta_completa) : null;
            $hayArchivo = $diskPath && file_exists($diskPath);

            $fechaFirma = $firmante->fechor_firmado ?? now();
            $fechaFormateada = $fechaFirma->format('Y-m-d H:i:s');
            $nombreFirmante = trim(($user->nombres ?? '').' '.($user->apellidos ?? ''));
            $cargoFirmante = $firmante->cargo?->nombre ?? $user->cargo?->nombre ?? 'Funcionario';

            $metodoTexto = in_array($otpUtilizado, ['PASSWORD', 'PASSWORD_REAUTH', 'CLAVE'])
                ? 'Re-autenticación con contraseña'
                : ($otpUtilizado ? "OTP correo institucional ({$otpUtilizado})" : 'Autenticación en sesión');

            $hashOriginal = $hayArchivo ? hash_file('sha256', $diskPath) : hash('sha256', "grupo_{$grupo->id}_{$user->id}_{$fechaFormateada}");
            $hashFirmado = $hashOriginal;

            // Preparar leyenda ISO 27001 de firma electrónica
            $leyendaIso = sprintf(
                "FIRMADO ELECTRÓNICAMENTE (ISO 27001 / Ley 527 de 1999)\nFirmante: %s - Cargo: %s\nFecha y Hora: %s\nMétodo de verificación: %s\nHash SHA-256: %s",
                $nombreFirmante,
                $cargoFirmante,
                $fechaFormateada,
                $metodoTexto,
                $hashOriginal
            );

            // Preparar bloque XML para documentos Word .docx
            $leyendaXmlDocx = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                .'<w:pPr><w:jc w:val="left"/><w:spacing w:before="120" w:after="120"/></w:pPr>'
                .'<w:r><w:rPr><w:b/><w:sz w:val="16"/><w:color w:val="2B579A"/></w:rPr><w:t>FIRMADO ELECTRÓNICAMENTE (ISO 27001 / Ley 527 de 1999)</w:t></w:r>'
                .'<w:r><w:br/><w:rPr><w:sz w:val="15"/><w:color w:val="444444"/></w:rPr><w:t>Firmante: '.htmlspecialchars($nombreFirmante, ENT_XML1).' - Cargo: '.htmlspecialchars($cargoFirmante, ENT_XML1).'</w:t></w:r>'
                .'<w:r><w:br/><w:rPr><w:sz w:val="15"/><w:color w:val="444444"/></w:rPr><w:t>Fecha y Hora: '.$fechaFormateada.' | Verificación: '.htmlspecialchars($metodoTexto, ENT_XML1).'</w:t></w:r>'
                .'<w:r><w:br/><w:rPr><w:sz w:val="14"/><w:color w:val="777777"/><w:i/></w:rPr><w:t>Hash SHA-256: '.$hashOriginal.'</w:t></w:r>'
                .'</w:p>';

            if ($hayArchivo) {
                $handle = fopen($diskPath, 'rb');
                $magicBytes = fread($handle, 4);
                fclose($handle);
                $esZip = $magicBytes === "PK\x03\x04" || $magicBytes === "PK\x05\x06" || $magicBytes === "PK\x07\x08";

                // Obtener ruta física de la imagen de firma del usuario si existe
                $rutaFirmaImg = null;
                if (! empty($user->firma) && Storage::disk('firmas')->exists($user->firma)) {
                    $rutaFirmaImg = Storage::disk('firmas')->path($user->firma);
                }

                if ($esZip) {
                    $zip = new \ZipArchive;
                    if ($zip->open($diskPath) === true) {
                        $mediaId = 'rFirma'.time();
                        $tieneImagen = false;

                        // Si el usuario tiene imagen de firma, inyectarla al paquete ZIP
                        if ($rutaFirmaImg && file_exists($rutaFirmaImg)) {
                            $extensionImg = pathinfo($rutaFirmaImg, PATHINFO_EXTENSION) ?: 'png';
                            $nombreMedia = 'firma_user_'.$user->id.'.'.$extensionImg;
                            $zip->addFile($rutaFirmaImg, 'word/media/'.$nombreMedia);

                            // Registrar la relación en word/_rels/document.xml.rels
                            $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
                            if ($relsXml !== false) {
                                $nuevaRel = '<Relationship Id="'.$mediaId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$nombreMedia.'"/>';
                                if (! str_contains($relsXml, $nuevaRel)) {
                                    $relsXml = str_replace('</Relationships>', $nuevaRel.'</Relationships>', $relsXml);
                                    $zip->addFromString('word/_rels/document.xml.rels', $relsXml);
                                }
                            }

                            $tieneImagen = true;
                        }

                        // Inyectar en document.xml la imagen y la constancia electrónica
                        $docXml = $zip->getFromName('word/document.xml');
                        if ($docXml !== false) {
                            $bloqueInsercion = '';
                            if ($tieneImagen) {
                                $bloqueInsercion .= '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                                    .'<w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0">'
                                    .'<wp:extent cx="1524000" cy="762000"/>'
                                    .'<wp:docPr id="'.rand(100, 999).'" name="Firma"/>'
                                    .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
                                    .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
                                    .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
                                    .'<pic:nvPicPr><pic:cNvPr id="0" name="Firma"/><pic:cNvPicPr/></pic:nvPicPr>'
                                    .'<pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="'.$mediaId.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
                                    .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="1524000" cy="762000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
                                    .'</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>'
                                    .'</w:p>';
                            }
                            $bloqueInsercion .= $leyendaXmlDocx;

                            // Reemplazar marcadores ${FIRMA}, ${FIRMAS}, ${FIRMA_DIGITAL} o anexar al cuerpo
                            if (str_contains($docXml, '${FIRMA}')) {
                                $docXml = str_replace('${FIRMA}', $bloqueInsercion, $docXml);
                            } elseif (str_contains($docXml, '${FIRMAS}')) {
                                $docXml = str_replace('${FIRMAS}', $bloqueInsercion, $docXml);
                            } elseif (str_contains($docXml, '${FIRMA_DIGITAL}')) {
                                $docXml = str_replace('${FIRMA_DIGITAL}', $bloqueInsercion, $docXml);
                            } else {
                                $docXml = str_replace('</w:body>', $bloqueInsercion.'</w:body>', $docXml);
                            }

                            $zip->addFromString('word/document.xml', $docXml);
                        }

                        $zip->close();
                    }
                } else {
                    // Documento texto plano / HTML
                    $contenido = file_get_contents($diskPath);
                    if ($contenido !== false) {
                        $bloqueTexto = "\n\n".$leyendaIso;
                        if (str_contains($contenido, '${FIRMA}')) {
                            $contenido = str_replace('${FIRMA}', $bloqueTexto, $contenido);
                        } elseif (str_contains($contenido, '${FIRMAS}')) {
                            $contenido = str_replace('${FIRMAS}', $bloqueTexto, $contenido);
                        } else {
                            $contenido .= $bloqueTexto;
                        }
                        file_put_contents($diskPath, $contenido, LOCK_EX);
                    }
                }

                // Recalcular hash de seguridad con la firma estampada
                $hashFirmado = hash_file('sha256', $diskPath);
                $ultimaVersion->updateQuietly([
                    'hash_seguridad' => $hashFirmado,
                    'peso' => round(filesize($diskPath) / 1024, 2),
                ]);
            }

            // Registrar trazabilidad inmutable en firmas_eventos (ISO 27001)
            FirmaEvento::create([
                'documentable_id' => $grupo->id,
                'documentable_type' => get_class($grupo),
                'user_id' => $user->id,
                'hash_original' => $hashOriginal,
                'hash_firmado' => $hashFirmado,
                'otp_utilizado' => substr($otpUtilizado ?? 'PASSWORD', 0, 10),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'Sistema Ocobo - Mi Bandeja Grupos',
                'fecha_firma' => $fechaFirma,
            ]);

            return $hayArchivo ? $ultimaVersion->fresh() : null;
        });
    }
}

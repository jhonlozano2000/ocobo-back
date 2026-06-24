<?php

namespace App\Services\MiBandeja;

use App\Events\MiBandeja\Grupos\DocumentoBloqueado;
use App\Events\MiBandeja\Grupos\DocumentoLiberado;
use App\Events\MiBandeja\Grupos\MiembroCumplido;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempArchivoVersion;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\MiBandeja\MiBandejaTempGrupoProyector;
use App\Models\MiBandeja\MiBandejaTempGrupoResponsable;
use App\Models\User;
use App\Models\MiBandeja\MiBandejaTempAuditLog;
use App\Models\OfiArchivo\OfiArchivoPlantillaDocumento;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GrupoColaborativoService
{
    private const DISCO = 'mi_bandeja_temp';
    private const HORAS_BLOQUEO = 24;

    public function __construct() {}

    public function checkOut(MiBandejaTemp $grupo, User $user): array
    {
        $version = $grupo->ultimaVersion;

        if (!$version) {
            if (!$grupo->plantilla_id) {
                throw new \RuntimeException('El grupo no tiene ninguna versión del documento ni plantilla asociada');
            }

            return $this->crearVersionDesdePlantilla($grupo, $user);
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

            Event::dispatch(new DocumentoBloqueado(
                $grupo->id,
                $user->id,
                "{$user->nombres} {$user->apellidos}",
                Carbon::now()->toISOString(),
            ));

            $path = $version->ruta_completa;
            if (!Storage::disk(self::DISCO)->exists($path)) {
                throw new \RuntimeException('El archivo no se encuentra en el servidor');
            }

            $diskPath = Storage::disk(self::DISCO)->path($path);
            $hashActual = hash_file('sha256', $diskPath);

            if ($hashActual !== $version->hash_seguridad) {
                throw new \RuntimeException(
                    'Integridad del archivo comprometida: el hash SHA-256 no coincide. '
                    . 'El archivo pudo haber sido modificado en el servidor.'
                );
            }

            return [
                'archivo' => $diskPath,
                'nombre' => $version->nombre_original,
                'mime' => $version->mime_type,
                'version' => $version->version,
            ];
        });
    }

    private function crearVersionDesdePlantilla(MiBandejaTemp $grupo, User $user): array
    {
        return DB::transaction(function () use ($grupo, $user) {
            $plantilla = OfiArchivoPlantillaDocumento::find($grupo->plantilla_id);

            if (!$plantilla) {
                throw new \RuntimeException('La plantilla asociada al grupo no se encuentra en el servidor');
            }

            if (!Storage::disk('plantillas')->exists($plantilla->ruta_completa)) {
                throw new \RuntimeException('El archivo de la plantilla no se encuentra en el servidor');
            }

            $extension = $plantilla->extension;
            $uuid = Str::uuid() . '.' . $extension;
            $rutaRelativa = "documentos/{$uuid}";

            Storage::disk(self::DISCO)->put(
                $rutaRelativa,
                Storage::disk('plantillas')->get($plantilla->ruta_completa)
            );

            $rutaCompleta = Storage::disk(self::DISCO)->path($rutaRelativa);
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

            Event::dispatch(new DocumentoBloqueado(
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

    public function checkIn(MiBandejaTemp $grupo, User $user, UploadedFile $archivo, ?string $comentario = null): MiBandejaTempArchivoVersion
    {
        return DB::transaction(function () use ($grupo, $user, $archivo, $comentario) {
            $ultimaVersion = $grupo->ultimaVersion;

            if (!$ultimaVersion) {
                throw new \RuntimeException('El grupo no tiene documento que actualizar');
            }

            if (!$ultimaVersion->estaBloqueadoPor($user->id) && !$ultimaVersion->bloqueoExpirado()) {
                if ($ultimaVersion->bloqueado_por_user_id !== null) {
                    throw new \RuntimeException('No tienes el documento bloqueado');
                }
            }

            $extension = $archivo->getClientOriginalExtension();
            $pesoKB = round($archivo->getSize() / 1024, 2);
            $uuid = Str::uuid() . '.' . $extension;

            $rutaRelativa = "documentos/{$uuid}";
            $rutaCompleta = Storage::disk(self::DISCO)->path($rutaRelativa);

            $directorio = dirname($rutaCompleta);
            if (!is_dir($directorio)) {
                mkdir($directorio, 0755, true);
            }

            $archivo->move($directorio, basename($rutaCompleta));

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

            Event::dispatch(new DocumentoLiberado(
                $grupo->id,
                $nuevaVersion,
                $user->id,
                "{$user->nombres} {$user->apellidos}",
            ));

            return $nuevoRegistro;
        });
    }

    public function liberarBloqueo(MiBandejaTemp $grupo, User $user): void
    {
        $version = $grupo->ultimaVersion;

        if (!$version) {
            throw new \RuntimeException('No hay documento que liberar');
        }

        if (!$version->estaBloqueadoPor($user->id)) {
            throw new \RuntimeException('No tienes un bloqueo activo en este documento');
        }

        DB::transaction(function () use ($version, $user) {
            $version->update([
                'bloqueado_por_user_id' => null,
                'fecha_bloqueo' => null,
            ]);

            Event::dispatch(new DocumentoLiberado(
                $version->grupo_id,
                $version->version,
                $user->id,
                "{$user->nombres} {$user->apellidos}",
            ));
        });
    }

    public function marcarCumplido(MiBandejaTemp $grupo, User $user): array
    {
        return DB::transaction(function () use ($grupo, $user) {
            $rol = null;
            $actualizado = false;

            $responsable = $grupo->responsables()->where('user_id', $user->id)->first();
            if ($responsable) {
                $responsable->update([
                    'estado_tarea' => 'cumplido',
                    'fechor_terminado' => Carbon::now(),
                    'descargo_plantilla' => true,
                ]);
                $rol = 'responsable';
                $actualizado = true;
            }

            if (!$actualizado) {
                $firmante = $grupo->firmantes()->where('user_id', $user->id)->first();
                if ($firmante) {
                    $firmante->update([
                        'estado_tarea' => 'cumplido',
                        'fechor_terminado' => Carbon::now(),
                        'descargo_plantilla' => true,
                    ]);
                    $rol = 'firmante';
                    $actualizado = true;
                }
            }

            if (!$actualizado) {
                $proyector = $grupo->proyectores()->where('user_id', $user->id)->first();
                if ($proyector) {
                    $proyector->update([
                        'estado_tarea' => 'cumplido',
                        'fechor_terminado' => Carbon::now(),
                        'descargo_plantilla' => true,
                    ]);
                    $rol = 'proyector';
                    $actualizado = true;
                }
            }

            if (!$actualizado) {
                throw new \RuntimeException('No eres miembro de este grupo');
            }

            $grupo->refresh();
            $todosCumplidos = $grupo->todosTerminados();

            $nuevoEstado = null;
            if ($todosCumplidos) {
                $grupo->update(['estado' => 'listo_envio']);
                $nuevoEstado = 'listo_envio';
            }

            Event::dispatch(new MiembroCumplido(
                $grupo->id,
                $user->id,
                "{$user->nombres} {$user->apellidos}",
                $rol,
                $todosCumplidos,
                $nuevoEstado,
            ));

            return [
                'rol' => $rol,
                'todos_cumplidos' => $todosCumplidos,
                'nuevo_estado' => $grupo->estado,
            ];
        });
    }

    public function enviarTramite(MiBandejaTemp $grupo, ?string $respuestaFinal = null): MiBandejaTemp
    {
        return DB::transaction(function () use ($grupo, $respuestaFinal) {
            if (!$grupo->todosTerminados()) {
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

            return $grupo->fresh();
        });
    }

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

    public function subirVersionInicial(MiBandejaTemp $grupo, User $user, UploadedFile $archivo): MiBandejaTempArchivoVersion
    {
        return DB::transaction(function () use ($grupo, $user, $archivo) {
            $extension = $archivo->getClientOriginalExtension();
            $pesoKB = round($archivo->getSize() / 1024, 2);
            $uuid = Str::uuid() . '.' . $extension;

            $rutaRelativa = "documentos/{$uuid}";
            $rutaCompleta = Storage::disk(self::DISCO)->path($rutaRelativa);

            $directorio = dirname($rutaCompleta);
            if (!is_dir($directorio)) {
                mkdir($directorio, 0755, true);
            }

            $archivo->move($directorio, basename($rutaCompleta));

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
}

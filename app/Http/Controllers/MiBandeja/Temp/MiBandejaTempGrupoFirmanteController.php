<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MiBandeja\Concerns\AutorizaGrupoColaborativo;
use App\Http\Requests\MiBandeja\StoreGrupoFirmanteRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTempGrupoFirmante;
use App\Models\Notificacion;
/**
 * Controlador para gestionar firmantes de grupos colaborativos temporales.
 * Permite agregar, actualizar, eliminar, marcar como terminado y firmar documentos.
 */
use App\Services\Firma\FirmaService;
use App\Services\MiBandeja\GrupoColaborativoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaTempGrupoFirmanteController extends Controller
{
    use ApiResponseTrait;
    use AutorizaGrupoColaborativo;

    private const PERM = 'Mi Bandeja - Grupos Colaborativos -> ';

    /**
     * Constructor del controlador.
     * Aplica middleware de permisos para gestión de miembros.
     */
    public function __construct(
        private readonly GrupoColaborativoService $grupoService,
        private readonly FirmaService $firmaService,
    ) {
        $this->middleware('can:'.self::PERM.'Gestionar Miembros')->only(['store', 'destroy', 'update']);
    }

    /**
     * Lista los firmantes de un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @return JsonResponse Respuesta JSON con los firmantes del grupo
     */
    public function index($grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $firmantes = $grupo->firmantes()->with(['user.cargo', 'cargo'])->get();

            return $this->successResponse($firmantes, 'Firmantes del grupo');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener firmantes', $e->getMessage(), 500);
        }
    }

    /**
     * Agrega un nuevo firmante a un grupo colaborativo temporal.
     *
     * @param  StoreGrupoFirmanteRequest  $request  Solicitud HTTP con datos del firmante
     * @param  int  $grupoId  Identificador del grupo
     * @return JsonResponse Respuesta JSON con el firmante creado
     */
    public function store(StoreGrupoFirmanteRequest $request, $grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            if ($grupo->firmantes()->where('user_id', $request->user_id)->exists()) {
                return $this->errorResponse('El usuario ya es firmante en este grupo', null, 422);
            }

            $firmante = MiBandejaTempGrupoFirmante::create([
                'grupo_id' => $grupoId,
                'user_id' => $request->user_id,
                'cargo_id' => $request->cargo_id,
                'orden_firma' => $request->integer('orden_firma', 1),
            ]);

            $firmante->load(['user.cargo', 'cargo']);

            // Crear notificación in-app para el firmante
            Notificacion::create([
                'user_id' => $request->user_id,
                'type' => 'asignacion_firmante',
                'title' => 'Firmante asignado en grupo colaborativo',
                'message' => 'Se le ha asignado como firmante del grupo "'.$grupo->nombre.'".',
                'notifiable_type' => 'App\Models\MiBandeja\MiBandejaTemp',
                'notifiable_id' => $grupoId,
                'data' => [
                    'grupo_id' => $grupoId,
                    'nombre_grupo' => $grupo->nombre,
                    'asignado_por' => auth()->id(),
                ],
            ]);

            return $this->successResponse($firmante, 'Firmante agregado exitosamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al agregar firmante', $e->getMessage(), 500);
        }
    }

    /**
     * Actualiza un firmante existente en un grupo colaborativo temporal.
     *
     * @param  Request  $request  Solicitud HTTP con datos a actualizar
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del firmante
     * @return JsonResponse Respuesta JSON con el firmante actualizado
     */
    public function update(Request $request, $grupoId, $id)
    {
        $this->autorizarGrupo($grupoId);

        // M07-01: el estado de la firma (fechor_firmado, fechor_terminado, plantillas) solo
        // cambia mediante firmar()/marcarTerminado(); aquí solo datos de la asignación.
        $datos = $request->validate([
            'cargo_id' => ['sometimes', 'nullable', 'integer', 'exists:calidad_organigrama,id'],
            'orden_firma' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $firmante = MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            if ($firmante->fechor_firmado) {
                return $this->errorResponse('El firmante ya firmó; su asignación no puede modificarse.', null, 422);
            }

            $firmante->update($datos);

            $firmante->load(['user.cargo', 'cargo']);

            return $this->successResponse($firmante, 'Firmante actualizado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al actualizar firmante', $e->getMessage(), 500);
        }
    }

    /**
     * Elimina un firmante de un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del firmante a eliminar
     * @return JsonResponse Respuesta JSON con resultado de la operación
     */
    public function destroy($grupoId, $id)
    {
        $this->autorizarGrupo($grupoId);

        try {
            $firmante = MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            if ($firmante->fechor_firmado) {
                return $this->errorResponse('No se puede retirar a un firmante que ya firmó el documento.', null, 422);
            }

            $totalFirmantes = MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->count();
            if ($totalFirmantes <= 1) {
                return $this->errorResponse('El grupo colaborativo debe conservar al menos un firmante.', null, 422);
            }

            $firmante->delete();

            return $this->successResponse(null, 'Firmante eliminado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al eliminar firmante', $e->getMessage(), 500);
        }
    }

    /**
     * Marca un firmante como terminado en un grupo colaborativo temporal.
     *
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del firmante a marcar como terminado
     * @return JsonResponse Respuesta JSON con el firmante actualizado
     */
    public function marcarTerminado($grupoId, $id)
    {
        $this->autorizarGrupo($grupoId);

        try {
            $firmante = MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            if ((int) $firmante->user_id !== (int) auth()->id()) {
                return $this->errorResponse('Solo el usuario titular puede marcar su tarea como terminada.', null, 403);
            }

            $firmante->update([
                'estado_tarea' => 'cumplido',
                'fechor_terminado' => now(),
                'descargo_plantilla' => true,
            ]);

            return $this->successResponse($firmante, 'Firmante marcado como terminado');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al marcar como terminado', $e->getMessage(), 500);
        }
    }

    /**
     * Solicita un código OTP de 6 dígitos para la firma electrónica del documento.
     * El código es enviado al correo institucional del funcionario firmante (validez: 5 minutos).
     *
     * @param  int  $grupoId  Identificador del grupo colaborativo
     * @return JsonResponse Respuesta JSON con correo enmascarado
     */
    public function solicitarOtp($grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $user = auth()->user();
            $firmante = $grupo->firmantes()->where('user_id', $user->id)->first();

            if (! $firmante) {
                return $this->errorResponse('No está registrado como firmante en este grupo colaborativo.', null, 403);
            }

            if ($firmante->fechor_firmado) {
                return $this->errorResponse('Ya ha firmado este documento previamente.', null, 422);
            }

            if (empty($user->email)) {
                return $this->errorResponse('El usuario no posee un correo electrónico registrado para el envío del código OTP.', null, 422);
            }

            $nombreDestino = trim(($user->nombres ?? '').' '.($user->apellidos ?? ''));

            $this->firmaService->generarYEnviarOtpParaEmail(
                $user->email,
                $nombreDestino,
                'grupo_colaborativo',
                $grupo->id,
                $user->id
            );

            // Enmascarar correo electrónico para privacidad
            $partes = explode('@', $user->email);
            $nombreUsuario = $partes[0];
            $dominio = $partes[1] ?? '';
            $longitud = strlen($nombreUsuario);
            $enmascarado = $longitud <= 2
                ? $nombreUsuario[0].'***@'.$dominio
                : $nombreUsuario[0].str_repeat('*', max(1, $longitud - 2)).substr($nombreUsuario, -1).'@'.$dominio;

            return $this->successResponse([
                'email_enmascarado' => $enmascarado,
                'expira_en_minutos' => 5,
            ], "Código de seguridad OTP enviado exitosamente al correo {$enmascarado}");
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al solicitar código OTP', $e->getMessage(), 500);
        }
    }

    /**
     * Registra la firma de un firmante en un grupo colaborativo temporal
     * previa verificación de identidad mediante OTP o contraseña (ISO 27001).
     *
     * @param  Request  $request  Solicitud HTTP con método ('otp' o 'password') y credencial
     * @param  int  $grupoId  Identificador del grupo
     * @param  int  $id  Identificador del firmante
     * @return JsonResponse Respuesta JSON con el firmante actualizado
     */
    public function firmar(Request $request, $grupoId, $id)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $firmante = MiBandejaTempGrupoFirmante::where('grupo_id', $grupoId)->find($id);

            if (! $firmante) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $user = auth()->user();
            if ($firmante->user_id !== $user->id) {
                return $this->errorResponse('Solo el usuario titular puede firmar su tarea asignada.', null, 403);
            }

            $metodo = $request->input('metodo');
            $otpUtilizado = null;

            if ($metodo === 'otp') {
                $otp = (string) $request->input('otp');
                if (empty($otp) || strlen($otp) !== 6) {
                    return $this->errorResponse('Debe ingresar un código OTP válido de 6 dígitos.', null, 422);
                }
                $valido = $this->firmaService->validarOtpPorDocumento($otp, 'grupo_colaborativo', $grupo->id, $user->id);
                if (! $valido) {
                    return $this->errorResponse('El código de seguridad (OTP) es incorrecto o ha expirado.', null, 422);
                }
                $otpUtilizado = $otp;
            } elseif ($metodo === 'password') {
                $password = (string) $request->input('password');
                if (empty($password) || ! Hash::check($password, $user->password)) {
                    return $this->errorResponse('La contraseña ingresada es incorrecta.', null, 422);
                }
                $otpUtilizado = 'PASSWORD';
            } else {
                return $this->errorResponse('Debe seleccionar un método de verificación (Código OTP o Contraseña) para firmar el documento.', null, 422);
            }

            // Firma en el documento del editor o estampada en el .docx (y la del sistema al firmar todos)
            DB::transaction(function () use ($firmante, $grupo, $user, $otpUtilizado) {
                $firmante->update([
                    'fechor_firmado' => now(),
                    'estado_tarea' => 'cumplido',
                    'fechor_terminado' => now(),
                    'descargo_plantilla' => true,
                ]);
                $this->grupoService->registrarFirma($grupo, $user, $firmante, $otpUtilizado);
            });

            // Actualizar estado del grupo si todos los miembros cumplieron su labor
            $grupo->refresh();
            if ($grupo->todosTerminados()) {
                $grupo->update(['estado' => 'listo_envio']);
            }

            $firmante->load(['user.cargo', 'cargo']);

            return $this->successResponse($firmante, 'Firma registrada y estampada en el documento exitosamente');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al registrar firma', $e->getMessage(), 500);
        }
    }
}

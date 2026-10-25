<?php

namespace App\Http\Controllers\Transversal;

use App\Helpers\FirmaElectronicaHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Transversal\FirmaEvento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\Firma\FirmaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FirmaElectronicaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private FirmaService $firmaService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Solicita un código OTP para firmar un documento.
     *
     * M06-02: solo un firmante designado del documento puede solicitarlo; el OTP se envía
     * a su propio correo y queda ligado al documento y al usuario.
     */
    public function solicitarOtp(Request $request)
    {
        $request->validate([
            'documentable_type' => 'required|string|in:radicado_enviado,radicado_recibido,radicado_interno',
            'documentable_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $tipo = $request->documentable_type;
        $id = (int) $request->documentable_id;

        [$modelo] = FirmaService::TIPOS_DOCUMENTO[$tipo];
        if (! $modelo::whereKey($id)->exists()) {
            return $this->errorResponse('Documento no encontrado', null, 404);
        }

        if (! $this->firmaService->esFirmanteDesignado($tipo, $id, $user->id)) {
            return $this->errorResponse('No es firmante designado de este documento.', null, 403);
        }

        if (empty($user->email)) {
            return $this->errorResponse('El usuario no tiene un correo electrónico configurado.', null, 422);
        }

        try {
            $nombre = trim("{$user->nombres} {$user->apellidos}");
            $this->firmaService->generarYEnviarOtpParaEmail($user->email, $nombre, $tipo, $id, $user->id);

            return $this->successResponse(null, 'Código OTP enviado a su correo electrónico.');
        } catch (\Exception $e) {
            Log::error('Error al enviar OTP de firma', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Error al enviar el código OTP', $e->getMessage(), 500);
        }
    }

    /**
     * Ejecuta la firma electrónica de un documento enviado.
     * Valida OTP, estampa PDF con sello visual, registra evento legal (ISO 27001).
     */
    public function firmarDocumento(Request $request)
    {
        $request->validate([
            'documentable_id' => 'required|integer',
            'documentable_type' => 'required|string|in:radicado_enviado,radicado_recibido,radicado_interno',
            'otp' => 'required|string|size:6',
            'firmado_en_representacion' => 'nullable|boolean',
            'nombre_representado' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        // M06-02: solo el firmante designado, con el OTP emitido para él y este documento
        if (! $this->firmaService->esFirmanteDesignado($request->documentable_type, (int) $request->documentable_id, $user->id)) {
            return $this->errorResponse('No es firmante designado de este documento.', null, 403);
        }

        // M03-19: un firmante de interno no firma dos veces el mismo documento
        if ($this->firmaService->yaFirmo($request->documentable_type, (int) $request->documentable_id, $user->id)) {
            return $this->warningResponse('Ya firmó este documento.');
        }

        // 1. Validar OTP por documento y usuario
        $otpValido = $this->firmaService->validarOtpPorDocumento($request->otp, $request->documentable_type, (int) $request->documentable_id, $user->id);
        if (! $otpValido) {
            return $this->errorResponse('Código OTP inválido o expirado', null, 403);
        }

        try {
            DB::beginTransaction();

            $documento = null;
            $disk = '';

            if ($request->documentable_type === 'radicado_enviado') {
                $documento = VentanillaRadicaEnviados::findOrFail($request->documentable_id);
                $disk = 'radicados_enviados';
            } elseif ($request->documentable_type === 'radicado_recibido') {
                $documento = VentanillaRadicaReci::findOrFail($request->documentable_id);
                $disk = 'radicados_recibidos';
            } elseif ($request->documentable_type === 'radicado_interno') {
                $documento = VentanillaRadicaInterno::findOrFail($request->documentable_id);
                $disk = 'radicados_internos';
            }

            if (! $documento || ! $documento->archivo_digital) {
                DB::rollBack();

                return $this->errorResponse('El documento original no tiene archivo PDF asociado para firmar', null, 422);
            }

            // Validar que el usuario tenga un cargo (para el sello visual)
            $cargo = $user->cargos()->first();
            $nombreCargo = $cargo ? $cargo->nom_organico : 'Funcionario';

            // Radicados antiguos sin hash registrado: se toma el del archivo actual (firmas_eventos lo exige)
            $hashOriginal = $documento->hash_sha256 ?: hash('sha256', Storage::disk($disk)->get($documento->archivo_digital));

            // 2. Estampar firma en el PDF (Helper)
            $datosFirma = [
                'nombre' => trim("{$user->nombres} {$user->apellidos}"),
                'cargo' => $nombreCargo,
                'fecha' => now()->format('Y-m-d H:i:s'),
                'hash_original' => $hashOriginal,
            ];

            $resultadoFirma = FirmaElectronicaHelper::estamparFirma(
                $disk,
                $documento->archivo_digital,
                $datosFirma
            );

            // 3. Registrar Evento Legal (ISO 27001)
            $evento = FirmaEvento::create([
                'documentable_id' => $documento->id,
                'documentable_type' => get_class($documento),
                'user_id' => $user->id,
                'hash_original' => $hashOriginal,
                'hash_firmado' => $resultadoFirma['nuevo_hash'],
                'timestamp_token' => $resultadoFirma['timestamp_token'],
                'timestamp_fecha' => $resultadoFirma['timestamp_fecha'],
                'otp_utilizado' => '***'.substr($request->otp, -3),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'fecha_firma' => now(),
            ]);

            $this->firmaService->marcarFirmante($request->documentable_type, $documento->id, $user->id, $request->otp);

            // 4. Actualizar el documento principal con el nuevo hash
            $documento->update([
                'hash_sha256' => $resultadoFirma['nuevo_hash'],
                'estado_firma' => 'firmado',
                'fecha_firma' => now(),
            ]);

            DB::commit();

            return $this->successResponse($evento, 'Documento firmado electrónicamente con éxito');

        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            DB::rollBack();

            Log::error('Error en proceso de firma electrónica', [
                'user_id' => $user->id,
                'documentable_type' => $request->documentable_type,
                'documentable_id' => $request->documentable_id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Error en el proceso de firma', $e->getMessage(), 500);
        }
    }
}

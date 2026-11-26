<?php

namespace App\Http\Controllers\Transversal;

use App\Helpers\FirmaElectronicaHelper;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Transversal\FirmaEvento;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Rules\PdfFirmable;
use App\Services\Firma\FirmaService;
use App\Services\Firma\PdfCompatible;
use App\Services\Firma\SolicitudFirmaRadicadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FirmaElectronicaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private FirmaService $firmaService,
        private SolicitudFirmaRadicadoService $solicitudFirmaService
    ) {
        $this->middleware('auth:sanctum');
    }

    /** El radicador solicita la firma; cada responsable se autentica al firmar. */
    public function solicitarFirma(Request $request)
    {
        $request->validate([
            'documentable_id' => 'required|integer',
            'metodo' => 'sometimes|in:otp,password',
            'otp' => $request->input('metodo') === 'otp' ? 'required|string|size:6' : 'prohibited',
            'password' => $request->input('metodo') === 'password' ? 'required|string' : 'prohibited',
        ]);

        $radicado = VentanillaRadicaReci::find($request->integer('documentable_id'));
        if (! $radicado) {
            return $this->errorResponse('Radicado no encontrado.', null, 404);
        }

        $user = $request->user();
        if (! $this->solicitudFirmaService->puedeSolicitar($radicado, $user)) {
            return $this->errorResponse('No tiene permiso para solicitar la firma de este radicado.', null, 403);
        }
        if ($this->firmaService->esFirmanteDesignado('radicado_recibido', $radicado->id, $user->id)) {
            return $this->errorResponse('Usted es responsable de este radicado. Firme con su propia contraseÃ±a o cÃ³digo OTP.', null, 422);
        }
        if (! $radicado->archivo_digital || $radicado->estado_firma === 'firmado') {
            return $this->errorResponse('El radicado debe tener un archivo digital pendiente de firma.', null, 422);
        }
        if ($request->metodo === 'otp') {
            if (! $this->firmaService->validarOtpPorDocumento($request->otp, SolicitudFirmaRadicadoService::TIPO_OTP, $radicado->id, $user->id)) {
                return $this->errorResponse('Código OTP inválido o expirado.', null, 403);
            }
        } elseif ($request->metodo === 'password' && ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('La contraseña ingresada es incorrecta.', null, 403);
        }

        $total = $this->solicitudFirmaService->solicitar($radicado, $user);
        if ($total === 0) {
            return $this->errorResponse('No hay responsables distintos del radicador para solicitar la firma.', null, 422);
        }

        return $this->successResponse(['responsables_notificados' => $total], 'Solicitud de firma enviada a los responsables.');
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
            'accion' => 'sometimes|in:solicitar_firma',
        ]);

        $user = Auth::user();
        $tipo = $request->documentable_type;
        $id = (int) $request->documentable_id;

        if ($request->accion === 'solicitar_firma') {
            if ($tipo !== 'radicado_recibido') {
                return $this->errorResponse('La solicitud de firma solo está disponible para radicados recibidos.', null, 422);
            }
            $radicado = VentanillaRadicaReci::find($id);
            if (! $radicado) {
                return $this->errorResponse('Radicado no encontrado.', null, 404);
            }
            if (! $this->solicitudFirmaService->puedeSolicitar($radicado, $user)) {
                return $this->errorResponse('No tiene permiso para solicitar la firma de este radicado.', null, 403);
            }
            if (! $radicado->archivo_digital || $radicado->estado_firma === 'firmado') {
                return $this->errorResponse('El radicado debe tener un archivo digital pendiente de firma.', null, 422);
            }
            if (empty($user->email)) {
                return $this->errorResponse('El usuario no tiene un correo electrónico configurado.', null, 422);
            }

            $nombre = trim("{$user->nombres} {$user->apellidos}");
            $this->firmaService->generarYEnviarOtpParaEmail($user->email, $nombre, SolicitudFirmaRadicadoService::TIPO_OTP, $id, $user->id);

            return $this->successResponse(null, 'Código OTP enviado a su correo electrónico.');
        }

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
     * Valida OTP o contraseña, estampa PDF con sello visual y registra el evento legal.
     */
    public function firmarDocumento(Request $request)
    {
        $metodo = $request->input('metodo', 'otp');
        $request->validate([
            'documentable_id' => 'required|integer',
            'documentable_type' => 'required|string|in:radicado_enviado,radicado_recibido,radicado_interno',
            'metodo' => 'sometimes|in:otp,password',
            'otp' => $metodo === 'otp' ? 'required|string|size:6' : 'prohibited',
            'password' => $metodo === 'password' ? 'required|string' : 'prohibited',
            'firmado_en_representacion' => 'nullable|boolean',
            'nombre_representado' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        // M06-02: solo el firmante designado, con el OTP emitido para él y este documento
        if (! $this->firmaService->esFirmanteDesignado($request->documentable_type, (int) $request->documentable_id, $user->id)) {
            return $this->errorResponse('No es firmante designado de este documento.', null, 403);
        }

        // M03-19: un firmante no firma dos veces el mismo documento
        if ($this->firmaService->yaFirmo($request->documentable_type, (int) $request->documentable_id, $user->id)) {
            return $this->warningResponse('Ya firmó este documento.');
        }

        // 1. Verificar la identidad antes de modificar el PDF o registrar la firma.
        if ($metodo === 'otp') {
            $otpValido = $this->firmaService->validarOtpPorDocumento($request->otp, $request->documentable_type, (int) $request->documentable_id, $user->id);
            if (! $otpValido) {
                return $this->errorResponse('Código OTP inválido o expirado', null, 403);
            }
        } elseif (! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('La contraseña ingresada es incorrecta.', null, 403);
        }

        $verificacion = $metodo === 'password' ? 'PASSWORD' : $request->otp;

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
                'otp_utilizado' => $metodo === 'password' ? 'PASSWORD' : '***'.substr($request->otp, -3),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'fecha_firma' => now(),
            ]);

            $this->firmaService->marcarFirmante($request->documentable_type, $documento->id, $user->id, $verificacion);

            // 4. Actualizar el documento con el nuevo hash; queda firmado cuando firma el último firmante
            $completa = $this->firmaService->todosFirmaron($request->documentable_type, $documento->id);
            $documento->update([
                'hash_sha256' => $resultadoFirma['nuevo_hash'],
                'estado_firma' => $completa ? 'firmado' : 'pendiente',
                'fecha_firma' => $completa ? now() : null,
            ]);

            DB::commit();

            return $this->successResponse(
                $evento->toArray() + ['firma_completa' => $completa],
                $completa ? 'Documento firmado electrónicamente con éxito' : 'Firma registrada; faltan otros firmantes'
            );

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

            if ($e->getMessage() === PdfFirmable::MENSAJE) {
                return $this->errorResponse(PdfFirmable::MENSAJE, null, 422);
            }
            if ($e->getMessage() === PdfCompatible::MENSAJE_SIN_QPDF) {
                return $this->errorResponse(PdfCompatible::MENSAJE_SIN_QPDF, null, 503);
            }

            return $this->errorResponse('Error en el proceso de firma', $e->getMessage(), 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Firma;

use App\Http\Controllers\Controller;
use App\Services\Firma\FirmaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FirmaController extends Controller
{
    protected $firmaService;

    public function __construct(FirmaService $firmaService)
    {
        $this->firmaService = $firmaService;
    }

    /**
     * Reglas comunes: M06-04, solo tipos de documento de la lista blanca (antes se
     * instanciaba 'App\\Models\\'.documento_type, es decir, cualquier modelo).
     */
    private function reglasDocumento(): array
    {
        return [
            'documento_type' => ['required', 'string', Rule::in(array_keys(FirmaService::TIPOS_DOCUMENTO))],
            'documento_id' => 'required|integer',
        ];
    }

    /**
     * Solicita un código OTP para firmar un documento (solo firmante designado).
     */
    public function solicitarOtp(Request $request)
    {
        $request->validate($this->reglasDocumento());

        $user = Auth::user();
        $tipo = $request->documento_type;
        $id = (int) $request->documento_id;

        if (! $user->email) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene un correo electrónico configurado.',
            ], 400);
        }

        if (! $this->firmaService->esFirmanteDesignado($tipo, $id, $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'No es firmante designado de este documento.',
            ], 403);
        }

        try {
            $nombre = trim("{$user->nombres} {$user->apellidos}");
            $this->firmaService->generarYEnviarOtpParaEmail($user->email, $nombre, $tipo, $id, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Código de seguridad enviado a su correo electrónico.',
            ]);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Valida el OTP y registra el evento de firma.
     */
    public function validarYFirmar(Request $request)
    {
        $request->validate($this->reglasDocumento() + [
            'otp' => 'required|digits:6',
            'hash_original' => 'nullable|string',
        ]);

        $user = Auth::user();
        $otp = $request->otp;
        $tipo = $request->documento_type;
        $id = (int) $request->documento_id;

        [$modelClass] = FirmaService::TIPOS_DOCUMENTO[$tipo];
        $documento = $modelClass::find($id);
        if (! $documento) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        if (! $this->firmaService->esFirmanteDesignado($tipo, $id, $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'No es firmante designado de este documento.',
            ], 403);
        }

        // Validar OTP ligado a documento + usuario
        if (! $this->firmaService->validarOtpPorDocumento($otp, $tipo, $id, $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Código OTP inválido o expirado.',
            ], 422);
        }

        // Registrar Firma
        try {
            $this->firmaService->registrarEventoFirma($user, $documento, [
                'otp' => $otp,
                'hash_original' => $request->hash_original,
                // Aquí se podría generar un nuevo hash si se estampara el PDF físicamente
                'hash_firmado' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Documento firmado exitosamente.',
            ]);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la firma: '.$e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Firma;

use App\Http\Controllers\Controller;
use App\Services\Firma\FirmaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FirmaController extends Controller
{
    protected $firmaService;

    public function __construct(FirmaService $firmaService)
    {
        $this->firmaService = $firmaService;
    }

    /**
     * Solicita un código OTP para el usuario autenticado.
     */
    public function solicitarOtp(Request $request)
    {
        $user = Auth::user();

        if (!$user->email) {
            return response()->json([
                "success" => false,
                "message" => "El usuario no tiene un correo electrónico configurado."
            ], 400);
        }

        try {
            $this->firmaService->generarYEnviarOtp($user);

            return response()->json([
                "success" => true,
                "message" => "Código de seguridad enviado a su correo (" . $user->email . ")."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Error al enviar el correo: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida el OTP y registra el evento de firma.
     */
    public function validarYFirmar(Request $request)
    {
        $request->validate([
            "otp" => "required|digits:6",
            "documento_id" => "required",
            "documento_type" => "required|string",
            "hash_original" => "nullable|string",
        ]);

        $user = Auth::user();
        $otp = $request->otp;

        // 1. Validar OTP
        if (!$this->firmaService->validarOtp($user, $otp)) {
            return response()->json([
                "success" => false,
                "message" => "Código OTP inválido o expirado."
            ], 422);
        }

        // 2. Resolver el modelo (Polimórfico)
        $modelClass = "App\\Models\\" . $request->documento_type;
        if (!class_exists($modelClass)) {
             return response()->json([
                "success" => false,
                "message" => "Tipo de documento no válido."
            ], 400);
        }

        $documento = $modelClass::find($request->documento_id);
        if (!$documento) {
            return response()->json([
                "success" => false,
                "message" => "Documento no encontrado."
            ], 404);
        }

        // 3. Registrar Firma
        try {
            $this->firmaService->registrarEventoFirma($user, $documento, [
                "otp" => $otp,
                "hash_original" => $request->hash_original,
                // Aqu� se podr�a generar un nuevo hash si se estampara el PDF f�sicamente
                "hash_firmado" => null 
            ]);

            return response()->json([
                "success" => true,
                "message" => "Documento firmado exitosamente."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Error al registrar la firma: " . $e->getMessage()
            ], 500);
        }
    }
}

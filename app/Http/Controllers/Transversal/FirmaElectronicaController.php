<?php

namespace App\Http\Controllers\Transversal;

use App\Http\Controllers\Controller;
use App\Models\Transversal\FirmaEvento;
use App\Models\VentanillaUnica\VentanillaRadicaEnviados;
use App\Helpers\FirmaElectronicaHelper;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FirmaElectronicaController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Solicita un código OTP para firmar un documento.
     * En producción esto enviaría un Email/SMS.
     */
    public function solicitarOtp(Request $request)
    {
        $user = Auth::user();
        
        // Generar OTP de 6 dígitos
        $otp = (string) random_int(100000, 999999);
        
        // Guardar en caché por 5 minutos
        $cacheKey = "firma_otp_{$user->id}";
        Cache::put($cacheKey, $otp, now()->addMinutes(5));

        // TODO: Aquí iría la integración con Mail/SMS (Mail::to($user->email)->send(new OtpMail($otp)))
        Log::info("🔑 OTP de Firma para {$user->email}: {$otp}");

        return $this->successResponse(null, 'Código OTP enviado al correo del usuario (Ver Log para testing)');
    }

    /**
     * Ejecuta la firma electrónica de un documento enviado.
     */
    public function firmarDocumento(Request $request)
    {
        $request->validate([
            'documentable_id' => 'required|integer',
            'documentable_type' => 'required|string|in:radicado_enviado',
            'otp' => 'required|string|size:6'
        ]);

        $user = Auth::user();
        $cacheKey = "firma_otp_{$user->id}";

        // 1. Validar OTP
        $cachedOtp = Cache::get($cacheKey);
        if (!$cachedOtp || $cachedOtp !== $request->otp) {
            return $this->errorResponse('Código OTP inválido o expirado', null, 403);
        }

        try {
            DB::beginTransaction();

            // Mapear modelo (por ahora solo radicados enviados)
            $documento = null;
            $disk = '';
            
            if ($request->documentable_type === 'radicado_enviado') {
                $documento = VentanillaRadicaEnviados::findOrFail($request->documentable_id);
                $disk = 'radicados_enviados'; // Suponiendo que este es el disco en config/filesystems
            }

            if (!$documento || !$documento->archivo_digital) {
                return $this->errorResponse('El documento original no tiene archivo PDF asociado para firmar', null, 422);
            }

            // Validar que el usuario tenga un cargo (para el sello visual)
            $cargo = $user->cargos()->first();
            $nombreCargo = $cargo ? $cargo->nom_organico : 'Funcionario';

            $hashOriginal = $documento->hash_sha256;

            // 2. Estampar firma en el PDF (Helper)
            $datosFirma = [
                'nombre' => trim("{$user->nombres} {$user->apellidos}"),
                'cargo' => $nombreCargo,
                'fecha' => now()->format('Y-m-d H:i:s'),
                'hash_original' => $hashOriginal
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
                'otp_utilizado' => '***' . substr($request->otp, -3), // Ofuscar por seguridad
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // 4. Actualizar el documento principal con el nuevo hash
            $documento->update([
                'hash_sha256' => $resultadoFirma['nuevo_hash'],
                'estado_firma' => 'Firmado' // Si existe este campo
            ]);

            Cache::forget($cacheKey); // Consumir el OTP

            DB::commit();

            return $this->successResponse($evento, 'Documento firmado electrónicamente con éxito');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error en el proceso de firma', $e->getMessage(), 500);
        }
    }
}

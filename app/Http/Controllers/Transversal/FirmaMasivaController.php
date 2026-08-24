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

class FirmaMasivaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private FirmaService $firmaService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Firma masiva de documentos.
     * Procesa cada documento individualmente; si uno falla, continúa con los demás.
     */
    public function firmarMasivo(Request $request)
    {
        $request->validate([
            'documentos' => 'required|array|min:1',
            'documentos.*.documentable_type' => 'required|string|in:radicado_enviado,radicado_recibido,radicado_interno',
            'documentos.*.documentable_id' => 'required|integer',
            'documentos.*.otp' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $resultados = [];

        foreach ($request->documentos as $index => $item) {
            $resultado = $this->procesarDocumento($user, $item);
            $resultados[] = $resultado;
        }

        $exitosos = count(array_filter($resultados, fn ($r) => $r['success']));
        $fallidos = count(array_filter($resultados, fn ($r) => ! $r['success']));

        return $this->successResponse([
            'resultados' => $resultados,
            'resumen' => [
                'total' => count($resultados),
                'exitosos' => $exitosos,
                'fallidos' => $fallidos,
            ],
        ], "Firma masiva completada: {$exitosos} éxitos, {$fallidos} fallos");
    }

    private function procesarDocumento($user, array $item): array
    {
        $documentableType = $item['documentable_type'];
        $documentableId = $item['documentable_id'];
        $otp = $item['otp'];

        try {
            // 1. Validar OTP
            $otpValido = $this->firmaService->validarOtpPorDocumento($otp, $documentableType, $documentableId);
            if (! $otpValido) {
                return [
                    'documentable_id' => $documentableId,
                    'documentable_type' => $documentableType,
                    'success' => false,
                    'error' => 'Código OTP inválido o expirado',
                ];
            }

            DB::beginTransaction();

            // 2. Resolver documento
            $documento = null;
            $disk = '';

            if ($documentableType === 'radicado_enviado') {
                $documento = VentanillaRadicaEnviados::findOrFail($documentableId);
                $disk = 'radicados_enviados';
            } elseif ($documentableType === 'radicado_recibido') {
                $documento = VentanillaRadicaReci::findOrFail($documentableId);
                $disk = 'radicados_recibidos';
            } elseif ($documentableType === 'radicado_interno') {
                $documento = VentanillaRadicaInterno::findOrFail($documentableId);
                $disk = 'radicados_internos';
            }

            if (! $documento || ! $documento->archivo_digital) {
                DB::rollBack();

                return [
                    'documentable_id' => $documentableId,
                    'documentable_type' => $documentableType,
                    'success' => false,
                    'error' => 'El documento no tiene archivo PDF asociado',
                ];
            }

            $cargo = $user->cargos()->first();
            $nombreCargo = $cargo ? $cargo->nom_organico : 'Funcionario';
            $hashOriginal = $documento->hash_sha256;

            // 3. Estampar firma
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

            // 4. Registrar evento legal
            FirmaEvento::create([
                'documentable_id' => $documento->id,
                'documentable_type' => get_class($documento),
                'user_id' => $user->id,
                'hash_original' => $hashOriginal,
                'hash_firmado' => $resultadoFirma['nuevo_hash'],
                'timestamp_token' => $resultadoFirma['timestamp_token'],
                'timestamp_fecha' => $resultadoFirma['timestamp_fecha'],
                'otp_utilizado' => '***'.substr($otp, -3),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'fecha_firma' => now(),
            ]);

            // 5. Actualizar documento
            $documento->update([
                'hash_sha256' => $resultadoFirma['nuevo_hash'],
                'estado_firma' => 'firmado',
                'fecha_firma' => now(),
            ]);

            DB::commit();

            return [
                'documentable_id' => $documentableId,
                'documentable_type' => $documentableType,
                'success' => true,
                'hash' => $resultadoFirma['nuevo_hash'],
            ];

        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            DB::rollBack();

            Log::error('Error en firma masiva', [
                'user_id' => $user->id,
                'documentable_type' => $documentableType,
                'documentable_id' => $documentableId,
                'error' => $e->getMessage(),
            ]);

            return [
                'documentable_id' => $documentableId,
                'documentable_type' => $documentableType,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

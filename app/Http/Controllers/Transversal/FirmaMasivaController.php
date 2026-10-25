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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FirmaMasivaController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private FirmaService $firmaService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * M06-13: documentos pendientes de firma del usuario actual.
     */
    public function pendientes(): JsonResponse
    {
        return $this->successResponse($this->firmaService->pendientesDe(Auth::id()), 'Documentos pendientes de firma');
    }

    /**
     * M06-13: envía un único OTP para el lote seleccionado. Se rechaza el lote completo si
     * incluye documentos de los que el usuario no es firmante o que ya firmó.
     */
    public function solicitarOtpMasivo(Request $request): JsonResponse
    {
        $documentos = $this->validarLote($request);
        $user = Auth::user();

        $noFirmables = array_values(array_filter($documentos, fn ($d) => ! $this->firmaService->esFirmanteDesignado($d['documentable_type'], $d['documentable_id'], $user->id)
            || $this->firmaService->yaFirmo($d['documentable_type'], $d['documentable_id'], $user->id)));

        if ($noFirmables) {
            return $this->errorResponse('El lote incluye documentos que no puede firmar.', ['no_firmables' => $noFirmables], 403);
        }

        if (empty($user->email)) {
            return $this->errorResponse('El usuario no tiene un correo electrónico configurado.', null, 422);
        }

        try {
            $this->firmaService->generarYEnviarOtpLote($user->email, trim("{$user->nombres} {$user->apellidos}"), $user->id, $documentos);

            return $this->successResponse(['total' => count($documentos)], 'Código OTP enviado a su correo electrónico.');
        } catch (\Exception $e) {
            Log::error('Error al enviar OTP de firma masiva', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return $this->errorResponse('Error al enviar el código OTP', null, 500);
        }
    }

    /**
     * Firma masiva de documentos con el OTP del lote.
     * Procesa cada documento individualmente; si uno falla, continúa con los demás.
     */
    public function firmarMasivo(Request $request)
    {
        $documentos = $this->validarLote($request, ['otp' => 'required|string|size:6']);
        $user = Auth::user();

        if (! $this->firmaService->validarOtpLote($request->otp, $user->id, $documentos)) {
            return $this->errorResponse('Código OTP inválido o expirado', null, 422);
        }

        $resultados = [];

        foreach ($documentos as $item) {
            $resultados[] = $this->procesarDocumento($user, $item + ['otp' => $request->otp]);
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

    /**
     * @return array<int, array{documentable_type: string, documentable_id: int}>
     */
    private function validarLote(Request $request, array $reglasExtra = []): array
    {
        $request->validate([
            'documentos' => 'required|array|min:1|max:'.FirmaService::MAX_LOTE,
            'documentos.*.documentable_type' => 'required|string|in:radicado_enviado,radicado_recibido,radicado_interno',
            'documentos.*.documentable_id' => 'required|integer',
        ] + $reglasExtra);

        return collect($request->documentos)
            ->map(fn ($d) => ['documentable_type' => $d['documentable_type'], 'documentable_id' => (int) $d['documentable_id']])
            ->unique(fn ($d) => $d['documentable_type'].':'.$d['documentable_id'])
            ->values()->all();
    }

    private function procesarDocumento($user, array $item): array
    {
        $documentableType = $item['documentable_type'];
        $documentableId = $item['documentable_id'];
        $otp = $item['otp'];

        try {
            // M06-02: solo el firmante designado (el OTP del lote ya se validó en firmarMasivo)
            if (! $this->firmaService->esFirmanteDesignado($documentableType, (int) $documentableId, $user->id)) {
                return [
                    'documentable_id' => $documentableId,
                    'documentable_type' => $documentableType,
                    'success' => false,
                    'error' => 'No es firmante designado de este documento',
                ];
            }

            // M03-19: un firmante de interno no firma dos veces el mismo documento
            if ($this->firmaService->yaFirmo($documentableType, (int) $documentableId, $user->id)) {
                return [
                    'documentable_id' => $documentableId,
                    'documentable_type' => $documentableType,
                    'success' => false,
                    'error' => 'Ya firmó este documento',
                ];
            }

            DB::beginTransaction();

            // 1. Resolver documento
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
            // Radicados antiguos sin hash registrado: se toma el del archivo actual (firmas_eventos lo exige)
            $hashOriginal = $documento->hash_sha256 ?: hash('sha256', Storage::disk($disk)->get($documento->archivo_digital));

            // 2. Estampar firma
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

            // 3. Registrar evento legal
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

            $this->firmaService->marcarFirmante($documentableType, $documento->id, $user->id, $otp);

            // 4. Actualizar documento
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
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
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

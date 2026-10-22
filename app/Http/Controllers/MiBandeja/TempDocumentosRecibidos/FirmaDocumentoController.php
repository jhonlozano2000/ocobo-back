<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\Transversal\FirmaEvento;
use App\Models\User;
use App\Services\Firma\FirmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Firma electrónica con OTP de un documento de Mis Documentos (Ley 527): como «Agregar una firma digital»
 * de Word, cada firma sella el hash del contenido y el documento queda protegido (solo lectura). Para volver
 * a editarlo se retiran las firmas; los eventos de firma son inmutables y quedan como historial.
 */
class FirmaDocumentoController extends Controller
{
    use ApiResponseTrait;

    /** Tipo con que se guarda el OTP del documento en FirmaService (independiente de los radicados). */
    private const TIPO_OTP = 'documento_colaborativo';

    public function __construct(private FirmaService $firmaService) {}

    /**
     * Estado de la firma: firmas vigentes, firmantes pendientes y lo que puede hacer el usuario.
     */
    public function index(Request $request, Documento $documento): JsonResponse
    {
        return $this->successResponse($this->estado($documento, $request->user()));
    }

    /**
     * Envía al correo del firmante un código OTP ligado a este documento y a él.
     */
    public function solicitarOtp(Request $request, Documento $documento): JsonResponse
    {
        $user = $request->user();

        if (! $documento->puedeFirmar($user)) {
            return $this->forbiddenResponse('No es firmante de este documento.');
        }

        if ($this->firmoVigente($documento, $user)) {
            return $this->warningResponse('Ya firmó este documento.');
        }

        if (empty($user->email)) {
            return $this->errorResponse('El usuario no tiene un correo electrónico configurado.', null, 422);
        }

        try {
            $this->firmaService->generarYEnviarOtpParaEmail($user->email, $this->nombre($user), self::TIPO_OTP, $documento->id, $user->id);
        } catch (\Exception $e) {
            Log::error('Error al enviar OTP de firma de documento', ['documento_id' => $documento->id, 'user_id' => $user->id, 'error' => $e->getMessage()]);

            return $this->errorResponse('No se pudo enviar el código de firma.', null, 500);
        }

        return $this->successResponse(
            ['correo' => $this->enmascararCorreo($user->email), 'minutos' => FirmaService::OTP_MINUTOS],
            'Código de firma enviado a su correo electrónico.'
        );
    }

    /**
     * Firma con el OTP: sella el hash del contenido actual y protege el documento.
     */
    public function firmar(Request $request, Documento $documento): JsonResponse
    {
        $request->validate(['otp' => ['required', 'digits:6']]);
        $user = $request->user();

        if (! $documento->puedeFirmar($user)) {
            return $this->forbiddenResponse('No es firmante de este documento.');
        }

        if ($this->firmoVigente($documento, $user)) {
            return $this->warningResponse('Ya firmó este documento.');
        }

        if (! $this->firmaService->validarOtpPorDocumento($request->otp, self::TIPO_OTP, $documento->id, $user->id)) {
            throw ValidationException::withMessages(['otp' => 'Código OTP inválido o expirado.']);
        }

        DB::transaction(function () use ($documento, $user, $request) {
            // Bloquea el contenido: lo que se firma es exactamente lo guardado en este momento
            $contenido = Contenido::where('documento_id', $documento->id)->lockForUpdate()->first();

            if (! $contenido?->hash_contenido) {
                throw ValidationException::withMessages(['documento' => 'El documento está vacío: no hay contenido para firmar.']);
            }

            $fecha = now();
            FirmaEvento::create([
                'documentable_id' => $documento->id,
                'documentable_type' => $documento->getMorphClass(),
                'user_id' => $user->id,
                'hash_original' => $contenido->hash_contenido,
                // Sello: el contenido firmado, por quién y cuándo
                'hash_firmado' => hash('sha256', $contenido->hash_contenido.'|'.$user->id.'|'.$fecha->toIso8601String()),
                'otp_utilizado' => '***'.substr($request->otp, -3),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'fecha_firma' => $fecha,
            ]);

            $documento->update(['estado' => 'firmado']);
        });

        return $this->successResponse(
            $this->estado($documento->fresh(), $user),
            'Documento firmado electrónicamente. Quedó protegido contra cambios.'
        );
    }

    /**
     * Retira las firmas para volver a editar: el documento vuelve a borrador y las firmas dejan de ser vigentes.
     */
    public function retirar(Request $request, Documento $documento): JsonResponse
    {
        $user = $request->user();

        if (! $documento->estaProtegido()) {
            return $this->warningResponse('El documento no está firmado.');
        }

        if ($documento->user_id !== $user->id && ! $documento->puedeFirmar($user)) {
            return $this->forbiddenResponse('Solo el creador o un firmante pueden retirar las firmas.');
        }

        $documento->update([
            'estado' => 'borrador',
            'firmas_retiradas_en' => now(),
            'firmas_retiradas_por' => $user->id,
        ]);

        Log::info('Firmas retiradas de documento colaborativo', ['documento_id' => $documento->id, 'user_id' => $user->id]);

        return $this->successResponse($this->estado($documento->fresh(), $user), 'Se retiraron las firmas: el documento se puede editar.');
    }

    private function estado(Documento $documento, User $user): array
    {
        $vigentes = $documento->firmasVigentes()->with('user:id,nombres,apellidos')->orderBy('fecha_firma')->get();
        $firmaron = $vigentes->pluck('user_id');
        $pendientes = $documento->firmantesRequeridos()->diff($firmaron);
        $protegido = $documento->estaProtegido();

        return [
            'estado' => $documento->estado,
            'protegido' => $protegido,
            'puede_firmar' => $documento->puedeFirmar($user) && ! $firmaron->contains($user->id),
            'puede_retirar' => $protegido && ($documento->user_id === $user->id || $documento->puedeFirmar($user)),
            'firmas' => $vigentes->map(fn (FirmaEvento $firma) => [
                'id' => $firma->id,
                'usuario' => $firma->user ? $this->nombre($firma->user) : null,
                'fecha' => $firma->fecha_firma?->toIso8601String(),
                'hash' => $firma->hash_original,
                'sello' => $firma->hash_firmado,
            ])->values(),
            'pendientes' => User::whereIn('id', $pendientes)->get(['id', 'nombres', 'apellidos'])
                ->map(fn (User $u) => ['id' => $u->id, 'nombre' => $this->nombre($u)])->values(),
        ];
    }

    private function firmoVigente(Documento $documento, User $user): bool
    {
        return $documento->firmasVigentes()->where('user_id', $user->id)->exists();
    }

    private function nombre(User $user): string
    {
        return trim("{$user->nombres} {$user->apellidos}") ?: 'Usuario';
    }

    /** j***@dominio.co: el usuario reconoce su correo sin exponerlo completo. */
    private function enmascararCorreo(string $correo): string
    {
        [$local, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$dominio;
    }
}

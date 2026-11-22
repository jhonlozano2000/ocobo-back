<?php

namespace App\Http\Middleware;

use App\Events\MiBandeja\TempReci\CamposGrupoActualizados;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Services\MiBandeja\CamposGrupoDocumento;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/** La asignación y sus campos se confirman o revierten en la misma transacción. */
class SincronizarCamposGrupo
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next)
    {
        $nivelInicial = DB::transactionLevel();
        DB::beginTransaction();
        try {
            $nuevo = $request->routeIs('mi-bandeja.temp.grupos.store');
            $grupo = MiBandejaTemp::find($request->route('grupo') ?? ($request->routeIs('mi-bandeja.temp.grupos.update') ? $request->route('id') : null));
            $documentoId = $nuevo ? $request->input('documento_id') : $grupo?->documento_id;
            if (! is_scalar($documentoId) || ! $documentoId) {
                DB::rollBack();

                return $next($request);
            }
            $contenido = Contenido::where('documento_id', $documentoId)->lockForUpdate()->first();
            $doc = Documento::lockForUpdate()->find($documentoId);
            if (! $doc) {
                DB::rollBack();

                return $next($request);
            }
            $doc->setRelation('contenido', $contenido);
            $sellado = $grupo?->firmantes()->whereNotNull('fechor_firmado')->exists();
            // El controlador comprueba el acceso al grupo antes de exponer su estado.
            $response = $next($request);

            $grupo = $grupo?->fresh() ?? $doc->grupoVigente();
            if (($response->isSuccessful() || $response->getStatusCode() === 422) && (! in_array($doc->estado, ['borrador', 'en_revision'], true) || $doc->firmasVigentes()->exists()
                || $sellado)) {
                DB::rollBack();

                return $this->conflictResponse('Retire las firmas o genere una nueva versión antes de modificar los participantes.');
            }
            if (! $response->isSuccessful()) {
                DB::rollBack();

                return $response;
            }
            if ($contenido && $grupo && $grupo->estado_grupo === 'activo') {
                $cambio = app(CamposGrupoDocumento::class)->syncCollaborativeGroupToDocument($contenido, $grupo, $request->user());
                if ($cambio) {
                    DB::afterCommit(fn () => rescue(fn () => event(new CamposGrupoActualizados($doc->id))));
                }
            }
            DB::commit();

            return $response;
        } catch (Throwable $error) {
            if (DB::transactionLevel() > $nivelInicial) {
                DB::rollBack();
            }
            throw $error;
        }
    }
}

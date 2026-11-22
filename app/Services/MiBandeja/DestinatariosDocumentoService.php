<?php

namespace App\Services\MiBandeja;

use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DestinatariosDocumentoService
{
    /** Serialize recipients separately from the collaborative group. */
    public function listar(Documento $documento): array
    {
        return [
            'internos' => $documento->destinatariosInternos()->orderByPivot('orden')->get()->map(fn ($u) => ['id' => $u->id, 'nombre' => trim($u->nombres.' '.$u->apellidos), 'email' => $u->email])->all(),
            'externos' => $documento->destinatariosExternos()->orderByPivot('orden')->get()->map(fn ($t) => ['id' => $t->id, 'nombre' => $t->nom_razo_soci, 'email' => $t->email])->all(),
        ];
    }

    /** Replace the whole ordered selection atomically, after locking the document. */
    public function guardar(Documento $documento, array $datos): array
    {
        DB::beginTransaction();
        try {
            $bloqueado = Documento::query()->lockForUpdate()->findOrFail($documento->id);
            if ($bloqueado->estaProtegido() || $bloqueado->firmasVigentes()->exists()) {
                throw new ConflictHttpException('Retire las firmas antes de cambiar destinatarios.');
            }
            $bloqueado->destinatariosInternos()->sync(collect($datos['internos'])->mapWithKeys(fn ($id, $orden) => [$id => ['orden' => $orden]])->all());
            $bloqueado->destinatariosExternos()->sync(collect($datos['externos'])->mapWithKeys(fn ($id, $orden) => [$id => ['orden' => $orden]])->all());
            $resultado = $this->listar($bloqueado);
            $contenido = $bloqueado->contenido()->lockForUpdate()->first();
            if ($contenido) {
                $doc = $contenido->contenido_yjs ?? [];
                $nombres = implode('; ', array_filter(array_merge(array_column($resultado['internos'], 'nombre'), array_column($resultado['externos'], 'nombre'))));
                $manuales = $contenido->campos_manuales ?? [];
                $actualizar = function (array &$nodo) use (&$actualizar, $nombres, $manuales): void {
                    $attrs = $nodo['attrs'] ?? [];
                    if (($nodo['type'] ?? null) === 'campoGrupo' && ($attrs['rol'] ?? null) === 'destinatarios'
                        && ($attrs['propiedad'] ?? null) === 'nombre' && ! empty($attrs['linked'])
                        && empty($attrs['manual_override']) && ! isset($manuales[$attrs['id'] ?? ''])) {
                        $nodo['attrs']['valor'] = $nombres;
                    }
                    foreach (array_keys($nodo['content'] ?? []) as $i) {
                        $actualizar($nodo['content'][$i]);
                    }
                };
                $original = $doc;
                $actualizar($doc);
                if ($doc !== $original) {
                    $contenido->actualizarContenido($doc, $bloqueado->creador);
                }
            }
            DB::commit();

            return $resultado;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

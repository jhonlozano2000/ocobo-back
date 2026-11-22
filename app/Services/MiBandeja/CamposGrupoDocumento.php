<?php

namespace App\Services\MiBandeja;

use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\TempDocumentosRecibidos\Contenido;
use App\Models\User;

class CamposGrupoDocumento
{
    private const CREDITOS = ['proyectores' => 'Proyectó', 'revisores' => 'Revisó'];

    public static function conCreditosDePlantilla(array $doc): array
    {
        $existentes = [];
        $buscar = function (array $nodo) use (&$buscar, &$existentes) {
            $attrs = $nodo['attrs'] ?? [];
            if (($nodo['type'] ?? '') === 'campoGrupo' && ($attrs['propiedad'] ?? '') === 'nombre') {
                $existentes[$attrs['rol'] ?? ''] = true;
            }
            foreach ($nodo['content'] ?? [] as $hijo) {
                $buscar($hijo);
            }
        };
        if (array_is_list($doc)) {
            $doc = ['type' => 'doc', 'content' => $doc];
        }
        // Una plantilla guardada desde otro documento puede traer valores del grupo anterior.
        foreach ($doc['content'] ?? [] as $i => $bloque) {
            $campo = $bloque['content'][0] ?? [];
            $attrs = $campo['attrs'] ?? [];
            if (($campo['type'] ?? '') === 'campoGrupo' && isset(self::CREDITOS[$attrs['rol'] ?? ''])
                && ($attrs['id'] ?? '') === 'credito-'.$attrs['rol'] && ! empty($attrs['linked']) && empty($attrs['manual_override'])) {
                $doc['content'][$i]['content'][0]['attrs']['valor'] = '';
            }
        }
        $buscar($doc);
        foreach (self::CREDITOS as $rol => $etiqueta) {
            if (! isset($existentes[$rol])) {
                $doc['content'][] = ['type' => 'paragraph', 'content' => [['type' => 'campoGrupo', 'attrs' => [
                    'id' => 'credito-'.$rol, 'rol' => $rol, 'propiedad' => 'nombre',
                    'valor' => '', 'linked' => true, 'manual_override' => false,
                ]]]];
            }
        }

        return $doc;
    }

    public static function creditoVacio(array $bloque): bool
    {
        return self::esCredito($bloque) && ($bloque['content'][0]['attrs']['valor'] ?? '') === '';
    }

    public static function esCredito(array $bloque): bool
    {
        $hijos = $bloque['content'] ?? [];
        $campo = $hijos[0] ?? [];
        $attrs = $campo['attrs'] ?? [];

        return ($bloque['type'] ?? '') === 'paragraph' && count($hijos) === 1
            && ($campo['type'] ?? '') === 'campoGrupo' && isset(self::CREDITOS[$attrs['rol'] ?? ''])
            && ($attrs['id'] ?? '') === 'credito-'.$attrs['rol'];
    }

    public function syncCollaborativeGroupToDocument(Contenido $contenido, MiBandejaTemp $grupo, User $actor): bool
    {
        $doc = $contenido->contenido_yjs ?? [];
        $original = $doc;
        $valores = [];
        $overrides = $contenido->campos_manuales ?? [];
        $visitar = function (array &$nodo) use (&$visitar, &$valores, $grupo, $overrides) {
            if (($nodo['type'] ?? null) === 'campoGrupo') {
                $attrs = $nodo['attrs'] ?? [];
                $rol = $attrs['rol'] ?? '';
                $campo = $attrs['propiedad'] ?? '';
                if (($attrs['linked'] ?? false) === true && empty($attrs['manual_override'])
                    && ! isset($overrides[$attrs['id'] ?? ''])
                ) {
                    if ($rol === 'grupo' && $campo === 'asunto') {
                        $nodo['attrs']['valor'] = (string) $grupo->asunto;
                    } elseif (in_array($rol, ['firmantes', 'revisores', 'aprobadores', 'proyectores', 'responsables'], true)
                    && in_array($campo, ['nombre', 'cargo', 'dependencia'], true)) {
                        $valores[$rol] ??= $this->participantes($grupo, $rol);
                        $valor = implode(', ', array_filter(array_column($valores[$rol], $campo)));
                        if ($valor !== '' && isset(self::CREDITOS[$rol]) && ($attrs['id'] ?? '') === 'credito-'.$rol) {
                            $valor = self::CREDITOS[$rol].': '.$valor;
                        }
                        $nodo['attrs']['valor'] = $valor;
                    }
                }
            }
            foreach (array_keys($nodo['content'] ?? []) as $i) {
                $visitar($nodo['content'][$i]);
            }
        };
        $visitar($doc);
        $pieCambio = $this->actualizarPie($grupo, $valores);
        if ($doc !== $original) {
            $contenido->actualizarContenido($doc, $actor);
        }

        return $doc !== $original || $pieCambio;
    }

    /** El texto fuente conserva sus tokens para que cada aparici?n se pueda editar independientemente. */
    public function actualizarPie(MiBandejaTemp $grupo, array $valores = []): bool
    {
        $documento = $grupo->documento;
        $pie = $documento->configuracion_footer;
        $texto = $pie['texto_libre'] ?? '';
        $resuelto = preg_replace_callback('/\{\{(firmantes|revisores|aprobadores|proyectores|responsables)(?:\.(nombre|cargo|dependencia))?\}\}/u',
            function ($m) use ($grupo, &$valores) {
                $valores[$m[1]] ??= $this->participantes($grupo, $m[1]);

                return implode(', ', array_filter(array_column($valores[$m[1]], $m[2] ?? 'nombre')));
            }, $texto, -1, $cantidad);
        if (! $cantidad && ! isset($pie['texto_grupo_resuelto'])) {
            return false;
        }
        $original = $pie;
        if ($cantidad) {
            $pie['texto_grupo_resuelto'] = $resuelto;
        } else {
            unset($pie['texto_grupo_resuelto']);
        }
        if ($pie === $original) {
            return false;
        }
        $documento->update(['configuracion_footer' => $pie]);

        return true;
    }

    public function firmantesOrdenados(?MiBandejaTemp $grupo): array
    {
        return $grupo ? $this->participantes($grupo, 'firmantes') : [];
    }

    private function participantes(MiBandejaTemp $grupo, string $rol): array
    {
        if ($rol === 'responsables') {
            // Documento::rolEnGrupo asigna responsabilidad a revisores y aprobadores.
            $personas = array_merge($this->participantes($grupo, 'revisores'), $this->participantes($grupo, 'aprobadores'));

            return array_values(array_column($personas, null, 'id'));
        }
        $query = $grupo->{$rol}()->with(['user.cargo', 'cargo']);
        if ($rol === 'firmantes') {
            $query->orderBy('orden_firma');
        }

        return $query->orderBy('id')->get()->map(function ($miembro) {
            $user = $miembro->user ?? $miembro->usuario;
            $cargo = $miembro->cargo ?? $user?->cargo;
            $dependencia = collect($cargo?->getJerarquiaCompleta() ?? [])->last(fn ($nivel) => ($nivel['tipo'] ?? '') === 'Dependencia');

            return [
                'id' => $miembro->user_id,
                'nombre' => trim($user?->nombres.' '.$user?->apellidos),
                'cargo' => $cargo?->nom_organico,
                'dependencia' => $dependencia['nom_organico'] ?? '',
            ];
        })->all();
    }
}

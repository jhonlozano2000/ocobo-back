<?php

namespace Database\Seeders\Configuracion;

use App\Models\Configuracion\ConfigLista;
use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Services\OfiArchivo\PlantillasBaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Plantillas institucionales de base del Generador de plantillas (oficio, memorando, circular y acta), con
 * membrete y variables. Quedan publicadas y se pueden editar o desactivar desde el Generador.
 *
 * Siembra sus tipos antes del contenido, también al ejecutarlo por separado. Idempotente: si ya existe una institucional con el
 * mismo nombre no la toca, para no pisar lo que se haya editado.
 */
class PlantillasGeneradasBaseSeed extends Seeder
{
    public function run(PlantillasBaseService $base): void
    {
        DB::beginTransaction();
        try {
            $this->call(TiposPlantillaSeed::class);
            $tipos = ConfigLista::where('cod', TiposPlantillaSeed::COD_LISTA)->firstOrFail()->detalles()->pluck('id', 'codigo');

            foreach (array_keys(PlantillasBaseService::PLANTILLAS) as $clave) {
                $plantilla = $base->construir($clave);

                $registro = OfiArchivoPlantillaGenerada::withTrashed()->firstOrCreate(
                    ['nombre' => $plantilla['nombre'], 'institucional' => true, 'user_id' => null],
                    [
                        'tipo_id' => $tipos[$plantilla['tipo']],
                        'descripcion' => $plantilla['descripcion'],
                        'activo' => true,
                        'contenido' => $plantilla['contenido'],
                        'configuracion' => $plantilla['configuracion'],
                    ]
                );
                $anteriores = [
                    'memorando' => 'ce0a82d32002f5670e356a55c087095473379b2355745eb00fffb79816e2d89b',
                    'oficio' => 'f89a02faa3a45adf84f0de757b14fcafcfe0810eebca9eb1e8139095507e3443',
                    'circular' => 'ae0138005bfb3d7716447afaddb98d1e3a9426980443744b49e777d56cc6f7d5',
                ];
                if (isset($anteriores[$clave]) && ! $registro->trashed() &&
                    hash('sha256', json_encode($this->canonico($registro->contenido ?? []))) === $anteriores[$clave]) {
                    $registro->update(['contenido' => $plantilla['contenido']]);
                }
            }
            DB::commit();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }
    }

    private function canonico(array $nodo): array
    {
        if (! array_is_list($nodo)) {
            ksort($nodo);
        }
        return array_map(fn ($v) => is_array($v) ? $this->canonico($v) : $v, $nodo);
    }
}

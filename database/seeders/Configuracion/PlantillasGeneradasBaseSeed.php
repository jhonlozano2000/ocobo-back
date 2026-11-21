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

                OfiArchivoPlantillaGenerada::withTrashed()->firstOrCreate(
                    ['nombre' => $plantilla['nombre'], 'institucional' => true, 'user_id' => null],
                    [
                        'tipo_id' => $tipos[$plantilla['tipo']],
                        'descripcion' => $plantilla['descripcion'],
                        'activo' => true,
                        'contenido' => $plantilla['contenido'],
                        'configuracion' => $plantilla['configuracion'],
                    ]
                );
            }
            DB::commit();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }
    }
}

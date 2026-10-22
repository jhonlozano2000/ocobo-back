<?php

namespace Database\Seeders\Configuracion;

use App\Models\Configuracion\ConfigLista;
use App\Models\OfiArchivo\OfiArchivoPlantillaGenerada;
use App\Services\OfiArchivo\PlantillasBaseService;
use Illuminate\Database\Seeder;

/**
 * Plantillas institucionales de base del Generador de plantillas (oficio, memorando, circular y acta), con
 * membrete y variables. Quedan publicadas y se pueden editar o desactivar desde el Generador.
 *
 * Requiere TiposPlantillaSeed (para clasificarlas por tipo). Idempotente: si ya existe una institucional con el
 * mismo nombre no la toca, para no pisar lo que se haya editado.
 */
class PlantillasGeneradasBaseSeed extends Seeder
{
    public function run(PlantillasBaseService $base): void
    {
        $tipos = ConfigLista::where('cod', TiposPlantillaSeed::COD_LISTA)->first()?->detalles()->pluck('id', 'codigo') ?? collect();

        foreach (array_keys(PlantillasBaseService::PLANTILLAS) as $clave) {
            $plantilla = $base->construir($clave);

            OfiArchivoPlantillaGenerada::withTrashed()->firstOrCreate(
                ['nombre' => $plantilla['nombre'], 'institucional' => true, 'user_id' => null],
                [
                    'tipo_id' => $tipos[$plantilla['tipo']] ?? null,
                    'descripcion' => $plantilla['descripcion'],
                    'activo' => true,
                    'contenido' => $plantilla['contenido'],
                    'configuracion' => $plantilla['configuracion'],
                ]
            );
        }
    }
}

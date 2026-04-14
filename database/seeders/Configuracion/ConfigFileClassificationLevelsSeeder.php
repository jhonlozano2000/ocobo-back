<?php

namespace Database\Seeders\Configuracion;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigFileClassificationLevelsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('config_file_classification_levels')->insert([
            [
                'codigo' => 'PUBLICO',
                'nombre' => 'Público',
                'descripcion' => 'Información que puede ser difundida sin restricciones',
                'color_hex' => '#10B981',
                'nivel_sensibilidad' => 1,
                'plazo_retencion_meses' => 24,
                'es_eliminable' => true,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'INTERNO',
                'nombre' => 'Interno',
                'descripcion' => 'Información para uso interno de la entidad',
                'color_hex' => '#3B82F6',
                'nivel_sensibilidad' => 2,
                'plazo_retencion_meses' => 60,
                'es_eliminable' => true,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'CONFIDENCIAL',
                'nombre' => 'Confidencial',
                'descripcion' => 'Información sensible que requiere control de acceso',
                'color_hex' => '#F59E0B',
                'nivel_sensibilidad' => 3,
                'plazo_retencion_meses' => 120,
                'es_eliminable' => false,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'RESERVADO',
                'nombre' => 'Reservado',
                'descripcion' => 'Información que por ley debe ser conservada permanentemente',
                'color_hex' => '#F97316',
                'nivel_sensibilidad' => 4,
                'plazo_retencion_meses' => 0,
                'es_eliminable' => false,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'SECRETO',
                'nombre' => 'Secreto',
                'descripcion' => 'Información crítica que requiere autorización especial',
                'color_hex' => '#EF4444',
                'nivel_sensibilidad' => 5,
                'plazo_retencion_meses' => 0,
                'es_eliminable' => false,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

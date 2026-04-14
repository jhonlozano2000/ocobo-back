<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_file_classification_levels', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 15)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->string('color_hex', 7)->default('#6B7280');
            $table->integer('nivel_sensibilidad')->default(1);
            $table->integer('plazo_retencion_meses')->default(24);
            $table->boolean('es_eliminable')->default(true);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

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

    public function down(): void
    {
        Schema::dropIfExists('config_file_classification_levels');
    }
};

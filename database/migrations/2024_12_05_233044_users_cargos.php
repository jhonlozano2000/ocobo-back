<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla pivot users_cargos para relacionar usuarios con cargos del organigrama.
     * Permite gestionar el histórico de cargos de cada usuario con fechas de inicio y fin.
     */
    public function up(): void
    {
        Schema::create('users_cargos', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('user_id')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade')
                ->comment('ID del usuario');

            $table->foreignId('organigrama_id')
                ->constrained('calidad_organigrama')
                ->onUpdate('cascade')
                ->onDelete('cascade')
                ->comment('ID del cargo en el organigrama');

            // Fechas de vigencia del cargo
            $table->date('fecha_inicio')
                ->comment('Fecha de inicio en el cargo');

            $table->date('fecha_fin')
                ->nullable()
                ->comment('Fecha de fin en el cargo (null = cargo activo)');

            // Información adicional
            $table->string('observaciones', 500)
                ->nullable()
                ->comment('Observaciones sobre la asignación del cargo');

            $table->boolean('estado')
                ->default(true)
                ->comment('Estado de la asignación (true = activo, false = inactivo)');

            // Auditoría
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['user_id', 'estado']);
            $table->index(['organigrama_id', 'estado']);
            $table->index(['fecha_inicio', 'fecha_fin']);

            // Constraint: Solo puede haber un cargo activo por usuario
            // Nota: Este constraint se maneja a nivel de aplicación debido a limitaciones de MySQL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_cargos');
    }
};

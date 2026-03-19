<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ventanilla_pqrs', function (Blueprint $table) {
            $table->id();

            // Vínculo 1:1 con Radicación Recibida
            $table->unsignedBigInteger('radicado_id')->unique();
            $table->foreign('radicado_id')->references('id')->on('ventanilla_radica_reci')->onDelete('cascade');

            // Clasificación del Trámite
            $table->unsignedBigInteger('tipo_pqrs_id')->comment('Referencia a config_listas (Petición, Queja, etc)');
            $table->foreign('tipo_pqrs_id')->references('id')->on('config_lista_detalles');

            // Responsabilidad Administrativa
            $table->unsignedBigInteger('dependencia_responsable_id')->comment('Oficina encargada de dar respuesta');
            $table->foreign('dependencia_responsable_id')->references('id')->on('calidad_organigrama');

            // Estados y Términos Legales
            $table->enum('estado_tramite', ['Pendiente', 'En Tramite', 'Respondida', 'Vencida'])->default('Pendiente');
            $table->date('fecha_vencimiento')->comment('Fecha límite de respuesta (Calculada en días hábiles)');
            $table->date('fecha_vencimiento_original')->nullable()->comment('Fecha de vencimiento inicial antes de prórroga');
            $table->boolean('tiene_prorroga')->default(false);
            
            // Metadatos de Ley 1755 / 1437
            $table->boolean('es_anonimo')->default(false)->comment('Si es true, ofuscar datos del remitente en vistas operativas');
            $table->enum('canal_preferido', ['Correo Electronico', 'Correo Fisico'])->default('Correo Electronico');
            $table->enum('prioridad', ['Normal', 'Urgente', 'Tutela'])->default('Normal');

            // Cierre del Trámite
            $table->timestamp('fecha_respuesta')->nullable()->comment('Fecha y hora en que se radicó la respuesta');
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Para integridad ISO 27001

            // Índices para el Dashboard de Semaforización
            $table->index(['fecha_vencimiento', 'estado_tramite']);
            $table->index('dependencia_responsable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_pqrs');
    }
};

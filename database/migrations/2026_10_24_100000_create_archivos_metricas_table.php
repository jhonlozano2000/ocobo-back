<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métricas de archivos: una fila por archivo cargado o eliminado en cualquier módulo
 * (Ventanilla, Mi Bandeja, Gestión de Archivo, Workflows). Se llena desde el trait
 * RegistraMetricaArchivo de cada modelo de archivo y, para lo ya existente, con
 * php artisan metricas:archivos-backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivos_metricas', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 10)->comment('cargado | eliminado');
            $table->string('modulo', 40);
            $table->string('submodulo', 40);
            $table->string('archivable_type');
            $table->unsignedBigInteger('archivable_id');
            $table->char('ruta_hash', 40)->comment('sha1 de la ruta: distingue reemplazos del mismo registro');
            $table->string('extension', 20)->default('');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Dependencia del usuario al momento del evento (snapshot: no cambia si luego lo trasladan)
            $table->unsignedBigInteger('dependencia_id')->nullable();
            $table->string('dependencia_nom')->nullable();
            $table->timestamp('ocurrido_en');
            $table->timestamp('created_at')->nullable();

            $table->index(['ocurrido_en', 'modulo']);
            $table->index(['modulo', 'submodulo', 'ocurrido_en']);
            $table->index(['extension', 'ocurrido_en']);
            $table->index(['dependencia_id', 'ocurrido_en']);
            $table->index(['archivable_type', 'archivable_id', 'ruta_hash', 'evento'], 'archivos_metricas_archivable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_metricas');
    }
};

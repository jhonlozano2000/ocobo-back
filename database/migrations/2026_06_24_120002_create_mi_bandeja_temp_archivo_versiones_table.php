<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_archivo_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('mi_bandeja_temp')->cascadeOnDelete();
            $table->string('version', 10);
            $table->string('nombre_original', 255);
            $table->string('nombre_archivo', 255);
            $table->string('ruta_completa', 512);
            $table->decimal('peso', 10, 2);
            $table->string('extension', 10);
            $table->string('mime_type', 127);
            $table->string('hash_seguridad', 64);
            $table->foreignId('user_subio_id')->constrained('users');
            $table->foreignId('bloqueado_por_user_id')->nullable()->constrained('users');
            $table->timestamp('fecha_bloqueo')->nullable();
            $table->text('comentario_version')->nullable();
            $table->timestamps();

            $table->index('version');
            $table->index(['grupo_id', 'version']);
            $table->index('bloqueado_por_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_archivo_versiones');
    }
};

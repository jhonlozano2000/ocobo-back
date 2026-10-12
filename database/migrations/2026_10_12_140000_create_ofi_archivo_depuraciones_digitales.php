<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M08-02: bitácora de la depuración digital. Cada archivo huérfano que entra a cuarentena,
 * se restaura o se purga queda registrado con su huella, en lugar de solo un Log::info.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_depuraciones_digitales', function (Blueprint $table) {
            $table->id();
            $table->string('disco', 60);
            $table->string('accion', 20)->comment('cuarentena|restaurado|purgado');
            $table->string('ruta_original', 500);
            $table->string('ruta_cuarentena', 500)->nullable();
            $table->string('hash_sha256', 64)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->string('origen', 100)->comment('Comando o proceso que ejecutó la acción');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['disco', 'accion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_depuraciones_digitales');
    }
};

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
        Schema::create('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->id();

            // Vínculo al expediente padre
            $table->unsignedBigInteger('expediente_id');
            $table->foreign('expediente_id')->references('id')->on('ofi_archivo_expedientes')->onDelete('cascade');

            // Foliación Automática (Acuerdo 003/2015)
            $table->integer('numero_folio')->comment('Orden secuencial e inalterable del documento en el expediente');

            // Relación Polimórfica (Radicados Recibidos, Enviados, Internos, etc)
            $table->morphs('documentable', 'ofi_archivo_exp_doc_polymorphic');

            // Metadatos de la pieza documental
            $table->text('detalle')->nullable()->comment('Descripción o notas del usuario sobre este archivo específico');
            $table->timestamp('fecha_incorporacion')->useCurrent();

            // Auditoría
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id')->references('id')->on('users');

            $table->timestamps();

            // Índice para búsquedas rápidas en expedientes voluminosos
            $table->index(['expediente_id', 'numero_folio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_expedientes_documentos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->id();

            // 1. Vínculo al expediente padre
            $table->foreignId('expediente_id')->constrained('ofi_archivo_expedientes')->onDelete('cascade');

            // 2. Relación Polimórfica (Radicados Recibidos, Enviados, Internos)
            $table->morphs('documentable', 'ofi_archivo_exp_doc_polymorphic');

            // 3. Foliación Automática e Inalterable (Acuerdo 003/2015)
            $table->unsignedSmallInteger('numero_folio')->comment('Orden secuencial e inalterable del documento');
            $table->unsignedSmallInteger('orden')->comment('Orden de visualización UI');

            // 4. Metadatos del Índice Electrónico (ISO 15489 / AGN 003)
            $table->string('tipo_documental', 200)->comment('Ej: Resolución, Oficio, Anexo');
            $table->date('fecha_documento')->comment('Fecha de producción del documento original');
            $table->string('asunto', 500);
            $table->string('autor', 200)->nullable();
            $table->string('formato_archivo', 20)->nullable()->comment('Ej: pdf, docx, xml');
            $table->unsignedBigInteger('tamano_bytes')->nullable();

            // 5. Criptografía e Integridad (ISO 27001)
            $table->string('hash_sha256', 64)->nullable()->comment('Garantiza que el archivo físico no fue alterado tras foliarse');

            // 6. Información adicional y control de estado
            $table->text('detalle')->nullable()->comment('Descripción o notas del usuario');
            $table->boolean('activo')->default(true)->comment('Borrado lógico para no perder el rastro del folio');
            $table->timestamp('fecha_incorporacion')->useCurrent();

            // 7. Auditoría
            $table->foreignId('usuario_id')->comment('Usuario que indexó el folio')->constrained('users');

            $table->timestamps();

            // 8. Restricción CRÍTICA: Un folio no puede repetirse en el mismo expediente
            $table->unique(['expediente_id', 'numero_folio'], 'uk_expediente_folio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofi_archivo_expedientes_documentos');
    }
};

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
        Schema::create('ia_borradores_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_radicado', 50)->nullable()->index();
            $table->string('tipo_radicado', 30)->nullable();
            $table->unsignedBigInteger('radicado_id')->nullable();
            $table->char('asunto_hash', 64)->index();
            $table->char('prompt_hash', 64)->index();
            $table->string('modelo_llm', 100);
            $table->string('estado', 50)->index();
            $table->decimal('score_confianza', 5, 4)->nullable();
            $table->json('citas_utilizadas')->nullable();
            $table->json('advertencias')->nullable();
            $table->json('tokens_verificar')->nullable();
            $table->longText('borrador_generado')->nullable();
            $table->decimal('tiempo_retrieval_ms', 8, 2)->nullable();
            $table->decimal('tiempo_reranking_ms', 8, 2)->nullable();
            $table->decimal('tiempo_llm_ms', 8, 2)->nullable();
            $table->decimal('tiempo_total_ms', 8, 2)->nullable();
            $table->boolean('fallback')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ia_borradores_auditoria');
    }
};

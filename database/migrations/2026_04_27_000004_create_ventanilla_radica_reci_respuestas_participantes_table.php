<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_reci_respuestas_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->constrained('ventanilla_radica_reci_respuestas')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('rol', ['editor', 'revisor', 'aprobador'])->default('editor');
            $table->boolean('puede_editar')->default(true);
            $table->boolean('puede_revisar')->default(false);
            $table->boolean('puede_aprobar')->default(false);
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->timestamps();
            $table->unique(['respuesta_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_respuestas_participantes');
    }
};
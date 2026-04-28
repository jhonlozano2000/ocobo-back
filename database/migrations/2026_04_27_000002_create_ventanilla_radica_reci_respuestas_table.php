<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_reci_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radicado_id')->constrained('ventanilla_radica_reci')->onDelete('cascade');
            $table->string('titulo', 500)->nullable();
            $table->longText('contenido')->nullable();
            $table->json('contenido_json')->nullable();
            $table->integer('version')->default(1);
            $table->integer('version_actual')->default(1);
            $table->enum('estado', ['borrador', 'en_edicion', 'finalizado', 'enviado'])->default('borrador');
            $table->foreignId('user_editando_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_inicio_edicion')->nullable();
            $table->integer('lock_tiempo')->default(300);
            $table->foreignId('user_crea_id')->constrained('users');
            $table->foreignId('user_actualiza_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('estado');
            $table->index(['radicado_id', 'estado']);
            $table->index(['user_editando_id', 'fecha_inicio_edicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_respuestas');
    }
};
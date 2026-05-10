<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_reci_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radica_reci_id')->nullable()->constrained('ventanilla_radica_reci')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('titulo');
            $table->enum('estado', ['borrador', 'en_revision', 'firmado'])->default('borrador');
            $table->text('notas')->nullable();
            $table->boolean('es_publico')->default(false);
            $table->timestamps();

            $table->index('radica_reci_id');
            $table->index('user_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_recibido_documentos');
    }
};
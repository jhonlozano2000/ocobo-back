<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_reci_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('mi_bandeja_temp_reci_documentos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('rol'); // firmante, responsable, proyector
            $table->timestamp('ultimo_acceso')->nullable();
            $table->timestamps();

            $table->unique(['documento_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_reci_usuarios');
    }
};

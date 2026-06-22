<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_bandeja_temp_reci_cursores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('mi_bandeja_temp_reci_documentos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nombre_usuario');
            $table->string('color');
            $table->integer('posicion')->default(0);
            $table->string('seleccion_inicio')->nullable();
            $table->string('seleccion_fin')->nullable();
            $table->timestamp('ultima_actividad')->useCurrent();
            $table->timestamps();

            $table->index('documento_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_bandeja_temp_reci_cursores');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_reci_respuestas_version', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->constrained('ventanilla_radica_reci_respuestas')->onDelete('cascade');
            $table->integer('version');
            $table->longText('contenido')->nullable();
            $table->json('contenido_json')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->text('cambios_resumen')->nullable();
            $table->timestamps();
            $table->unique(['respuesta_id', 'version']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_respuestas_version');
    }
};
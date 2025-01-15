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
        Schema::create('logs_globales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Usuario que realiza la acción
            $table->string('accion'); // Acción realizada (ejemplo: "CREAR_RADICADO")
            $table->text('detalles')->nullable(); // Datos relevantes de la acción
            $table->string('ip')->nullable(); // IP del usuario
            $table->string('user_agent')->nullable(); // Navegador o cliente
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_globals');
    }
};

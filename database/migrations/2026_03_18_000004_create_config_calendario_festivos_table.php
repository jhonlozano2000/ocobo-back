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
        Schema::create('config_calendario_festivos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique()->comment('Fecha del día festivo o no laboral');
            $table->string('nombre', 150)->comment('Nombre del festivo (Ej: Navidad, San José)');
            $table->enum('tipo', ['Nacional', 'Regional', 'Empresarial'])->default('Nacional');
            $table->integer('anio')->index()->comment('Año de la fecha para optimizar consultas');
            $table->timestamps();
            
            $table->index('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_calendario_festivos');
    }
};

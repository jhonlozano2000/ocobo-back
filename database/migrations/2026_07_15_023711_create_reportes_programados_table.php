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
        Schema::create('reportes_programados', function (Blueprint $table) {
            $table->id();
            $table->string('modulo');
            $table->json('filtros');
            $table->string('formato', 10);
            $table->string('periodicidad', 20);
            $table->string('asunto');
            $table->json('destinatarios');
            $table->timestamp('ultima_ejecucion')->nullable();
            $table->timestamp('proxima_ejecucion');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reportes_programados');
    }
};

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
        Schema::create('clasificacion_documental_trd_versiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dependencia_id');
            $table->foreign('dependencia_id')->references('id')->on('calidad_organigrama');

            $table->integer('version')->default(1);
            $table->enum('estado_version', ['TEMP', 'ACTIVO', 'HISTORICO'])->default('TEMP');
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->foreign('aprobado_por')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clasificacion_documental_t_r_d_versions');
    }
};

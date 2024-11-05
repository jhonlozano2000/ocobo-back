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
        Schema::create('calidad_organi_cargos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('oficina_id');
            $table->foreign('oficina_id')->references('id')->on('calidad_organi_oficinas');
            $table->string('nom_cargo')->nullable();
            $table->string('observaciones')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calidad_organi_cargos');
    }
};

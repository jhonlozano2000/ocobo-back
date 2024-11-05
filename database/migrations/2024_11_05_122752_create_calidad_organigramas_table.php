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
        Schema::create('calidad_organigrama', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 15);
            $table->string('nom_organico', 100);
            $table->string('cod_organico', 10)->nullable();
            $table->string('observaciones')->nullable();
            $table->unsignedBigInteger('parent')->nullable();
            $table->foreign('parent')->references('id')->on('organigrama');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calidad_organigrama');
    }
};

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
        Schema::create('config_listas_detalles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('lista_id');
            $table->foreign('lista_id')->on('config_listas')->references('id');

            $table->string('codigo', 20);
            $table->string('nombre', 70);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_listas_detalles');
    }
};

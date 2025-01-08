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
        Schema::create('config_server_archivos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('proceso_id');
            $table->foreign('proceso_id')->on('config_listas_detalles')->references('id');

            $table->string('host', 11);
            $table->string('ruta', 100)->nullable();
            $table->string('user', 20);
            $table->string('password');
            $table->string('detalle', 200)->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_server_archivos');
    }
};

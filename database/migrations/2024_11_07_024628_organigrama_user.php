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
        Schema::create('calidad_organigrama_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organigrama_id');
            $table->date('start_date'); // Fecha en la que el usuario comienza el cargo
            $table->date('end_date')->nullable(); // Fecha en la que el usuario dejó el cargo, puede ser nulo si sigue activo
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('organigrama_id')->references('id')->on('calidad_organigrama')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calidad_organigrama_user');
    }
};

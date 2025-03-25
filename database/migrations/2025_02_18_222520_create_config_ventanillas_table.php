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
        Schema::create('config_ventanillas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sede_id');
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('numeracion_unificada')->default(true)
                ->comment('Define si la numeración de radicados es unificada o por sede.');
            $table->timestamps();

            $table->foreign('sede_id')->references('id')->on('config_sedes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_ventanillas');
    }
};

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
        Schema::create('calidad_organi_dependencias', function (Blueprint $table) {
            $table->id();
            $table->string('cod_depen', 15)->nullable()->comment('Codigo de la dependencia o codigo de correspondencia de la dependencia');
            $table->string('nom_depen', 150)->comment('Nmbre de la dependneica');
            $table->string('observaciones')->nullable();;
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calidad_organi_dependencias');
    }
};

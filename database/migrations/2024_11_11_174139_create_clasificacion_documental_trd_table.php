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
        Schema::create('clasificacion_documental_trd', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('parent')->nullable();
            $table->foreign('parent')->references('id')->on('clasificacion_documental_trd');

            $table->unsignedBigInteger('user_register');
            $table->foreign('user_register')->references('id')->on('users');

            $table->unsignedBigInteger('dependencia_id')->nullable();
            $table->foreign('dependencia_id')->references('id')->on('calidad_organigrama');

            $table->string('tipo', 15);
            $table->string('cod', 10)->nullable();
            $table->string('nom', 100)->nullable();
            $table->string('a_g')->nullable()->comment('Archivo de gestrión');
            $table->string('a_c')->nullable()->comment('Archivo Central');
            $table->string('ct')->nullable()->comment('Conservación Total');
            $table->string('e')->nullable()->comment('Eliminación');
            $table->string('m_d')->nullable()->comment('M: Microfilmación - D:Digitalización ');
            $table->string('s')->nullable()->comment('Selección');
            $table->text('procedimiento')->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clasificacion_documental_trd');
    }
};

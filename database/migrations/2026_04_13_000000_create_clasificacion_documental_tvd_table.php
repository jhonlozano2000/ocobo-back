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
        Schema::create('clasificacion_documental_tvd', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50)->nullable(); // SerieDocumental, SubSerieDocumental
            $table->string('cod', 20)->nullable();
            $table->string('nom', 255)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('soporte', 100)->nullable(); // Papel, Electrónico, Mixto
            $table->integer('gestion')->nullable(); // Años en gestión
            $table->integer('central')->nullable(); // Años en archivo central
            $table->integer('total_anios')->nullable(); // Total años retención
            $table->string('disposicion_final', 100)->nullable(); // Eliminación, Conservación, Selección
            $table->text('procedimiento')->nullable();
            $table->unsignedBigInteger('parent')->nullable();
            $table->unsignedBigInteger('dependencia_id')->nullable();
            $table->unsignedBigInteger('user_register')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->foreign('parent')->references('id')->on('clasificacion_documental_tvd')->onDelete('cascade');
            $table->foreign('dependencia_id')->references('id')->on('calidad_organigrama')->onDelete('set null');
            $table->foreign('user_register')->references('id')->on('users')->onDelete('set null');

            $table->index('tipo');
            $table->index('dependencia_id');
            $table->index('parent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clasificacion_documental_tvd');
    }
};
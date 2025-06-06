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

            $table->unsignedBigInteger('version_id')->nullable();
            $table->foreign('version_id')->references('id')->on('clasificacion_documental_trd_versiones');

            $table->unsignedBigInteger('user_register');
            $table->foreign('user_register')->references('id')->on('users');

            $table->unsignedBigInteger('dependencia_id')->nullable();
            $table->foreign('dependencia_id')->references('id')->on('calidad_organigrama');

            $table->integer('version')->default(1)->comment('Número de versión del TRD');
            $table->enum('estado_version', ['TEMP', 'ACTIVO', 'HISTORICO'])->default('TEMP')
                ->comment('Estado de la versión: TEMP = Temporal, ACTIVO = En uso, HISTORICO = Versiones antiguas');

            $table->enum('tipo', ['Serie', 'SubSerie', 'TipoDocumento'])->index();
            $table->string('cod', 10)->nullable();
            $table->string('nom', 100)->nullable();
            $table->string('a_g', 5)->nullable()->comment('Archivo de Gestión');
            $table->string('a_c', 5)->nullable()->comment('Archivo Central');
            $table->boolean('ct')->default(false)->comment('Conservación Total');
            $table->boolean('e')->default(false)->comment('Eliminación');
            $table->boolean('m_d')->default(false)->comment('Microfilmación / Digitalización');
            $table->boolean('s')->default(false)->comment('Selección');
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

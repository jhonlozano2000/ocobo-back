<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('config_varias', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique(); // Identificador de la configuración
            $table->string('valor', 255); // Valor de la configuración
            $table->string('descripcion', 255)->nullable(); // Explicación de la configuración
            $table->timestamps();
        });

        // Insertar configuraciones iniciales
        DB::table('config_varias')->insert([
            ['clave' => 'max_tamano_archivo', 'valor' => '20480', 'descripcion' => 'Tamaño máximo de archivos en KB (20MB)'],
            ['clave' => 'tipos_archivos_permitidos', 'valor' => 'pdf,jpg,png,docx', 'descripcion' => 'Extensiones permitidas para carga de archivos'],
        ]);

        DB::table('config_varias')->insert([
            ['clave' => 'formato_num_radicado_reci', 'valor' => 'YYYYMMDD-#####', 'descripcion' => 'Formato del número de radicado'],
            ['clave' => 'numeracion_unificada', 'valor' => 'true', 'descripcion' => 'Configuración de numeración unificada de radicados'],
            ['clave' => 'multi_sede', 'valor' => '0', 'descripcion' => 'Configuración de múltiples sedes (0: deshabilitado, 1: habilitado)'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_varias');
    }
};

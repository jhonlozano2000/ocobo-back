<?php

use App\Services\OfiArchivo\ConfiguracionArchivo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B20 (diseño de expedientes §5): ubicación topográfica de los expedientes físicos e híbridos.
 *
 * - `ofi_archivo_ubicaciones`: árbol depósito → estante → entrepaño → caja. El depósito indica a
 *   qué archivo pertenece (gestión, central o histórico) y sus hijos lo heredan. La carpeta es el
 *   número dentro de la caja que se registra en el expediente o el tomo.
 * - `ubicacion_id` en expedientes y tomos apunta a la caja; los textos `ubicacion_fisica` y `caja`
 *   se derivan de ella para que el FUID, la hoja de control y el cierre sigan leyéndolos.
 * - `archivo_ubicacion_con_catalogo`: con el interruptor apagado (por defecto) la ubicación sigue
 *   siendo texto libre; encendido, solo se aceptan cajas del catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ofi_archivo_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('ofi_archivo_ubicaciones')->restrictOnDelete();
            $table->enum('tipo', ['deposito', 'estante', 'entrepano', 'caja']);
            $table->enum('archivo', ['gestion', 'central', 'historico']);
            $table->string('codigo', 30);
            $table->string('nombre', 150)->nullable();
            $table->unsignedInteger('capacidad')->nullable()->comment('Carpetas que caben en la caja; null = sin límite');
            $table->boolean('activo')->default(true);
            $table->string('ruta', 200)->comment('Códigos desde el depósito, derivados');
            $table->timestamps();

            $table->unique(['parent_id', 'codigo'], 'uk_ubicacion_padre_codigo');
            $table->index(['tipo', 'archivo', 'activo'], 'idx_ubicacion_tipo_archivo');
        });

        foreach (['ofi_archivo_expedientes', 'ofi_archivo_expediente_tomos'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('ubicacion_id')->nullable()->after('ubicacion_fisica')
                    ->constrained('ofi_archivo_ubicaciones')->restrictOnDelete();
            });
        }

        if (! DB::table('config_varias')->where('clave', ConfiguracionArchivo::UBICACION_CON_CATALOGO)->exists()) {
            DB::table('config_varias')->insert([
                'clave' => ConfiguracionArchivo::UBICACION_CON_CATALOGO,
                'valor' => 'false',
                'descripcion' => 'Exigir que la ubicación de los expedientes físicos e híbridos sea una caja del catálogo topográfico',
                'tipo' => 'boolean',
                'estado' => true,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('config_varias')->where('clave', ConfiguracionArchivo::UBICACION_CON_CATALOGO)->delete();

        foreach (['ofi_archivo_expediente_tomos', 'ofi_archivo_expedientes'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('ubicacion_id');
            });
        }

        Schema::dropIfExists('ofi_archivo_ubicaciones');
    }
};

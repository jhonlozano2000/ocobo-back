<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * M16-04: Habeas Data (Ley 1581/2012, Decreto 1377/2013) y dígito de verificación DIAN (M16-07).
     */
    public function up(): void
    {
        Schema::table('gestion_terceros', function (Blueprint $table) {
            if (! Schema::hasColumn('gestion_terceros', 'autoriza_tratamiento_datos')) {
                $table->boolean('autoriza_tratamiento_datos')->default(false)->after('notifica_msm');
            }
            if (! Schema::hasColumn('gestion_terceros', 'fecha_autorizacion_datos')) {
                $table->timestamp('fecha_autorizacion_datos')->nullable()->after('autoriza_tratamiento_datos');
            }
            if (! Schema::hasColumn('gestion_terceros', 'dv_nit')) {
                $table->unsignedTinyInteger('dv_nit')->nullable()->after('num_docu_nit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gestion_terceros', function (Blueprint $table) {
            $cols = array_filter(
                ['autoriza_tratamiento_datos', 'fecha_autorizacion_datos', 'dv_nit'],
                fn (string $c) => Schema::hasColumn('gestion_terceros', $c)
            );
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};

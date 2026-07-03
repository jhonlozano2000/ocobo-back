<?php

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_radica_internos', function (Blueprint $table) {
            $table->unsignedBigInteger('dependencia_origen_id')
                ->nullable()
                ->after('usuario_crea')
                ->comment('Dependencia de origen (inferida del usuario creador al radicar)');
            $table->foreign('dependencia_origen_id', 'vri_dependencia_origen_fk')
                ->references('id')->on('calidad_organigrama')
                ->onDelete('set null');
        });

        VentanillaRadicaInterno::whereNull('dependencia_origen_id')
            ->whereNotNull('usuario_crea')
            ->chunk(50, function ($radicados) {
                foreach ($radicados as $radicado) {
                    $dependencia = $radicado->dependencia_origen;
                    if ($dependencia && isset($dependencia['id'])) {
                        $radicado->dependencia_origen_id = $dependencia['id'];
                        $radicado->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_internos', function (Blueprint $table) {
            $table->dropForeign('vri_dependencia_origen_fk');
            $table->dropColumn('dependencia_origen_id');
        });
    }
};

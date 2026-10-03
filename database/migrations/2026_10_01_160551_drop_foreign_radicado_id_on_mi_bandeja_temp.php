<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * radicado_id en mi_bandeja_temp es polimórfico (puede ser de ventanilla_radica_reci,
     * ventanilla_radica_envia o ventanilla_radica_internos según radicado_tipo).
     * Por ello, no debe tener foreign key rígida a ventanilla_radica_reci.
     */
    public function up(): void
    {
        Schema::table('mi_bandeja_temp', function (Blueprint $table) {
            $table->dropForeign('mi_bandeja_temp_radicado_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mi_bandeja_temp', function (Blueprint $table) {
            $table->foreign('radicado_id')
                ->references('id')
                ->on('ventanilla_radica_reci')
                ->onDelete('cascade');
        });
    }
};

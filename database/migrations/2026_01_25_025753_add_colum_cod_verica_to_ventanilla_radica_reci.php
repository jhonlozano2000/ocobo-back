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
        Schema::table('ventanilla_radica_reci', function (Blueprint $table) {
            $table->string('cod_verifica')->after('archivo_digital')->comment('Codigo de verificacion del radicado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventanilla_radica_reci', function (Blueprint $table) {
            $table->dropColumn('cod_verifica');
        });
    }
};

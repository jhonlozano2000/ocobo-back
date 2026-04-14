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
        Schema::table('ventanilla_radica_reci_archivos', function (Blueprint $table) {
            if (!Schema::hasColumn('ventanilla_radica_reci_archivos', 'nom_origi')) {
                $table->string('nom_origi', 500)->nullable()->comment('Nombre original del archivo')->after('archivo');
            }
            if (!Schema::hasColumn('ventanilla_radica_reci_archivos', 'archivo_peso')) {
                $table->unsignedBigInteger('archivo_peso')->nullable()->comment('Peso del archivo en bytes')->after('nom_origi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventanilla_radica_reci_archivos', function (Blueprint $table) {
            $table->dropColumn(['nom_origi', 'archivo_peso']);
        });
    }
};

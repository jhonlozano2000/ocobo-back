<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_radica_reci', function (Blueprint $table) {
            if (!Schema::hasColumn('ventanilla_radica_reci', 'nom_origi')) {
                $table->string('nom_origi', 500)->nullable()->after('archivo_digital')->comment('Nombre original del archivo digital');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_reci', function (Blueprint $table) {
            $table->dropColumn(['nom_origi']);
        });
    }
};

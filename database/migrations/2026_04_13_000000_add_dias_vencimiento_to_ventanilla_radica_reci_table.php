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
            $table->integer('dias_vencimiento')->nullable()->comment('Días de vencimiento configurados en TRD')->after('archivo_digital');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventanilla_radica_reci', function (Blueprint $table) {
            $table->dropColumn('dias_vencimiento');
        });
    }
};

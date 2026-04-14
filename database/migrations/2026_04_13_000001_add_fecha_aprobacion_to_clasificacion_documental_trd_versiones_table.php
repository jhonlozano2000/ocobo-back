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
        Schema::table('clasificacion_documental_trd_versiones', function (Blueprint $table) {
            $table->timestamp('fecha_aprobacion')->nullable()->comment('Fecha de aprobación de la versión');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clasificacion_documental_trd_versiones', function (Blueprint $table) {
            $table->dropColumn('fecha_aprobacion');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make `motivo` nullable in ventanilla_radica_reci_historial_clasifica
     * so classification changes can be saved without a reason.
     */
    public function up(): void
    {
        Schema::table('ventanilla_radica_reci_historial_clasifica', function (Blueprint $table) {
            $table->text('motivo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_reci_historial_clasifica', function (Blueprint $table) {
            $table->text('motivo')->nullable(false)->change();
        });
    }
};

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
        Schema::table('ventanilla_email_radicados', function (Blueprint $table) {
            $table->dropForeign('ventanilla_email_radicados_radicado_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventanilla_email_radicados', function (Blueprint $table) {
            $table->foreign('radicado_id')->references('id')->on('ventanilla_radica_reci');
        });
    }
};

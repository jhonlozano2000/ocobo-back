<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_radica_internos', function (Blueprint $table) {
            $table->unsignedBigInteger('usua_soli_anula_id')->nullable()->comment('Usuario que solicita la anulación');
            $table->foreign('usua_soli_anula_id')->references('id')->on('users')->onDelete('set null');
            $table->text('observa_soli_anula')->nullable()->comment('Observaciones de la solicitud de anulación');
            $table->unsignedBigInteger('usua_aprue_anula_id')->nullable()->comment('Usuario que aprueba la anulación');
            $table->foreign('usua_aprue_anula_id')->references('id')->on('users')->onDelete('set null');
            $table->text('observa_aprue_anula')->nullable()->comment('Observaciones de la aprobación de anulación');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_internos', function (Blueprint $table) {
            $table->dropForeign(['usua_soli_anula_id']);
            $table->dropForeign(['usua_aprue_anula_id']);
            $table->dropColumn(['usua_soli_anula_id', 'observa_soli_anula', 'usua_aprue_anula_id', 'observa_aprue_anula']);
        });
    }
};
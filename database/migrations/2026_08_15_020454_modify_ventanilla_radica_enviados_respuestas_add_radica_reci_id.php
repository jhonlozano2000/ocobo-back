<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_radica_enviados_respuestas', function (Blueprint $table) {
            $table->dropForeign(['users_cargos_id']);
            $table->dropColumn('users_cargos_id');
            $table->foreignId('radica_reci_id')->constrained('ventanilla_radica_reci');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_enviados_respuestas', function (Blueprint $table) {
            $table->dropForeign(['radica_reci_id']);
            $table->dropColumn('radica_reci_id');
            $table->foreignId('users_cargos_id')->constrained('users_cargos');
        });
    }
};

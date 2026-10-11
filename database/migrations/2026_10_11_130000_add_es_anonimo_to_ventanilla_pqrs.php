<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva de identidad del peticionario (M04-03): con es_anonimo la identidad del
 * afectado solo la ve quien tenga el permiso 'Radicar -> PQRSF -> Ver identidad reservada'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_pqrs', function (Blueprint $table) {
            $table->boolean('es_anonimo')->default(false)->after('tipo_persona');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_pqrs', function (Blueprint $table) {
            $table->dropColumn('es_anonimo');
        });
    }
};

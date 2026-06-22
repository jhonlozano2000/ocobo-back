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
        Schema::table('ventanilla_radica_internos', function (Blueprint $table) {
            $table->enum('estado_firma', ['pendiente', 'firmado'])->default('pendiente')->after('hash_sha256')->comment('Estado de firma electronica');
            $table->timestamp('fecha_firma')->nullable()->after('estado_firma')->comment('Fecha y hora de la firma electronica');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_internos', function (Blueprint $table) {
            $table->dropColumn(['estado_firma', 'fecha_firma']);
        });
    }
};

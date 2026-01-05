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
            $table->unsignedBigInteger('user_register')->nullable()->before('aprobado_por');
            $table->foreign('user_register')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clasificacion_documental_trd_versiones', function (Blueprint $table) {
            $table->dropForeign(['user_register']);
            $table->dropColumn('user_register');
        });
    }
};

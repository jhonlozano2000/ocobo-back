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
        Schema::table('ventanilla_pqrs_comentarios', function (Blueprint $table) {
            $table->unsignedBigInteger('resuelto_por')->nullable()->after('es_nota_interna');
            $table->timestamp('fecha_resolucion')->nullable()->after('resuelto_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventanilla_pqrs_comentarios', function (Blueprint $table) {
            //
        });
    }
};

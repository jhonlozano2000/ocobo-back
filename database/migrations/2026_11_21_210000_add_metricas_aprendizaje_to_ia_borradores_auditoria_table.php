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
        Schema::table('ia_borradores_auditoria', function (Blueprint $table) {
            $table->longText('contenido_final')->nullable()->after('borrador_generado');
            $table->decimal('edit_ratio', 5, 4)->nullable()->after('contenido_final')->index();
            $table->string('estado_aprendizaje', 40)->default('pendiente')->after('edit_ratio')->index();
            $table->timestamp('firmado_at')->nullable()->after('estado_aprendizaje');
            $table->string('radicado_salida', 50)->nullable()->after('firmado_at')->index();
            $table->json('metadata_aprendizaje')->nullable()->after('radicado_salida');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ia_borradores_auditoria', function (Blueprint $table) {
            $table->dropColumn([
                'contenido_final',
                'edit_ratio',
                'estado_aprendizaje',
                'firmado_at',
                'radicado_salida',
                'metadata_aprendizaje',
            ]);
        });
    }
};

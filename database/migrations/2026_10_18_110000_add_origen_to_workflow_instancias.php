<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M19-30: una instancia puede nacer de un radicado recibido o de una PQRS. radicado_id es el
 * radicado recibido resuelto (el de la PQRS cuando el origen es una PQRS): a su expediente
 * abierto van los archivos del flujo al completarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_instancias', function (Blueprint $table) {
            $table->enum('origen_tipo', ['radicado_recibido', 'pqrs'])->nullable()->after('usuario_ejecuta_id');
            $table->unsignedBigInteger('origen_id')->nullable()->after('origen_tipo');
            $table->foreignId('radicado_id')->nullable()->after('origen_id')
                ->constrained('ventanilla_radica_reci')->nullOnDelete();
            $table->index(['origen_tipo', 'origen_id']);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_instancias', function (Blueprint $table) {
            $table->dropIndex(['origen_tipo', 'origen_id']);
            $table->dropConstrainedForeignId('radicado_id');
            $table->dropColumn(['origen_tipo', 'origen_id']);
        });
    }
};

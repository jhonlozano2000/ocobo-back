<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_nodos', function (Blueprint $table) {
            $table->foreignId('responsable_usuario_id')->nullable()
                ->after('configuracion_json')
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedSmallInteger('tiempo_limite_horas')->nullable()
                ->after('responsable_usuario_id');
            $table->boolean('adjuntos_permitidos')->default(false)
                ->after('tiempo_limite_horas');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_nodos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_usuario_id');
            $table->dropColumn(['tiempo_limite_horas', 'adjuntos_permitidos']);
        });
    }
};

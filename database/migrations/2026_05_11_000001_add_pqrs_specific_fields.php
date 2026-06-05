<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_pqrs', function (Blueprint $table) {
            $table->string('modalidad', 100)->nullable()->comment('Modalidad del derecho de petición (Art. 13 Ley 1755)');
            $table->string('derecho_solicitado', 255)->nullable()->comment('Derecho específico solicitado');
            $table->string('area_afectada', 255)->nullable()->comment('Área o dependencia afectada');
            $table->text('funcionarios_implicados')->nullable()->comment('Nombres de funcionarios implicados (quejas/reclamos)');
            $table->string('derecho_vulnerado', 255)->nullable()->comment('Derecho fundamental vulnerado');
            $table->text('pretension')->nullable()->comment('Pretensión del peticionario (Art. 13)');
            $table->text('area_mejora')->nullable()->comment('Área de mejora (sugerencias)');
            $table->text('motivo_felicitacion')->nullable()->comment('Motivo de felicitación');

            $table->string('autoridad_destino', 255)->nullable()->comment('Autoridad a la que se dirige (Art. 16 Ley 1755)');
            $table->string('tipo_persona', 20)->nullable()->default('Natural')->comment('Natural o Jurídica');

            $table->enum('estado_firma', ['pendiente', 'firmada'])->default('pendiente')->comment('Estado de firma digital');
            $table->longText('firma_digital')->nullable()->comment('Firma digital en base64');
            $table->datetime('fecha_firma')->nullable()->comment('Fecha y hora de la firma');
            $table->string('ip_firma', 45)->nullable()->comment('IP desde donde se firmó');
            $table->boolean('firmado_en_representacion')->default(false)->comment('¿Firma en representación?');
            $table->string('nombre_representado', 255)->nullable()->comment('Nombre de quien se representa');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_pqrs', function (Blueprint $table) {
            $table->dropColumn([
                'modalidad',
                'derecho_solicitado',
                'area_afectada',
                'funcionarios_implicados',
                'derecho_vulnerado',
                'pretension',
                'area_mejora',
                'motivo_felicitacion',
                'autoridad_destino',
                'tipo_persona',
                'estado_firma',
                'firma_digital',
                'fecha_firma',
                'ip_firma',
                'firmado_en_representacion',
                'nombre_representado',
            ]);
        });
    }
};

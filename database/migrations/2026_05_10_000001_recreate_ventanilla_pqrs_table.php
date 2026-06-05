<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ventanilla_pqrs');

        Schema::create('ventanilla_pqrs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('ventanilla_radica_reci_id')->unique()->comment('FK al radicado');
            $table->foreign('ventanilla_radica_reci_id')
                ->references('id')
                ->on('ventanilla_radica_reci')
                ->onDelete('cascade');

            $table->unsignedBigInteger('tipo_pqrs_id')->comment('Tipo PQRS (lista_id=7)');
            $table->foreign('tipo_pqrs_id')
                ->references('id')
                ->on('config_listas_detalles');

            $table->enum('prioridad', ['Normal', 'Urgente', 'Tutela'])->default('Normal');

            $table->enum('estado_tramite', ['Pendiente', 'En Tramite', 'Respondida', 'Vencida'])->default('Pendiente');

            $table->date('fecha_vencimiento')->comment('Fecha límite de respuesta');
            $table->date('fecha_vencimiento_original')->nullable()->comment('Fecha original antes de prórga');
            $table->boolean('tiene_prorroga')->default(false);

            $table->enum('fallo_judicial', ['Si', 'No'])->default('No')->comment('¿Hay fallo judicial?');

            $table->datetime('fechor_tramite')->nullable()->comment('Fecha y hora del trámite');

            $table->text('observaciones')->nullable();

            $table->string('num_docu_afectado', 25)->nullable()->comment('Doc del afectado (del tercero)');
            $table->string('nom_afectado', 100)->nullable()->comment('Nombre del afectado');
            $table->string('dir_afectado', 150)->nullable()->comment('Dirección del afectado');
            $table->char('tel_afectado', 50)->nullable()->comment('Teléfono del afectado');
            $table->string('movil_afectado', 30)->nullable()->comment('Móvil del afectado');

            $table->text('detalle_solicitud')->nullable()->comment('Detalles de la solicitud (copiado del asunto)');

            $table->index(['estado_tramite', 'fecha_vencimiento']);
            $table->index('tipo_pqrs_id');
            $table->index('prioridad');

            $table->index('ventanilla_radica_reci_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_pqrs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_interno_respuestas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('radica_interno_id')->comment('Radicado interno padre');
            $table->foreign('radica_interno_id', 'vri_resp_radica_id_fk')
                ->references('id')->on('ventanilla_radica_internos')->onDelete('cascade');

            $table->unsignedBigInteger('radica_interno_respuesta_id')->comment('Radicado interno que responde (debe tener fec_venci)');
            $table->foreign('radica_interno_respuesta_id', 'vri_resp_respuesta_fk')
                ->references('id')->on('ventanilla_radica_internos')->onDelete('cascade');

            $table->timestamps();

            $table->unique(['radica_interno_id', 'radica_interno_respuesta_id'], 'interno_resp_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_interno_respuestas');
    }
};

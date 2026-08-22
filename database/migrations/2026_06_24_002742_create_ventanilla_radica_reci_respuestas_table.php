<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_reci_respuestas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radicado_id');
            $table->string('titulo')->nullable();
            $table->longText('contenido')->nullable();
            $table->json('contenido_json')->nullable();
            $table->unsignedBigInteger('user_crea_id');
            $table->unsignedBigInteger('user_actualiza_id')->nullable();
            $table->timestamps();

            $table->foreign('radicado_id', 'rr_rad_fk')->references('id')->on('ventanilla_radica_reci')->onDelete('cascade');
            $table->foreign('user_crea_id', 'rr_crea_fk')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_actualiza_id', 'rr_act_fk')->references('id')->on('users')->onDelete('set null');
            $table->index('radicado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_respuestas');
    }
};

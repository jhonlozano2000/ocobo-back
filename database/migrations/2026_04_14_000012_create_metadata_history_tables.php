<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_reci_metadata_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('metadata_id');
            $table->string('tipo_cambio', 50);
            $table->string('campo_modificado', 100)->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_nombre')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('fecha_cambio');
            $table->timestamps();

            $table->foreign('metadata_id')->references('id')->on('ventanilla_radica_reci_metadata')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');

            $table->index('metadata_id');
            $table->index('usuario_id');
            $table->index('fecha_cambio');
            $table->index('tipo_cambio');
        });

        Schema::create('ventanilla_radica_enviados_metadata_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('metadata_id');
            $table->string('tipo_cambio', 50);
            $table->string('campo_modificado', 100)->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_nombre')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('fecha_cambio');
            $table->timestamps();

            $table->foreign('metadata_id')->references('id')->on('ventanilla_radica_enviados_metadata')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');

            $table->index('metadata_id');
            $table->index('usuario_id');
            $table->index('fecha_cambio');
            $table->index('tipo_cambio');
        });

        Schema::create('ventanilla_radica_interno_metadata_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('metadata_id');
            $table->string('tipo_cambio', 50);
            $table->string('campo_modificado', 100)->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_nombre')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('fecha_cambio');
            $table->timestamps();

            $table->foreign('metadata_id')->references('id')->on('ventanilla_radica_interno_metadata')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');

            $table->index('metadata_id');
            $table->index('usuario_id');
            $table->index('fecha_cambio');
            $table->index('tipo_cambio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_interno_metadata_history');
        Schema::dropIfExists('ventanilla_radica_enviados_metadata_history');
        Schema::dropIfExists('ventanilla_radica_reci_metadata_history');
    }
};

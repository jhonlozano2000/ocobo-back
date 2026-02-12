<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radica_enviados_archivo_eliminados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radica_enviado_id');
            $table->foreign('radica_enviado_id', 'vrae_radica_enviado_id_fk')->references('id')->on('ventanilla_radica_enviados')->onDelete('cascade');

            $table->string('archivo', 255)->comment('Ruta del archivo eliminado');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->foreign('deleted_by', 'vrae_deleted_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->timestamp('deleted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_enviados_archivo_eliminados');
    }
};

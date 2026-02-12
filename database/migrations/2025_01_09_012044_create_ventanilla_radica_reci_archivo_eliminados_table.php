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
        Schema::create('ventanilla_radica_reci_archivos_eliminados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('radicado_id');
            $table->foreign('radicado_id')->references('id')->on('ventanilla_radica_reci')->onDelete('cascade');

            $table->string('archivo', 255)->comment('Nombre del archivo eliminado');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamp('deleted_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_reci_archivos_eliminados');
    }
};

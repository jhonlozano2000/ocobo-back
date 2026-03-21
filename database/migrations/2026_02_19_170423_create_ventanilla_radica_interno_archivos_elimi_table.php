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
        Schema::create('ventanilla_radica_internos_archi_elimi', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('radica_interno_id');
            $table->foreign('radica_interno_id')->references('id')->on('ventanilla_radica_internos')->onDelete('cascade');

            $table->string('archivo', 255)->comment('Ruta del archivo eliminado');

            $table->unsignedBigInteger('eliminado_por')->nullable();
            $table->foreign('eliminado_por')->references('id')->on('users')->onDelete('set null');

            $table->timestamp('deleted_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radica_internos_archi_elimi');
    }
};

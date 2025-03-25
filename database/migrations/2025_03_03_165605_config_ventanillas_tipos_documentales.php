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
        Schema::create('config_ventanillas_tipos_documentales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ventanilla_id');
            $table->unsignedBigInteger('tipo_documental_id');
            $table->timestamps();

            $table->foreign('ventanilla_id')->references('id')->on('config_ventanillas')->onDelete('cascade');
            $table->foreign('tipo_documental_id')->references('id')->on('clasificacion_documental_trd')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

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
        Schema::create("ventanilla_radica_internos_firmantes", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("radica_interno_id")->comment("ID del radicado interno");
            $table->unsignedBigInteger("users_id")->comment("ID del usuario que debe firmar");
            
            $table->dateTime("fecha_firma")->nullable()->comment("Fecha y hora en que se realizó la firma");
            $table->string("otp_utilizado", 10)->nullable()->comment("Código OTP con el que se firmó");
            $table->boolean("firmado")->default(0)->comment("Estado de la firma (0: Pendiente, 1: Firmado)");

            // Llaves foráneas
            $table->foreign("radica_interno_id", "vri_firmantes_radica_id_foreign")
                  ->references("id")->on("ventanilla_radica_internos")
                  ->onDelete("cascade");
            
            $table->foreign("users_id", "vri_firmantes_users_id_foreign")
                  ->references("id")->on("users");

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("ventanilla_radica_internos_firmantes");
    }
};

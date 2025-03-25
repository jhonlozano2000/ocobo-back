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
        Schema::create('config_ventanillas_permisos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ventanilla_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('ventanilla_id')->references('id')->on('config_ventanillas')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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

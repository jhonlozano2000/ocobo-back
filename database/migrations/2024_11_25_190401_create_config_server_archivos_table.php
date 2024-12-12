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
        Schema::create('config_server_archivos', function (Blueprint $table) {
            $table->id();
            $table->string('host', 15);
            $table->string('ruta', 40)->nullable();
            $table->string('password', 15);
            $table->string('user', 15);
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_server_archivos');
    }
};

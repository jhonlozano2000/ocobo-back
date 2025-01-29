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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('divi_poli_id')->nullable();
            $table->foreign('divi_poli_id')->references('id')->on('config_divi_poli');

            $table->string('num_docu', 20)->comment('Numero de documento del usuario');
            $table->string('nombres', 70);
            $table->string('apellidos', 70);
            $table->string('tel', 15)->nullable();
            $table->string('movil', 15)->nullable();
            $table->string('dir', 100)->nullable();
            $table->string('email')->unique();
            $table->string('firma', 100)->nullable();
            $table->string('avatar', 100)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('estado')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

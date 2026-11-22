<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firmas_eventos', fn (Blueprint $table) => $table->mediumText('imagen_firma')->nullable());
    }

    public function down(): void
    {
        Schema::table('firmas_eventos', fn (Blueprint $table) => $table->dropColumn('imagen_firma'));
    }
};

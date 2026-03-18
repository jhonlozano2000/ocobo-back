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
        Schema::table('clasificacion_documental_trd', function (Blueprint $table) {
            $table->boolean('papel')->default(false)->after('s')->comment('Soporte papel');
            $table->boolean('electronico')->default(false)->after('papel')->comment('Soporte electrónico');
            $table->boolean('mixto')->default(false)->after('electronico')->comment('Soporte mixto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clasificacion_documental_trd', function (Blueprint $table) {
            $table->dropColumn(['papel', 'electronico', 'mixto']);
        });
    }
};

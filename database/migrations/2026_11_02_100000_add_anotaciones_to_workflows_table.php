<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anotaciones del lienzo del builder (notas adhesivas, textos, formas y resaltados). Son solo
 * visuales: no forman parte del grafo ni las lee el motor de instancias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->json('anotaciones')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', fn (Blueprint $table) => $table->dropColumn('anotaciones'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * M16-03: Blind Index (HMAC SHA-256) para búsquedas exactas e integridad de identificación de terceros.
     */
    public function up(): void
    {
        Schema::table('gestion_terceros', function (Blueprint $table) {
            if (! Schema::hasColumn('gestion_terceros', 'num_docu_nit_hash')) {
                $table->string('num_docu_nit_hash', 64)->nullable()->after('num_docu_nit')->index();
            }
        });

        // Poblar hashes existentes con HMAC SHA-256 usando APP_KEY
        $appKey = (string) config('app.key');
        if (! empty($appKey)) {
            $terceros = DB::table('gestion_terceros')->whereNotNull('num_docu_nit')->select('id', 'num_docu_nit')->get();
            foreach ($terceros as $t) {
                $hash = hash_hmac('sha256', (string) $t->num_docu_nit, $appKey);
                DB::table('gestion_terceros')->where('id', $t->id)->update(['num_docu_nit_hash' => $hash]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gestion_terceros', function (Blueprint $table) {
            if (Schema::hasColumn('gestion_terceros', 'num_docu_nit_hash')) {
                $table->dropColumn('num_docu_nit_hash');
            }
        });
    }
};

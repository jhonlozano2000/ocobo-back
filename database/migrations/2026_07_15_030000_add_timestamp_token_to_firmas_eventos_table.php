<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firmas_eventos', function (Blueprint $table) {
            $table->text('timestamp_token')->nullable()->after('hash_firmado')
                ->comment('Token TSR (Timestamp Response) codificado en base64 - RFC 3161');
            $table->dateTime('timestamp_fecha')->nullable()->after('timestamp_token')
                ->comment('Fecha del timestamp TSA (genTime del Token)');
        });
    }

    public function down(): void
    {
        Schema::table('firmas_eventos', function (Blueprint $table) {
            $table->dropColumn(['timestamp_token', 'timestamp_fecha']);
        });
    }
};

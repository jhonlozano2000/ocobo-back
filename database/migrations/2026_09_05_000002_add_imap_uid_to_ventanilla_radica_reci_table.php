<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventanilla_radica_reci', function (Blueprint $table) {
            $table->string('imap_uid', 50)->nullable()->after('num_radicado')
                ->comment('UID del correo IMAP asociado al radicado');
        });
    }

    public function down(): void
    {
        Schema::table('ventanilla_radica_reci', function (Blueprint $table) {
            $table->dropColumn('imap_uid');
        });
    }
};

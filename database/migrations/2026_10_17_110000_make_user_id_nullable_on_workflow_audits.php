<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M19-13: el barrido programado de vencimientos audita sin usuario autenticado (acción del sistema);
 * con user_id NOT NULL el comando fallaba en el primer registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_audits', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_audits', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};

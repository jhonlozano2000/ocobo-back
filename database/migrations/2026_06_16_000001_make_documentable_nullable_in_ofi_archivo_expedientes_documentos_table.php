<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->string('documentable_type', 255)->nullable()->change();
            $table->unsignedBigInteger('documentable_id')->nullable()->change();
        });

        // Fix existing invalid records
        DB::table('ofi_archivo_expedientes_documentos')
            ->where('documentable_type', 'tipo_documental')
            ->update([
                'documentable_type' => null,
                'documentable_id' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('ofi_archivo_expedientes_documentos', function (Blueprint $table) {
            $table->string('documentable_type', 255)->nullable(false)->change();
            $table->unsignedBigInteger('documentable_id')->nullable(false)->change();
        });
    }
};

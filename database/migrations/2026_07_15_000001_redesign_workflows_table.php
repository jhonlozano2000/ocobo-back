<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->unsignedSmallInteger('tiempo_finalizacion_horas')->nullable()->after('descripcion');
            $table->unsignedBigInteger('creador_user_id')->nullable()->after('tiempo_finalizacion_horas');
            $table->unsignedBigInteger('administrador_user_id')->nullable()->after('creador_user_id');

            $table->foreign('creador_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('administrador_user_id')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('UPDATE workflows SET uuid = UUID() WHERE uuid IS NULL');

        DB::statement('UPDATE workflows SET creador_user_id = usuario_crea_id WHERE creador_user_id IS NULL AND usuario_crea_id IS NOT NULL');

        DB::statement('ALTER TABLE workflows MODIFY COLUMN uuid CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE workflows ADD UNIQUE INDEX workflows_uuid_unique (uuid)');

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['usuario_crea_id']);
            $table->dropColumn('usuario_crea_id');

            $table->dropColumn(['fecha_limite_dias', 'fecha_limite_tipo', 'configuracion_general']);
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_crea_id')->nullable();
            $table->unsignedSmallInteger('fecha_limite_dias')->nullable();
            $table->enum('fecha_limite_tipo', ['naturales', 'habiles'])->default('habiles');
            $table->json('configuracion_general')->nullable();
        });

        DB::statement('UPDATE workflows SET usuario_crea_id = creador_user_id WHERE usuario_crea_id IS NULL AND creador_user_id IS NOT NULL');

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('usuario_crea_id')->references('id')->on('users');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['creador_user_id']);
            $table->dropForeign(['administrador_user_id']);
        });

        DB::statement('ALTER TABLE workflows DROP INDEX workflows_uuid_unique');
        DB::statement('ALTER TABLE workflows MODIFY COLUMN uuid CHAR(36) NULL');

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'tiempo_finalizacion_horas', 'creador_user_id', 'administrador_user_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventanilla_radicado_consecutivos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->unsignedSmallInteger('periodo');
            $table->unsignedBigInteger('consecutivo')->default(0);
            $table->timestamps();

            $table->unique(['tipo', 'periodo']);
        });

        $maximosPorPeriodo = [];
        foreach (DB::table('ventanilla_radica_reci')->select(['num_radicado', 'created_at'])->cursor() as $radicado) {
            if (! preg_match('/(\d+)$/', (string) $radicado->num_radicado, $matches)) {
                continue;
            }

            $periodo = (int) substr((string) $radicado->created_at, 0, 4);
            $maximosPorPeriodo[$periodo] = max($maximosPorPeriodo[$periodo] ?? 0, (int) $matches[1]);
        }

        foreach ($maximosPorPeriodo as $periodo => $consecutivo) {
            DB::table('ventanilla_radicado_consecutivos')->insert([
                'tipo' => 'recibido',
                'periodo' => $periodo,
                'consecutivo' => $consecutivo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanilla_radicado_consecutivos');
    }
};

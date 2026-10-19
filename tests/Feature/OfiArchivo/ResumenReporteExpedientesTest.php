<?php

namespace Tests\Feature\OfiArchivo;

use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\User;
use App\Services\OfiArchivo\ReportesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * B05 (diseño de expedientes): el resumen del reporte comparaba 'Abierto'/'Cerrado' en PHP
 * contra el enum en minúscula, así que todos los conteos daban 0.
 */
class ResumenReporteExpedientesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_el_resumen_cuenta_por_estado(): void
    {
        $user = User::factory()->create(['estado' => 1]);

        Schema::disableForeignKeyConstraints();
        $depId = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep R', 'cod_organico' => 'B05D']);
        $serieId = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $user->id, 'tipo' => 'Serie', 'cod' => 'B05']);
        Schema::enableForeignKeyConstraints();

        // B16: el transferido al central sigue cerrado; los transferidos son los que salieron de gestión
        $casos = [['abierto', 'gestion'], ['abierto', 'gestion'], ['cerrado', 'gestion'], ['cerrado', 'central'], ['transferido', 'historico']];
        foreach ($casos as $i => [$estado, $fase]) {
            OfiArchivoExpediente::create([
                'numero_expediente' => '2026-B05D-B05-000'.$i,
                'nombre_expediente' => 'Expediente '.$i,
                'anio' => 2026,
                'dependencia_id' => $depId,
                'serie_trd_id' => $serieId,
                'estado' => $estado,
                'fase' => $fase,
                'fecha_apertura' => '2026-10-18',
                'usuario_apertura_id' => $user->id,
            ]);
        }

        $reporte = app(ReportesService::class)->expedientes(['dependencia_id' => $depId]);

        $this->assertSame(['abiertos' => 2, 'cerrados' => 1, 'transferidos' => 2], $reporte['resumen']);
    }
}

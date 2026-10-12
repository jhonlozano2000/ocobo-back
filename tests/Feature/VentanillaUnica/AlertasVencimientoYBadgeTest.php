<?php

namespace Tests\Feature\VentanillaUnica;

use App\Http\Resources\VentanillaUnica\PqrsResource;
use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlertasVencimientoYBadgeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_comando_alertas_vencimiento_dry_run_ejecuta_correctamente(): void
    {
        $this->artisan('alertas:vencimiento', ['--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_pqrs_resource_respeta_semaforo_de_config_varias(): void
    {
        ConfigVarias::setSemaforoActivo(true);
        ConfigVarias::setSemaforoAmarilloDias(4);
        ConfigVarias::setSemaforoVerdeDias(2);

        $radicado = new VentanillaRadicaReci([
            'id' => 99999,
            'num_radicado' => 'RECI-2026-TEST-99',
            'fec_radi' => now(),
            'asunto' => 'Prueba semaforo badge',
            'estado_trabajo' => 'Pendiente',
            'cod_organico' => '100',
        ]);

        $pqrs = new VentanillaPqrs([
            'ventanilla_radica_reci_id' => $radicado->id,
            'estado_tramite' => 'En Trámite',
            'prioridad' => 'Normal',
            'fecha_vencimiento' => Carbon::now()->addDays(2),
        ]);
        $pqrs->id = 99999;
        $pqrs->setRelation('radicado', $radicado);

        $resource = new PqrsResource($pqrs);
        $data = $resource->toArray(Request::create('/api/ventanilla-pqrs'));

        $this->assertArrayHasKey('vuexy_badges', $data);
        $this->assertArrayHasKey('vencimiento', $data['vuexy_badges']);
        $this->assertContains($data['vuexy_badges']['vencimiento']['color'], ['danger', 'warning', 'info']);
    }
}

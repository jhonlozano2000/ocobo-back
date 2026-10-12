<?php

namespace Tests\Feature\GestionTerceros;

use App\Models\Gestion\GestionTercero;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M16-04 / M16-07: Habeas Data (Ley 1581) y Dígito de Verificación DIAN (Módulo 11).
 */
class HabeasDataYDvNitTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Gestión - Terceros -> Crear', 'Gestión - Terceros -> Editar', 'Gestión - Terceros -> Mostrar'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->admin = User::factory()->create(['estado' => 1]);
        $this->admin->givePermissionTo(['Gestión - Terceros -> Crear', 'Gestión - Terceros -> Editar', 'Gestión - Terceros -> Mostrar']);
    }

    public function test_autorizacion_tratamiento_datos_registra_fecha(): void
    {
        $payload = [
            'tipo' => 'Natural',
            'num_docu_nit' => '1098'.uniqid(),
            'nom_razo_soci' => 'Ciudadano Con Consentimiento',
            'autoriza_tratamiento_datos' => true,
        ];

        $res = $this->actingAs($this->admin)->postJson('/api/gestion/terceros', $payload);
        $res->assertStatus(201);

        $id = $res->json('data.id');
        $tercero = GestionTercero::findOrFail($id);

        $this->assertTrue($tercero->autoriza_tratamiento_datos);
        $this->assertNotNull($tercero->fecha_autorizacion_datos);
    }

    public function test_sin_autorizacion_no_registra_fecha(): void
    {
        $payload = [
            'tipo' => 'Natural',
            'num_docu_nit' => '1099'.uniqid(),
            'nom_razo_soci' => 'Ciudadano Sin Consentimiento',
            'autoriza_tratamiento_datos' => false,
        ];

        $res = $this->actingAs($this->admin)->postJson('/api/gestion/terceros', $payload);
        $res->assertStatus(201);

        $id = $res->json('data.id');
        $tercero = GestionTercero::findOrFail($id);

        $this->assertFalse($tercero->autoriza_tratamiento_datos);
        $this->assertNull($tercero->fecha_autorizacion_datos);
    }

    public function test_calculo_automatico_dv_nit_dian(): void
    {
        // NIT DIAN conocido: 800197268 -> DV 4
        $nit = '800197268';
        $this->assertSame(4, GestionTercero::calcularDvNit($nit));

        $payload = [
            'tipo' => 'Juridico',
            'num_docu_nit' => $nit,
            'nom_razo_soci' => 'Empresa de Prueba S.A.S.',
            'autoriza_tratamiento_datos' => true,
        ];

        $res = $this->actingAs($this->admin)->postJson('/api/gestion/terceros', $payload);
        $res->assertStatus(201);

        $id = $res->json('data.id');
        $tercero = GestionTercero::findOrFail($id);

        $this->assertSame(4, $tercero->dv_nit);
    }
}

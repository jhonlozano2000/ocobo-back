<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\ControlAcceso\UserCargo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M03-08/21: StoreRadicadoInternoRequest valida participantes con cargo activo y evita N+1.
 */
class StoreRadicadoInternoTest extends TestCase
{
    use DatabaseTransactions;

    private const PERM = 'Radicar -> Cores. Interna -> Crear';

    protected User $creador;

    protected User $destinatario;

    protected User $responsable;

    protected ClasificacionDocumentalTRD $trd;

    protected CalidadOrganigrama $cargoDestinatario;

    protected CalidadOrganigrama $cargoResponsable;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate(self::PERM, 'web');
        $this->creador = User::factory()->create(['estado' => 1]);
        $this->creador->givePermissionTo(self::PERM);

        $this->destinatario = User::factory()->create(['estado' => 1]);
        $this->responsable = User::factory()->create(['estado' => 1]);

        $this->trd = ClasificacionDocumentalTRD::first() ?? ClasificacionDocumentalTRD::create([
            'cod' => '100-TEST',
            'nom' => 'Serie de Prueba',
            'tipo' => 'Serie',
            'estado' => 1,
            'user_register' => $this->creador->id,
        ]);

        $dep = CalidadOrganigrama::firstOrCreate(
            ['cod_organico' => 'D1'],
            ['nom_organico' => 'Dependencia Test', 'tipo' => 'Dependencia', 'estado' => 1]
        );

        $this->cargoDestinatario = CalidadOrganigrama::firstOrCreate(
            ['cod_organico' => 'C1'],
            ['nom_organico' => 'Cargo Destinatario', 'tipo' => 'Cargo', 'parent' => $dep->id, 'estado' => 1]
        );

        $this->cargoResponsable = CalidadOrganigrama::firstOrCreate(
            ['cod_organico' => 'C2'],
            ['nom_organico' => 'Cargo Responsable', 'tipo' => 'Cargo', 'parent' => $dep->id, 'estado' => 1]
        );
    }

    public function test_participante_sin_cargo_activo_falla_con_422(): void
    {
        // El destinatario no tiene cargo activo
        $payload = [
            'asunto' => 'Memorando interno de prueba',
            'clasifica_documen_id' => $this->trd->id,
            'num_folios' => 2,
            'destinatarios' => [$this->destinatario->id],
            'responsables' => [
                ['user_id' => $this->responsable->id, 'custodio' => true],
            ],
        ];

        $this->actingAs($this->creador)
            ->postJson('/api/ventanilla/radica-interno', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['responsables']);
    }

    public function test_responsables_sin_custodio_falla_con_422(): void
    {
        // Asignar cargo activo a ambos
        UserCargo::create([
            'user_id' => $this->destinatario->id,
            'cargo_id' => $this->cargoDestinatario->id,
            'fecha_inicio' => now()->subDay(),
            'estado' => true,
        ]);

        UserCargo::create([
            'user_id' => $this->responsable->id,
            'cargo_id' => $this->cargoResponsable->id,
            'fecha_inicio' => now()->subDay(),
            'estado' => true,
        ]);

        // Responsable sin custodio
        $payload = [
            'asunto' => 'Memorando interno de prueba',
            'clasifica_documen_id' => $this->trd->id,
            'num_folios' => 2,
            'destinatarios' => [$this->destinatario->id],
            'responsables' => [
                ['user_id' => $this->responsable->id, 'custodio' => false],
            ],
        ];

        $this->actingAs($this->creador)
            ->postJson('/api/ventanilla/radica-interno', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['responsables']);
    }

    public function test_radicacion_interna_exitosa_con_cargos_activos(): void
    {
        UserCargo::create([
            'user_id' => $this->destinatario->id,
            'cargo_id' => $this->cargoDestinatario->id,
            'fecha_inicio' => now()->subDay(),
            'estado' => true,
        ]);

        UserCargo::create([
            'user_id' => $this->responsable->id,
            'cargo_id' => $this->cargoResponsable->id,
            'fecha_inicio' => now()->subDay(),
            'estado' => true,
        ]);

        $payload = [
            'asunto' => 'Memorando interno válido',
            'clasifica_documen_id' => $this->trd->id,
            'num_folios' => 3,
            'destinatarios' => [$this->destinatario->id],
            'responsables' => [
                ['user_id' => $this->responsable->id, 'custodio' => true],
            ],
        ];

        $res = $this->actingAs($this->creador)
            ->postJson('/api/ventanilla/radica-interno', $payload)
            ->assertStatus(201)
            ->assertJsonPath('status', true);

        $id = $res->json('data.id');
        $this->assertDatabaseHas('ventanilla_radica_internos_destina', [
            'radica_interno_id' => $id,
        ]);
        $this->assertDatabaseHas('ventanilla_radica_internos_responsa', [
            'radica_interno_id' => $id,
            'custodio' => 1,
        ]);
    }
}

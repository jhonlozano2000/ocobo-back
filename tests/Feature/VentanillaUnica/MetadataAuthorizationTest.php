<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\Gestion\GestionTercero;
use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Las rutas /metadata/* son compartidas y el módulo se elige con el parámetro
 * {tipo}. Antes la URI registrada ganaba con el permiso fijo de Recibida, de modo
 * que un usuario solo con Recibida podía leer/escribir la metadata de Enviados, y
 * un usuario solo con Enviada quedaba bloqueado (403) en su propio módulo.
 */
class MetadataAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $soloRecibida;

    protected User $soloEnviada;

    protected int $metadataEnviadosId;

    protected int $archivoEnviadosId;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisosReci = [
            'Radicar -> Cores. Recibida -> Mostrar',
            'Radicar -> Cores. Recibida -> Editar',
            'Radicar -> Cores. Recibida -> Exportar',
        ];
        $permisosEnvi = [
            'Radicar -> Cores. Enviada -> Mostrar',
            'Radicar -> Cores. Enviada -> Editar',
            'Radicar -> Cores. Enviada -> Exportar',
        ];

        foreach (array_merge($permisosReci, $permisosEnvi) as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rolReci = Role::firstOrCreate(['name' => 'Solo Recibida']);
        $rolReci->givePermissionTo($permisosReci);

        $rolEnvi = Role::firstOrCreate(['name' => 'Solo Enviada']);
        $rolEnvi->givePermissionTo($permisosEnvi);

        $this->soloRecibida = User::factory()->create();
        $this->soloRecibida->assignRole($rolReci);

        $this->soloEnviada = User::factory()->create();
        $this->soloEnviada->assignRole($rolEnvi);

        $tercero = GestionTercero::create([
            'num_docu_nit' => 'CC'.random_int(10000000, 99999999),
            'nom_razo_soci' => 'Tercero Metadata',
            'tipo' => 'Natural',
            'notifica_email' => false,
        ]);

        $clasificacion = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-MT-01',
            'nom' => 'Serie Metadata Test',
            'dias_vencimiento' => 15,
            'user_register' => $this->soloRecibida->id,
        ]);

        $lista = ConfigLista::create(['nombre' => 'Listas Metadata', 'cod' => 'L-MT-01']);
        $medio = ConfigListaDetalle::create([
            'lista_id' => $lista->id, 'nombre' => 'Correo', 'codigo' => 'MT-CORR', 'estado' => 1,
        ]);
        $tipoRespuesta = ConfigListaDetalle::create([
            'lista_id' => $lista->id, 'nombre' => 'Respuesta unica', 'codigo' => 'MT-RES', 'estado' => 1,
        ]);

        $enviado = VentanillaRadicaEnviados::create([
            'num_radicado' => 'ENV-MT-'.random_int(100000, 999999),
            'clasifica_documen_id' => $clasificacion->id,
            'tercero_id' => $tercero->id,
            'medio_enviado_id' => $medio->id,
            'tipo_respuesta_id' => $tipoRespuesta->id,
            'num_folios' => 1,
            'num_anexos' => 0,
            'asunto' => 'Radicado enviado para metadata',
            'usuario_crea' => $this->soloEnviada->id,
            'estado_trabajo' => 'ENVIADO',
        ]);

        // El "archivo_id" de la metadata de enviados apunta al radicado enviado.
        $this->archivoEnviadosId = $enviado->id;

        $metadata = VentanillaRadicaEnviadosMetadata::create([
            'archivo_id' => $enviado->id,
            'radicado_id' => $enviado->id,
            'descripcion' => 'descripcion original',
            'nivel_clasificacion' => 'INTERNO',
        ]);

        $this->metadataEnviadosId = $metadata->id;
    }

    public function test_usuario_sin_permiso_recibida_no_accede_a_metadata_propia(): void
    {
        // Ninguno de los dos roles tiene Internos: tipo=interno debe dar 403.
        $this->actingAs($this->soloRecibida)
            ->getJson('/api/ventanilla/metadata/archivos/1/interno')
            ->assertForbidden();
    }

    public function test_recibida_no_puede_leer_metadata_de_enviados(): void
    {
        $this->actingAs($this->soloRecibida)
            ->getJson("/api/ventanilla/metadata/archivos/{$this->archivoEnviadosId}/enviados")
            ->assertForbidden();
    }

    public function test_enviada_si_puede_leer_su_propia_metadata(): void
    {
        $this->actingAs($this->soloEnviada)
            ->getJson("/api/ventanilla/metadata/archivos/{$this->archivoEnviadosId}/enviados")
            ->assertOk()
            ->assertJsonPath('data.metadata.id', $this->metadataEnviadosId);
    }

    public function test_recibida_no_puede_editar_metadata_de_enviados(): void
    {
        $this->actingAs($this->soloRecibida)
            ->putJson("/api/ventanilla/metadata/archivos/{$this->archivoEnviadosId}/enviados", [
                'descripcion' => 'intrusa',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('ventanilla_radica_enviados_metadata', [
            'id' => $this->metadataEnviadosId,
            'descripcion' => 'descripcion original',
        ]);
    }

    public function test_historial_resuelve_por_archivo_no_por_id_de_metadata(): void
    {
        // La UI pasa el id del radicado/archivo; antes se filtraba metadata_id = archivoId
        // y el historial siempre venia vacio.
        $this->actingAs($this->soloEnviada)
            ->getJson("/api/ventanilla/metadata/historial/{$this->archivoEnviadosId}/enviados")
            ->assertOk()
            ->assertJsonStructure(['status', 'message', 'data']);
    }

    public function test_tipo_inventado_se_rechaza(): void
    {
        $this->actingAs($this->soloEnviada)
            ->getJson('/api/ventanilla/metadata/archivos/1/otracosa')
            ->assertStatus(422);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/ventanilla/metadata/archivos/1/reci')
            ->assertUnauthorized();
    }

    public function test_usuario_con_permiso_pqrs_accede_a_metadata_pqrs(): void
    {
        $permisos = [
            'Radicar -> PQRSF -> Mostrar',
            'Radicar -> PQRSF -> Editar',
        ];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'PQRS Metadata']);
        $rol->givePermissionTo($permisos);

        $user = User::factory()->create();
        $user->assignRole($rol);

        $tercero = GestionTercero::create([
            'num_docu_nit' => 'CC'.random_int(10000000, 99999999),
            'nom_razo_soci' => 'Tercero PQRS Meta',
            'tipo' => 'Natural',
            'notifica_email' => false,
        ]);

        $clasificacion = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-MT-P',
            'nom' => 'Serie PQRS Metadata',
            'dias_vencimiento' => 15,
            'user_register' => $user->id,
        ]);

        $lista = ConfigLista::create(['nombre' => 'Listas PQRS Meta', 'cod' => 'L-MT-P']);
        $medio = ConfigListaDetalle::create([
            'lista_id' => $lista->id, 'nombre' => 'Correo', 'codigo' => 'MT-CORR-P', 'estado' => 1,
        ]);
        $tipoRespuesta = ConfigListaDetalle::create([
            'lista_id' => $lista->id, 'nombre' => 'Respuesta', 'codigo' => 'MT-RES-P', 'estado' => 1,
        ]);

        $radicado = \App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci::create([
            'num_radicado' => 'REC-PMT-'.random_int(100000, 999999),
            'clasifica_documen_id' => $clasificacion->id,
            'tercero_id' => $tercero->id,
            'medio_recep_id' => $medio->id,
            'tipo_respuesta_id' => $tipoRespuesta->id,
            'num_folios' => 1,
            'num_anexos' => 0,
            'asunto' => 'Radicado para PQRS metadata',
            'usuario_crea' => $user->id,
            'estado_trabajo' => 'ACTIVO',
        ]);

        $tipoPqrs = ConfigListaDetalle::create([
            'lista_id' => $lista->id, 'nombre' => 'Petición', 'codigo' => 'TP-PQ', 'estado' => 1,
        ]);

        $pqrs = \App\Models\VentanillaUnica\Comunes\VentanillaPqrs::create([
            'ventanilla_radica_reci_id' => $radicado->id,
            'tipo_pqrs_id' => $tipoPqrs->id,
            'prioridad' => 'Normal',
            'estado_tramite' => 'Pendiente',
            'fecha_vencimiento' => now()->addDays(15),
            'detalle_solicitud' => 'PQRS para metadata test',
        ]);

        $metadata = \App\Models\VentanillaUnica\Pqrs\VentanillaPqrsMetadata::create([
            'archivo_id' => $radicado->id,
            'pqrs_id' => $pqrs->id,
            'radicado_id' => $radicado->id,
            'nivel_clasificacion' => 'PUBLICO',
            'descripcion' => 'Metadata PQRS test',
            'num_radicado' => $radicado->num_radicado,
            'asunto' => $radicado->asunto,
        ]);

        $this->actingAs($user)
            ->getJson("/api/ventanilla/metadata/archivos/{$radicado->id}/pqrs")
            ->assertOk()
            ->assertJsonPath('data.metadata.id', $metadata->id);
    }

    public function test_usuario_sin_permiso_pqrs_no_accede_a_metadata_pqrs(): void
    {
        $this->actingAs($this->soloRecibida)
            ->getJson('/api/ventanilla/metadata/archivos/1/pqrs')
            ->assertForbidden();
    }
}

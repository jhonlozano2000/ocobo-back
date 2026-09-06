<?php

namespace Tests\Feature\M15;

use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\Configuracion\ConfigLista;
use App\Models\Configuracion\ConfigListaDetalle;
use App\Models\Gestion\GestionTercero;
use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Services\VentanillaUnica\RadicacionEnviadosService;
use App\Services\VentanillaUnica\RadicacionReciService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard aggregates stats through rescue() fallbacks, so a broken query is
 * silently turned into zeros. These tests call the services directly so a SQL error
 * or a wrong column surfaces instead of being masked.
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected GestionTercero $tercero;

    protected ClasificacionDocumentalTRD $clasificacion;

    protected ConfigListaDetalle $medio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->tercero = GestionTercero::create([
            'num_docu_nit' => 'CC'.random_int(10000000, 99999999),
            'nom_razo_soci' => 'Tercero Dashboard',
            'tipo' => 'Natural',
            'notifica_email' => false,
        ]);

        $this->clasificacion = ClasificacionDocumentalTRD::create([
            'tipo' => 'Serie',
            'cod' => 'S-DSH-01',
            'nom' => 'Serie Dashboard Test',
            'dias_vencimiento' => 15,
            'user_register' => $this->user->id,
        ]);

        $lista = ConfigLista::create(['nombre' => 'Medios Dashboard', 'cod' => 'L-MED-DSH']);
        $this->medio = ConfigListaDetalle::create([
            'lista_id' => $lista->id,
            'nombre' => 'Correo electronico',
            'codigo' => 'CORR-DSH',
            'estado' => 1,
        ]);
    }

    private function crearRadicadoRecibido(string $estado): VentanillaRadicaReci
    {
        return VentanillaRadicaReci::create([
            'num_radicado' => 'REC-DSH-'.random_int(100000, 999999),
            'clasifica_documen_id' => $this->clasificacion->id,
            'tercero_id' => $this->tercero->id,
            'medio_recep_id' => $this->medio->id,
            'num_folios' => 1,
            'num_anexos' => 0,
            'asunto' => 'Radicado dashboard',
            'usuario_crea' => $this->user->id,
            'estado_trabajo' => $estado,
        ]);
    }

    public function test_get_stats_reci_cuenta_pendientes_usando_estado_trabajo(): void
    {
        $abiertos = [
            $this->crearRadicadoRecibido('RECIBIDO'),
            $this->crearRadicadoRecibido('EN_PROCESO'),
        ];
        $cerrados = [
            $this->crearRadicadoRecibido('FINALIZADO'),
            $this->crearRadicadoRecibido('ANULADO'),
        ];

        // The model recalculates estado_trabajo on created(), so closed states are
        // forced through the query builder, which bypasses model events.
        foreach ($cerrados as $i => $radicado) {
            VentanillaRadicaReci::where('id', $radicado->id)
                ->update(['estado_trabajo' => $i === 0 ? 'FINALIZADO' : 'ANULADO']);
        }
        foreach ($abiertos as $i => $radicado) {
            VentanillaRadicaReci::where('id', $radicado->id)
                ->update(['estado_trabajo' => $i === 0 ? 'RECIBIDO' : 'EN_PROCESO']);
        }

        $stats = app(RadicacionReciService::class)->getStats();

        $this->assertSame(4, $stats['total_radicados']);
        $this->assertSame(2, $stats['pendientes']);
    }

    public function test_get_stats_enviados_cuenta_pendientes_usando_estado_trabajo(): void
    {
        $listaTipos = ConfigLista::create(['nombre' => 'Tipos Respuesta Dashboard', 'cod' => 'L-TRES-DSH']);
        $tipoRespuesta = ConfigListaDetalle::create([
            'lista_id' => $listaTipos->id,
            'nombre' => 'Respuesta unica',
            'codigo' => 'RES-DSH',
            'estado' => 1,
        ]);

        $crear = function (string $estado) use ($tipoRespuesta) {
            VentanillaRadicaEnviados::create([
                'num_radicado' => 'ENV-DSH-'.random_int(100000, 999999),
                'clasifica_documen_id' => $this->clasificacion->id,
                'tercero_id' => $this->tercero->id,
                'medio_enviado_id' => $this->medio->id,
                'tipo_respuesta_id' => $tipoRespuesta->id,
                'num_folios' => 1,
                'num_anexos' => 0,
                'asunto' => 'Radicado enviado dashboard',
                'usuario_crea' => $this->user->id,
                'estado_trabajo' => $estado,
            ]);
        };

        $crear('ENVIADO');
        $crear('FINALIZADO');

        $stats = app(RadicacionEnviadosService::class)->getStats();

        $this->assertSame(2, $stats['total_enviados']);
        $this->assertSame(1, $stats['total_pendientes']);
    }
}

<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosHistorialClasificacionDocumental;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternosHistorialClasificacionDocumental;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReciHistorialClasificacionDocumental;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * M02-10: el cambio de clasificación documental y su historial no estaban en una transacción;
 * si el historial fallaba, el radicado quedaba reclasificado sin rastro del cambio.
 */
class CambioClasificacionAtomicoTest extends TestCase
{
    use DatabaseTransactions;

    public static function modulos(): array
    {
        return [
            'enviados' => ['ventanilla_radica_enviados', 'radica-enviada', VentanillaRadicaEnviadosHistorialClasificacionDocumental::class],
            'internos' => ['ventanilla_radica_internos', 'radica-interno', VentanillaRadicaInternosHistorialClasificacionDocumental::class],
            'recibidos' => ['ventanilla_radica_reci', 'radica-recibida', VentanillaRadicaReciHistorialClasificacionDocumental::class],
        ];
    }

    #[DataProvider('modulos')]
    public function test_si_falla_el_historial_no_cambia_la_clasificacion(string $tabla, string $ruta, string $historial): void
    {
        if ($ruta === 'radica-interno' && ! Schema::hasTable('ventanilla_radica_internos_responsa')) {
            $this->markTestSkipped('La BD de pruebas no tiene la tabla de responsables de internos');
        }

        $user = User::factory()->create(['estado' => 1]);
        $trd = fn (string $cod) => DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $user->id, 'tipo' => 'Serie', 'cod' => $cod]);
        $anterior = $trd('M0210A');
        $nueva = $trd('M0210B');

        Schema::disableForeignKeyConstraints();
        $obligatorios = [
            'ventanilla_radica_reci' => ['medio_recep_id' => 1],
            'ventanilla_radica_enviados' => ['tercero_id' => 1, 'medio_enviado_id' => 1, 'tipo_respuesta_id' => 1],
        ];
        $id = DB::table($tabla)->insertGetId([
            'num_radicado' => 'M0210-'.uniqid(), 'clasifica_documen_id' => $anterior, 'usuario_crea' => $user->id,
        ] + ($obligatorios[$tabla] ?? []));
        Schema::enableForeignKeyConstraints();

        $historial::creating(fn () => throw new \RuntimeException('Fallo simulado del historial'));

        $this->withoutMiddleware()->actingAs($user)
            ->putJson("/api/ventanilla/{$ruta}/{$id}/update-clasificacion-documental", ['clasifica_documen_id' => $nueva])
            ->assertStatus(500);

        $this->assertSame($anterior, (int) DB::table($tabla)->where('id', $id)->value('clasifica_documen_id'));
    }
}

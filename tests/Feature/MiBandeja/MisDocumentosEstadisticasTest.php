<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 19.29: las tarjetas de Mis Documentos salían siempre en 0. El listado viene paginado,
 * así que los conteos los da el back con el mismo alcance que el listado.
 */
class MisDocumentosEstadisticasTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cuenta_por_estado_los_documentos_propios_y_compartidos(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $yo = User::factory()->create(['estado' => 1]);
        $otro = User::factory()->create(['estado' => 1]);

        $documento = fn (User $dueno, string $estado) => DB::table('mi_bandeja_temp_reci_documentos')->insertGetId([
            'user_id' => $dueno->id, 'titulo' => 'Doc '.uniqid(), 'estado' => $estado, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $documento($yo, 'borrador');
        $documento($yo, 'borrador');
        $documento($yo, 'firmado');
        $compartido = $documento($otro, 'en_revision');
        DB::table('mi_bandeja_temp_reci_usuarios')->insert([
            'documento_id' => $compartido, 'user_id' => $yo->id, 'rol' => 'responsable', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Ajeno: no cuenta
        $documento($otro, 'borrador');

        $this->actingAs($yo)->getJson('/api/mi-bandeja/comunicaciones-recibidas/documentos/estadisticas')
            ->assertOk()
            ->assertJsonPath('data', ['total' => 4, 'borrador' => 2, 'en_revision' => 1, 'firmado' => 1]);
    }
}

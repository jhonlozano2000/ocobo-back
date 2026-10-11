<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Services\VentanillaUnica\EmailRadicacionService;
use App\Services\VentanillaUnica\RadicadoConsecutivoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M02-08: el consecutivo de Enviadas se leía sin bloqueo (colisión con el índice
 * único) y el error devolvía el SQL crudo al cliente.
 */
class ConsecutivoEnviadosTest extends TestCase
{
    use DatabaseTransactions;

    public function test_formulario_y_correo_comparten_contador_bloqueado(): void
    {
        if (! Schema::hasTable('ventanilla_radicado_consecutivos')) {
            $this->markTestSkipped('Falta la migración ventanilla_radicado_consecutivos en la BD de pruebas.');
        }

        DB::table('config_varias')->where('clave', 'formato_num_radicado_envi')->delete();
        DB::table('config_varias')->updateOrInsert(['clave' => 'formato_num_radicado_env'], ['valor' => 'ENV-YYYY-#####']);

        $primero = DB::transaction(fn () => app(RadicadoConsecutivoService::class)->reservarEnviado());
        $segundo = app(EmailRadicacionService::class)->obtenerSiguienteNumeroRadicadoEnviado();

        $this->assertMatchesRegularExpression('/^ENV-'.now()->format('Y').'-\d{5}$/', $primero);
        $this->assertSame((int) substr($primero, -5) + 1, (int) substr($segundo, -5));
    }

    public function test_error_al_crear_no_expone_sql(): void
    {
        $permiso = 'Radicar -> Cores. Enviada -> Crear';
        Permission::findOrCreate($permiso, 'web');
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo($permiso);

        Schema::disableForeignKeyConstraints();
        $trd = DB::table('clasificacion_documental_trd')->insertGetId(['user_register' => $user->id, 'tipo' => 'Serie']);
        $detalle = DB::table('config_listas_detalles')->insertGetId(['lista_id' => 999999, 'nombre' => 'M02-08']);
        $tercero = DB::table('gestion_terceros')->insertGetId(['tipo' => 'Natural', 'nom_razo_soci' => 'Tercero M02-08']);
        Schema::enableForeignKeyConstraints();

        $this->mock(RadicadoConsecutivoService::class)
            ->shouldReceive('reservarEnviado')
            ->andThrow(new QueryException('mysql', 'insert into `ventanilla_radica_enviados` (...)', [], new \Exception('SQLSTATE[23000]: Duplicate entry')));

        $respuesta = $this->actingAs($user)->postJson('/api/ventanilla/radica-enviada', [
            'clasifica_documen_id' => $trd,
            'tercero_id' => $tercero,
            'medio_enviado_id' => $detalle,
            'tipo_respuesta_id' => $detalle,
            'num_folios' => 1,
            'num_anexos' => 0,
            'descrip_anexos' => 'N/A',
            'asunto' => 'Prueba M02-08',
        ]);

        $respuesta->assertStatus(500);
        $this->assertStringNotContainsString('SQLSTATE', $respuesta->getContent());
        $this->assertStringNotContainsString('insert into', $respuesta->getContent());
    }
}

<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\User;
use App\Services\VentanillaUnica\RadicadoConsecutivoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M15-09: la variable COD_DEPEN del formato de radicado nunca se llenaba; ahora toma el código
 * de la dependencia del usuario que radica.
 */
class CodigoDependenciaRadicadoTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // La BD de pruebas puede no tener la migración del contador; TEMPORARY la suple
        if (! Schema::hasTable('ventanilla_radicado_consecutivos')) {
            DB::statement('CREATE TEMPORARY TABLE ventanilla_radicado_consecutivos (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tipo VARCHAR(30) NOT NULL, periodo SMALLINT UNSIGNED NOT NULL,
                consecutivo BIGINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
                UNIQUE KEY tipo_periodo (tipo, periodo))');
        }

        $this->user = User::factory()->create(['estado' => 1]);

        Schema::disableForeignKeyConstraints();
        $dependencia = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Dependencia', 'nom_organico' => 'Dep M15-09', 'cod_organico' => 'D159']);
        $oficina = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Oficina', 'nom_organico' => 'Of M15-09', 'cod_organico' => 'D159O', 'parent' => $dependencia]);
        $cargo = DB::table('calidad_organigrama')->insertGetId(['tipo' => 'Cargo', 'nom_organico' => 'Cargo M15-09', 'cod_organico' => 'D159C', 'parent' => $oficina]);
        DB::table('users_cargos')->insert(['user_id' => $this->user->id, 'cargo_id' => $cargo, 'fecha_inicio' => '2026-01-01', 'estado' => 1]);
        Schema::enableForeignKeyConstraints();

        DB::table('config_varias')->updateOrInsert(['clave' => 'formato_num_radicado_reci'], ['valor' => 'COD_DEPEN-YYYY-#####']);
    }

    public function test_cod_depen_toma_la_dependencia_del_usuario_que_radica(): void
    {
        $this->actingAs($this->user);

        $numero = DB::transaction(fn () => app(RadicadoConsecutivoService::class)->reservarRecibido());

        $this->assertMatchesRegularExpression('/^D159-'.now()->format('Y').'-\d{5}$/', $numero);
    }

    public function test_un_codigo_explicito_tiene_prioridad(): void
    {
        $this->actingAs($this->user);

        $numero = DB::transaction(fn () => app(RadicadoConsecutivoService::class)->reservarRecibido('X01'));

        $this->assertStringStartsWith('X01-', $numero);
    }
}

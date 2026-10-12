<?php

namespace Tests\Feature\VentanillaUnica;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sin dependencia_origen_id guardada, la dependencia de origen se infiere del cargo del
 * creador también cuando la relación dependenciaOrigen viene precargada (Mi bandeja).
 */
class RadicadoInternoDependenciaOrigenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dependencia_origen_se_infiere_del_creador_con_la_relacion_precargada(): void
    {
        $dependencia = CalidadOrganigrama::create(['tipo' => 'Dependencia', 'nom_organico' => 'Dep Origen Test', 'cod_organico' => 'DOT']);
        $oficina = CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'Ofi Origen Test', 'cod_organico' => 'DOTO', 'parent' => $dependencia->id]);
        $cargo = CalidadOrganigrama::create(['tipo' => 'Cargo', 'nom_organico' => 'Cargo Origen Test', 'cod_organico' => 'DOTC', 'parent' => $oficina->id]);

        $creador = User::factory()->create(['estado' => 1]);
        DB::table('users_cargos')->insert([
            'user_id' => $creador->id,
            'cargo_id' => $cargo->id,
            'fecha_inicio' => now()->toDateString(),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => 'DOT-TEST-0001',
            'clasifica_documen_id' => 999999,
            'asunto' => 'Prueba dependencia origen',
            'usuario_crea' => $creador->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $esperado = ['id' => $dependencia->id, 'nombre' => 'Dep Origen Test'];

        $this->assertSame($esperado, VentanillaRadicaInterno::find($id)->dependencia_origen);
        $this->assertSame($esperado, VentanillaRadicaInterno::with('dependenciaOrigen')->find($id)->dependencia_origen);
    }

    public function test_dependencia_origen_guardada_tiene_prioridad(): void
    {
        $guardada = CalidadOrganigrama::create(['tipo' => 'Dependencia', 'nom_organico' => 'Dep Guardada Test', 'cod_organico' => 'DGT']);
        $creador = User::factory()->create(['estado' => 1]);

        Schema::disableForeignKeyConstraints();
        $id = DB::table('ventanilla_radica_internos')->insertGetId([
            'num_radicado' => 'DOT-TEST-0002',
            'clasifica_documen_id' => 999999,
            'asunto' => 'Prueba dependencia guardada',
            'usuario_crea' => $creador->id,
            'dependencia_origen_id' => $guardada->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        $this->assertSame(
            ['id' => $guardada->id, 'nombre' => 'Dep Guardada Test'],
            VentanillaRadicaInterno::with('dependenciaOrigen')->find($id)->dependencia_origen
        );
    }
}

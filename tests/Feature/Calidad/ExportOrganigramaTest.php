<?php

namespace Tests\Feature\Calidad;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * El organigrama se exportaba en el navegador aplanando el árbol cargado; ahora lo arma el back con el
 * formato de los reportes y bajo el permiso Exportar.
 */
class ExportOrganigramaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_exporta_en_orden_jerarquico_con_nivel_y_padre(): void
    {
        $dep = CalidadOrganigrama::create(['tipo' => 'Dependencia', 'nom_organico' => 'ZZXORG Gerencia', 'cod_organico' => 'XORG1']);
        $ofi = CalidadOrganigrama::create(['tipo' => 'Oficina', 'nom_organico' => 'ZZXORG Archivo', 'cod_organico' => 'XORG2', 'parent' => $dep->id]);
        CalidadOrganigrama::create(['tipo' => 'Cargo', 'nom_organico' => 'ZZXORG Archivista', 'cod_organico' => 'XORG3', 'parent' => $ofi->id]);

        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('Calidad - Organigrama -> Exportar', 'web'));

        $contenido = $this->actingAs($user)->get('/api/calidad/organigrama/export?format=csv')->assertOk()->streamedContent();
        $filas = array_map('str_getcsv', array_values(array_filter(explode("\n", ltrim($contenido, "\xEF\xBB\xBF")))));

        $this->assertSame(['Código', 'Nombre', 'Tipo', 'Nivel', 'Depende de'], $filas[0]);
        $nuestras = array_values(array_filter($filas, fn ($f) => str_starts_with($f[0], 'XORG')));
        $this->assertSame([
            ['XORG1', 'ZZXORG Gerencia', 'Dependencia', '0', ''],
            ['XORG2', 'ZZXORG Archivo', 'Oficina', '1', 'ZZXORG Gerencia'],
            ['XORG3', 'ZZXORG Archivista', 'Cargo', '2', 'ZZXORG Archivo'],
        ], $nuestras);
    }

    public function test_requiere_permiso_de_exportar(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('Calidad - Organigrama -> Listar', 'web'));

        $this->actingAs($user)->getJson('/api/calidad/organigrama/export?format=csv')->assertForbidden();
    }
}

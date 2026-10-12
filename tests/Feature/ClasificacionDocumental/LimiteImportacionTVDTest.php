<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M10-10/M11-04: la importación de TVD aceptaba 10 MB (5 veces el tope de la TRD).
 */
class LimiteImportacionTVDTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rechaza_archivos_de_mas_de_2_mb(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        foreach (['TVD -> Importar', 'TVD -> Listar'] as $permiso) {
            $user->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        }

        foreach (['/api/clasifica-documental/tvd/import', '/api/clasifica-documental/tvd/validate'] as $ruta) {
            $this->actingAs($user)->post($ruta, ['archivo' => UploadedFile::fake()->create('tvd.xlsx', 3 * 1024)], ['Accept' => 'application/json'])
                ->assertStatus(422)
                ->assertJsonValidationErrors('archivo');
        }
    }
}

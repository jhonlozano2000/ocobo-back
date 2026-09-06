<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTVD;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * M05 TVD — valida el endpoint /tvd/validate y el /tvd/import con un xlsx real,
 * porque hasta ahora ninguna prueba tocaba ninguna de las dos rutas.
 */
class TVDImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CalidadOrganigrama $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = ['TVD -> Listar', 'TVD -> Crear', 'TVD -> Importar'];

        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $rol = Role::firstOrCreate(['name' => 'Importador TVD']);
        $rol->givePermissionTo($permisos);

        $this->user = User::factory()->create();
        $this->user->assignRole($rol);

        $this->dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia',
            'nom_organico' => 'Dependencia Import TVD',
            'cod_organico' => 'IMP'.random_int(100000, 999999),
        ]);
    }

    /**
     * Construye un xlsx con la forma que espera el importador:
     * B4 = codigo de dependencia; datos desde la fila 7 en
     * A=dependencia, B=serie, C=subserie, E=nombre, H=gestion, I=central.
     *
     * @param  array<int, array<string, mixed>>  $filas  columnas por letra
     */
    private function crearXlsx(array $filas, ?string $codigoDependencia = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A4', 'Dependencia');
        $sheet->setCellValue('B4', $codigoDependencia ?? $this->dependencia->cod_organico);

        $fila = 7;

        foreach ($filas as $datos) {
            foreach ($datos as $coluna => $valor) {
                $sheet->setCellValue($coluna.$fila, $valor);
            }
            $fila++;
        }

        $path = tempnam(sys_get_temp_dir(), 'tvd').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, 'tvd.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_validar_detecta_dependencia_inexistente(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd/validate', [
                'archivo' => $this->crearXlsx(
                    [['A' => 'DEP', 'B' => 'S-01', 'E' => 'Serie uno']],
                    'ZZZ99999'
                ),
            ]);

        $response->assertOk();
        $errores = $response->json('data.errores');
        $this->assertNotEmpty($errores);
        $this->assertStringContainsString('ZZZ99999', implode(' | ', (array) $errores));
    }

    public function test_validar_cuenta_series_y_subseries(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd/validate', [
                'archivo' => $this->crearXlsx([
                    ['A' => 'DEP', 'B' => 'S-01', 'E' => 'Serie documental uno'],
                    ['A' => 'DEP', 'B' => 'S-01', 'C' => 'SS-01', 'E' => 'Subserie uno'],
                    ['A' => 'DEP', 'B' => 'S-01', 'C' => 'SS-02', 'E' => 'Subserie dos'],
                ]),
            ]);

        $response->assertOk();
        $this->assertSame(3, $response->json('data.filas_validas'));
        $this->assertSame(1, $response->json('data.series'));
        $this->assertSame(2, $response->json('data.subseries'));
        $this->assertEmpty($response->json('data.errores'));
    }

    public function test_validar_marca_subserie_sin_serie_padre(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd/validate', [
                'archivo' => $this->crearXlsx([
                    ['A' => 'DEP', 'B' => 'S-01', 'C' => 'SS-01', 'E' => 'Hija de una serie que no aparece'],
                ]),
            ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('data.filas_validas'));
        $this->assertStringContainsString(
            'sin Serie padre',
            implode(' | ', (array) $response->json('data.errores'))
        );
    }

    public function test_import_crea_serie_y_subserie_con_padre(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd/import', [
                'archivo' => $this->crearXlsx([
                    ['A' => 'DEP', 'B' => 'S-IMP', 'E' => 'Serie importada'],
                    ['A' => 'DEP', 'B' => 'S-IMP', 'C' => 'SS-IMP', 'E' => 'Subserie importada'],
                ]),
            ]);

        $response->assertOk();
        $this->assertSame(2, $response->json('data.inserted'));

        $serie = ClasificacionDocumentalTVD::where('cod', 'S-IMP')->first();
        $subserie = ClasificacionDocumentalTVD::where('cod', 'SS-IMP')->first();

        $this->assertNotNull($serie);
        $this->assertSame('SerieDocumental', $serie->tipo);
        $this->assertNull($serie->parent);
        $this->assertNotNull($subserie);
        $this->assertSame('SubSerieDocumental', $subserie->tipo);
        $this->assertSame($serie->id, (int) $subserie->parent);
    }

    public function test_import_calcula_total_anios(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd/import', [
                'archivo' => $this->crearXlsx([
                    ['A' => 'DEP', 'B' => 'S-ANI', 'E' => 'Serie con anos', 'H' => 5, 'I' => 3],
                ]),
            ])
            ->assertOk();

        $serie = ClasificacionDocumentalTVD::where('cod', 'S-ANI')->first();

        $this->assertSame(5, (int) $serie->gestion);
        $this->assertSame(3, (int) $serie->central);
        $this->assertSame(8, (int) $serie->total_anios);
    }

    public function test_dependencia_resuelta_aparece_en_validacion(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/clasifica-documental/tvd/validate', [
                'archivo' => $this->crearXlsx([['A' => 'DEP', 'B' => 'S-OK', 'E' => 'Serie valida']]),
            ]);

        $response->assertOk();
        $this->assertSame(
            $this->dependencia->nom_organico,
            $response->json('data.dependencia')
        );
    }
}

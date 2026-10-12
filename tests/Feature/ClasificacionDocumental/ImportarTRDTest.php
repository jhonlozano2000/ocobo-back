<?php

namespace Tests\Feature\ClasificacionDocumental;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M10-03: la importación de TRD no usaba transacción; una fila con error dejaba una versión TEMP
 * a medias y el front la anunciaba como importada.
 */
class ImportarTRDTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/clasifica-documental/trd/import-trd';

    private User $user;

    private CalidadOrganigrama $dependencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['estado' => 1]);
        $this->user->givePermissionTo(Permission::findOrCreate('TRD -> Importar', 'web'));
        $this->dependencia = CalidadOrganigrama::create([
            'tipo' => 'Dependencia', 'nom_organico' => 'Dependencia M10-03', 'cod_organico' => 'M1003'.random_int(100, 999),
        ]);
    }

    private function plantilla(string $codigo, array $filas): UploadedFile
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setCellValueExplicit('B4', $codigo, DataType::TYPE_STRING);
        $hoja->fromArray($filas, null, 'A7');

        $ruta = tempnam(sys_get_temp_dir(), 'trd').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return new UploadedFile($ruta, 'trd.xlsx', null, null, true);
    }

    private function filasValidas(): array
    {
        return [
            ['100', 'S1', '', '', 'Actas'],
            ['100', 'S1', 'S1.1', '', 'Actas de junta', '', '', '2', '8'],
        ];
    }

    public function test_importacion_valida_crea_version_y_filas(): void
    {
        $this->actingAs($this->user)
            ->post(self::URL, ['archivo' => $this->plantilla($this->dependencia->cod_organico, $this->filasValidas())], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.inserted', 2);

        $this->assertDatabaseHas('clasificacion_documental_trd_versiones', ['dependencia_id' => $this->dependencia->id, 'estado_version' => 'TEMP']);
        $this->assertDatabaseHas('clasificacion_documental_trd', ['dependencia_id' => $this->dependencia->id, 'nom' => 'Actas de junta']);
    }

    public function test_una_fila_con_error_revierte_toda_la_importacion(): void
    {
        // TipoDocumento sin serie previa: error de fila; las demás son válidas
        $filas = array_merge([['', '', '', '', 'Tipo huérfano']], $this->filasValidas());

        $this->actingAs($this->user)
            ->post(self::URL, ['archivo' => $this->plantilla($this->dependencia->cod_organico, $filas)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');

        $this->assertDatabaseMissing('clasificacion_documental_trd', ['dependencia_id' => $this->dependencia->id]);
        $this->assertDatabaseMissing('clasificacion_documental_trd_versiones', ['dependencia_id' => $this->dependencia->id]);
    }

    public function test_dependencia_inexistente_responde_422(): void
    {
        $this->actingAs($this->user)
            ->post(self::URL, ['archivo' => $this->plantilla('NO-EXISTE-'.uniqid(), $this->filasValidas())], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');
    }
}

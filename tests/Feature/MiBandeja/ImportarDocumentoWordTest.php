<?php

namespace Tests\Feature\MiBandeja;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Importar un Word en Mis Documentos: antes guardaba el nombre y la ruta del archivo en columnas que la tabla de
 * documentos no tiene y respondía 500.
 */
class ImportarDocumentoWordTest extends TestCase
{
    use DatabaseTransactions;

    public function test_importa_un_docx_como_documento_nuevo(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $yo = User::factory()->create(['estado' => 1]);

        $ruta = tempnam(sys_get_temp_dir(), 'docx');
        $word = new PhpWord;
        $word->addSection()->addText('Contenido importado');
        IOFactory::createWriter($word, 'Word2007')->save($ruta);

        $id = $this->actingAs($yo)->post('/api/mi-bandeja/comunicaciones-recibidas/documentos/importar', [
            'archivo' => new UploadedFile($ruta, 'Informe anual.docx', null, null, true),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.documento.id');
        @unlink($ruta);

        $this->assertDatabaseHas('mi_bandeja_temp_reci_documentos', ['id' => $id, 'titulo' => 'Informe anual', 'user_id' => $yo->id]);
        $this->assertStringContainsString(
            'Contenido importado',
            DB::table('mi_bandeja_temp_reci_contenido')->where('documento_id', $id)->value('contenido_yjs')
        );
    }
}

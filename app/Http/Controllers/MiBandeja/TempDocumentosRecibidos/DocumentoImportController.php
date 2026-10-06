<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\TempReci\ImportDocumentoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DocumentoImportController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected DocumentoImportService $importService
    ) {}

    public function importar(ImportDocumentoRequest $request): JsonResponse
    {
        try {
            $archivo = $request->file('archivo');

            $extension = strtolower($archivo->getClientOriginalExtension());
            $soportados = ['docx', 'doc', 'html', 'htm', 'txt'];

            if (! in_array($extension, $soportados)) {
                return $this->badRequestResponse("Formato no soportado: {$extension}. Use: ".implode(', ', $soportados));
            }

            $tamanoMaximo = 10 * 1024 * 1024;
            if ($archivo->getSize() > $tamanoMaximo) {
                return $this->badRequestResponse('El archivo es demasiado grande. Máximo 10MB');
            }

            $documento = $this->importService->importar(
                $archivo,
                $request->user()->id,
                $request->input('radica_reci_id')
            );

            return $this->createdResponse([
                'documento' => [
                    'id' => $documento->id,
                    'titulo' => $documento->titulo,
                    'estado' => $documento->estado,
                ],
            ], 'Documento importado correctamente');

        } catch (\InvalidArgumentException $e) {
            return $this->badRequestResponse($e->getMessage());
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al importar el documento', config('app.debug') ? $e->getMessage() : null);
        }
    }

    public function importarHtml(ImportDocumentoRequest $request): JsonResponse
    {
        try {
            $documento = $this->importService->importarDesdeTexto(
                $request->input('contenido'),
                $request->input('titulo'),
                $request->user()->id,
                $request->input('radica_reci_id')
            );

            return $this->createdResponse([
                'documento' => [
                    'id' => $documento->id,
                    'titulo' => $documento->titulo,
                    'estado' => $documento->estado,
                ],
            ], 'Documento importado correctamente');

        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al importar el documento', config('app.debug') ? $e->getMessage() : null);
        }
    }
}

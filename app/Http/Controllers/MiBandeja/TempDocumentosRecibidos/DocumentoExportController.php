<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DocumentoExportController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected DocumentoExportService $exportService
    ) {}

    public function exportar(Request $request, Documento $documento, string $formato): Response
    {
        if (! $documento->tieneAcceso($request->user())) {
            return $this->forbiddenResponse('No tienes acceso a este documento');
        }

        $metodos = [
            'pdf' => 'exportarPdf',
            'docx' => 'exportarDocx',
            'html' => 'exportarHtml',
            'txt' => 'exportarTxt',
        ];

        if (! isset($metodos[$formato])) {
            return $this->badRequestResponse('Formato no soportado. Use: pdf, docx, html, txt');
        }

        try {
            $rutaArchivo = $this->exportService->{$metodos[$formato]}($documento);
            $nombreArchivo = $documento->titulo.'.'.$formato;

            return response()->download($rutaArchivo, $nombreArchivo, [
                'Content-Type' => $this->getMimeType($formato),
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->serverErrorResponse('Error al exportar el documento', $e->getMessage());
        }
    }

    private function getMimeType(string $formato): string
    {
        return match ($formato) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'html' => 'text/html',
            'txt' => 'text/plain',
            default => 'application/octet-stream'
        };
    }
}

<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentoExportController extends Controller
{
    public function __construct(
        protected DocumentoExportService $exportService
    ) {}

    public function exportar(Request $request, Documento $documento, string $formato): Response
    {
        if (!$documento->tieneAcceso($request->user())) {
            return response()->json(['message' => 'No tienes acceso a este documento'], 403);
        }

        $metodos = [
            'pdf' => 'exportarPdf',
            'docx' => 'exportarDocx',
            'html' => 'exportarHtml',
            'txt' => 'exportarTxt',
        ];

        if (!isset($metodos[$formato])) {
            return response()->json([
                'message' => 'Formato no soportado. Use: pdf, docx, html, txt'
            ], 400);
        }

        try {
            $rutaArchivo = $this->exportService->{$metodos[$formato]}($documento);
            $nombreArchivo = $documento->titulo . '.' . $formato;

            return response()->download($rutaArchivo, $nombreArchivo, [
                'Content-Type' => $this->getMimeType($formato),
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al exportar el documento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getMimeType(string $formato): string
    {
        return match($formato) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'html' => 'text/html',
            'txt' => 'text/plain',
            default => 'application/octet-stream'
        };
    }
}
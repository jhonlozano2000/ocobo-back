<?php

namespace App\Http\Controllers\MiBandeja\TempDocumentosRecibidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documento\ImportDocumentoRequest;
use App\Services\MiBandeja\TempDocumentosRecibidos\DocumentoImportService;
use Illuminate\Http\JsonResponse;

class DocumentoImportController extends Controller
{
    public function __construct(
        protected DocumentoImportService $importService
    ) {}

    public function importar(ImportDocumentoRequest $request): JsonResponse
    {
        try {
            $archivo = $request->file('archivo');
            
            $extension = strtolower($archivo->getClientOriginalExtension());
            $soportados = ['docx', 'doc', 'html', 'htm', 'txt'];

            if (!in_array($extension, $soportados)) {
                return response()->json([
                    'message' => "Formato no soportado: {$extension}. Use: " . implode(', ', $soportados)
                ], 400);
            }

            $tamanoMaximo = 10 * 1024 * 1024;
            if ($archivo->getSize() > $tamanoMaximo) {
                return response()->json([
                    'message' => 'El archivo es demasiado grande. Máximo 10MB'
                ], 400);
            }

            $documento = $this->importService->importar(
                $archivo,
                $request->user()->id,
                $request->input('radica_reci_id')
            );

            return response()->json([
                'message' => 'Documento importado correctamente',
                'documento' => [
                    'id' => $documento->id,
                    'titulo' => $documento->titulo,
                    'estado' => $documento->estado,
                ]
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al importar el documento',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    public function importarHtml(ImportDocumentoRequest $request): JsonResponse
    {
        try {
            $request->validate([
                'contenido' => 'required|string',
                'titulo' => 'required|string|max:255',
            ]);

            $documento = $this->importService->importarDesdeTexto(
                $request->input('contenido'),
                $request->input('titulo'),
                $request->user()->id,
                $request->input('radica_reci_id')
            );

            return response()->json([
                'message' => 'Documento importado correctamente',
                'documento' => [
                    'id' => $documento->id,
                    'titulo' => $documento->titulo,
                    'estado' => $documento->estado,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al importar el documento',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\MiBandeja;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiDisco\MiDiscoCarpeta;
use App\Models\MiDisco\MiDiscoArchivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MiDiscoController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $carpetaId = $request->get('carpeta_id');

        $carpetas = MiDiscoCarpeta::where('user_id', $user->id)
            ->where('carpeta_padre_id', $carpetaId)
            ->orderBy('nombre')
            ->get();

        $archivos = MiDiscoArchivo::where('user_id', $user->id)
            ->where('carpeta_id', $carpetaId)
            ->orderBy('nombre')
            ->get();

        $breadcrumb = [];
        if ($carpetaId) {
            $current = MiDiscoCarpeta::find($carpetaId);
            while ($current) {
                array_unshift($breadcrumb, [
                    'id' => $current->id,
                    'nombre' => $current->nombre,
                ]);
                $current = $current->padre;
            }
        }

        return $this->successResponse([
            'carpetas' => $carpetas,
            'archivos' => $archivos,
            'breadcrumb' => $breadcrumb,
        ], 'Listado obtenido');
    }

    public function storeCarpeta(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'carpeta_padre_id' => 'nullable|exists:mi_disco_carpetas,id',
        ]);

        $user = Auth::user();

        $carpeta = MiDiscoCarpeta::create([
            'nombre' => $request->nombre,
            'carpeta_padre_id' => $request->carpeta_padre_id,
            'user_id' => $user->id,
        ]);

        return $this->successResponse($carpeta, 'Carpeta creada', 201);
    }

    public function destroyCarpeta($id): JsonResponse
    {
        $user = Auth::user();
        $carpeta = MiDiscoCarpeta::where('user_id', $user->id)->findOrFail($id);

        $this->eliminarCarpetaRecursivo($carpeta);
        $carpeta->delete();

        return $this->successResponse(null, 'Carpeta eliminada');
    }

    public function subirArchivo(Request $request): JsonResponse
    {
        $request->validate([
            'archivos' => 'required|array|max:10',
            'archivos.*' => 'file|max:51200',
            'carpeta_id' => 'nullable|exists:mi_disco_carpetas,id',
        ]);

        $user = Auth::user();
        $archivosCreados = [];

        foreach ($request->file('archivos') as $file) {
            $nombreUniq = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $ruta = $file->storeAs('mi_disco/' . $user->id, $nombreUniq, 'local');

            $archivosCreados[] = MiDiscoArchivo::create([
                'nombre' => $nombreUniq,
                'nombre_original' => $file->getClientOriginalName(),
                'carpeta_id' => $request->carpeta_id,
                'user_id' => $user->id,
                'ruta_almacenamiento' => $ruta,
                'peso' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'hash_sha256' => hash_file('sha256', $file->getRealPath()),
            ]);
        }

        return $this->successResponse($archivosCreados, 'Archivos subidos', 201);
    }

    public function downloadArchivo($id)
    {
        $user = Auth::user();
        $archivo = MiDiscoArchivo::where('user_id', $user->id)->findOrFail($id);

        if (!Storage::disk('local')->exists($archivo->ruta_almacenamiento)) {
            abort(404, 'Archivo no encontrado en disco');
        }

        return Storage::disk('local')->download(
            $archivo->ruta_almacenamiento,
            $archivo->nombre_original
        );
    }

    public function destroyArchivo($id): JsonResponse
    {
        $user = Auth::user();
        $archivo = MiDiscoArchivo::where('user_id', $user->id)->findOrFail($id);

        if (Storage::disk('local')->exists($archivo->ruta_almacenamiento)) {
            Storage::disk('local')->delete($archivo->ruta_almacenamiento);
        }

        $archivo->delete();

        return $this->successResponse(null, 'Archivo eliminado');
    }

    private function eliminarCarpetaRecursivo(MiDiscoCarpeta $carpeta): void
    {
        foreach ($carpeta->archivos as $archivo) {
            if (Storage::disk('local')->exists($archivo->ruta_almacenamiento)) {
                Storage::disk('local')->delete($archivo->ruta_almacenamiento);
            }
            $archivo->delete();
        }

        foreach ($carpeta->subcarpetas as $subcarpeta) {
            $this->eliminarCarpetaRecursivo($subcarpeta);
            $subcarpeta->delete();
        }
    }
}

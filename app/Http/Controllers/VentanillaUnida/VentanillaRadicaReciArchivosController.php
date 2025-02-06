<?php

namespace App\Http\Controllers\VentanillaUnida;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\VentanillaRadicaReci;
use App\Models\VentanillaUnica\VentanillaRadicaReciArchivoEliminado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class VentanillaRadicaReciArchivosController extends Controller
{
    public function upload(Request $request, $id)
    {
        $maxSize = ConfigVarias::getValor('max_tamano_archivo', 20480);
        $allowedExtensions = explode(',', ConfigVarias::getValor('tipos_archivos_permitidos', 'pdf,jpg,png,docx'));

        $request->validate([
            'archivo' => 'required|file|max:' . $maxSize . '|mimes:' . implode(',', $allowedExtensions),
        ]);

        $radicado = VentanillaRadicaReci::find($id);

        if (!$radicado) {
            return response()->json(['message' => 'Radicación no encontrada'], 404);
        }

        $archivo = $request->file('archivo');
        $path = $archivo->store('radicaciones_recibidas');

        // Guardar quién subió el archivo (si hay usuario autenticado)
        $usuario = Auth::check() ? Auth::user() : null;

        $radicado->update([
            'archivo_radica' => $path,
            'uploaded_by' => $usuario ? $usuario->id : null,
        ]);

        return response()->json([
            'message' => 'Archivo subido correctamente',
            'path' => $path,
            'uploaded_by' => $usuario ? $usuario->name : 'No se registró usuario',
        ]);
    }

    public function download($id)
    {
        $radicado = VentanillaRadicaReci::find($id);

        if (!$radicado || !$radicado->archivo_radica) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        return Storage::download($radicado->archivo_radica);
    }

    public function deleteFile($id)
    {
        $radicado = VentanillaRadicaReci::find($id);

        if (!$radicado || !$radicado->archivo_radica) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        $archivoEliminado = $radicado->archivo_radica;

        // Eliminar el archivo del storage
        Storage::delete($archivoEliminado);

        // Guardar información del usuario que lo eliminó en el historial
        $usuario = Auth::user();
        VentanillaRadicaReciArchivoEliminado::create([
            'radicado_id' => $radicado->id,
            'archivo' => $archivoEliminado,
            'deleted_by' => $usuario ? $usuario->id : null,
            'deleted_at' => now(),
        ]);

        // Limpiar el campo en la tabla principal
        $radicado->update(['archivo_radica' => null]);

        return response()->json([
            'message' => 'Archivo eliminado correctamente',
            'deleted_by' => $usuario ? $usuario->name : 'Usuario no identificado',
        ]);
    }

    public function historialEliminaciones($id)
    {
        $historial = VentanillaRadicaReciArchivoEliminado::where('radicado_id', $id)
            ->with('usuario')
            ->orderBy('deleted_at', 'desc')
            ->get();

        return response()->json(['historial' => $historial]);
    }
}

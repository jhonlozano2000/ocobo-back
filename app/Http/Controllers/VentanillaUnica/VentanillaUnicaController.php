<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\VentanillaUnica;
use Illuminate\Http\Request;

class VentanillaUnicaController extends Controller
{
    /**
     * Listar todas las ventanillas de una sede
     */
    public function index($sedeId)
    {
        $ventanillas = VentanillaUnica::where('sede_id', $sedeId)->get();

        return response()->json([
            'status' => true,
            'data' => $ventanillas
        ]);
    }

    /**
     * Crear una nueva ventanilla en una sede
     */
    public function store(Request $request, $sedeId)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string'
        ]);

        $ventanilla = VentanillaUnica::create([
            'sede_id' => $sedeId,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion
        ]);

        return response()->json([
            'status' => true,
            'data' => $ventanilla,
            'message' => 'Ventanilla creada correctamente'
        ], 201);
    }

    /**
     * Mostrar una ventanilla específica
     */
    public function show($sedeId, $id)
    {
        $ventanilla = VentanillaUnica::where('sede_id', $sedeId)->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $ventanilla
        ]);
    }

    /**
     * Actualizar una ventanilla
     */
    public function update(Request $request, $sedeId, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string'
        ]);

        $ventanilla = VentanillaUnica::where('sede_id', $sedeId)->findOrFail($id);
        $ventanilla->update($request->only(['nombre', 'descripcion']));

        return response()->json([
            'status' => true,
            'data' => $ventanilla,
            'message' => 'Ventanilla actualizada correctamente'
        ]);
    }

    /**
     * Eliminar una ventanilla
     */
    public function destroy($sedeId, $id)
    {
        $ventanilla = VentanillaUnica::where('sede_id', $sedeId)->findOrFail($id);
        $ventanilla->delete();

        return response()->json([
            'status' => true,
            'message' => 'Ventanilla eliminada correctamente'
        ]);
    }

    /**
     * Configurar los tipos documentales permitidos en la ventanilla
     */
    public function configurarTiposDocumentales(Request $request, $id)
    {
        $request->validate([
            'tipos_documentales' => 'required|array|min:1',
            'tipos_documentales.*' => 'exists:clasificacion_documental_trd,id'
        ]);

        $ventanilla = VentanillaUnica::findOrFail($id);
        $ventanilla->tiposDocumentales()->sync($request->tipos_documentales);

        return response()->json([
            'status' => true,
            'message' => 'Tipos documentales configurados correctamente'
        ]);
    }

    /**
     * Listar los tipos documentales permitidos en una ventanilla
     */
    public function listarTiposDocumentales($id)
    {
        $ventanilla = VentanillaUnica::with('tiposDocumentales')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $ventanilla->tiposDocumentales
        ]);
    }
}

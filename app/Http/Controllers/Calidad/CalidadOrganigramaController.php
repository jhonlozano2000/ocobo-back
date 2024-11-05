<?php

namespace App\Http\Controllers\Calidad;

use App\Http\Controllers\Controller;
use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Http\Request;
use \Validator;

class CalidadOrganigramaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Obtener todas las dependencias (nodos raíz) donde el campo 'tipo' es 'dependencia' y no tienen un padre
        $organigrama = CalidadOrganigrama::whereIn('tipo', ['Dependencia', 'Oficina'])
            ->whereNull('parent')
            ->with('children') // Carga los hijos de cada nodo, sin importar su tipo
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $organigrama,
            'message' => 'Organigrama de calidad obtenido correctamente'
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'required',
            'nom_organico' => 'required|min:2|max:100',
            'parent' => 'nullable|exists:calidad_organigramas,id', // Valida que el padre exista si se envía
        ], [
            'tipo.requires' => 'Te hizo falta el tipo',
            'nom_organico.required' => 'Te hizo falta el nombre del organismo',
            'nom_organico.min' => 'El nombre del organismo debe tener al menos 2 caracteres',
            'nom_organico.max' => 'El nombre del organismo no puede tener más de 100 caracteres',
            'parent.exists' => 'El padre no existe', // Valida que el padre exista si se envía
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        $organigrama = CalidadOrganigrama::create([
            'tipo' => $request->tipo,
            'cod_organico' => $request->cod_organico,
            'nom_organico' => $request->nom_organico,
            'observaciones' => $request->observaciones,
            'parent' => $request->parent
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $organigrama,
            'message' => 'Organismo creado correctamente'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Int $id)
    {
        $organigrama = CalidadOrganigrama::find($id);

        if (!$organigrama) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organismo no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $organigrama
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Int $id)
    {
        $organigrama = CalidadOrganigrama::find($id);

        if (!$organigrama) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organismo no encontrado'
            ], 404);
        }

        $organigrama->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Organismo eliminado correctamente'
        ]);
    }
}

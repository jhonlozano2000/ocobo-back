<?php

namespace App\Http\Controllers\Calidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\CalidadOrganigramaRequest;
use App\Models\Calidad\CalidadOrganigrama;

class CalidadOrganigramaController extends Controller
{
    /**
     * Mostrar la estructura organizacional completa.
     */
    public function index()
    {
        $organigrama = CalidadOrganigrama::dependenciasRaiz()->with('children')->get();

        return response()->json([
            'status' => 'success',
            'data' => $organigrama,
            'message' => 'Organigrama obtenido correctamente'
        ]);
    }

    /**
     * Crear un nuevo nodo en el organigrama.
     */
    public function store(CalidadOrganigramaRequest $request)
    {
        $organigrama = CalidadOrganigrama::create($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $organigrama,
            'message' => 'Nodo creado correctamente'
        ]);
    }

    /**
     * Mostrar un nodo específico.
     */
    public function show(Int $id)
    {
        $organigrama = CalidadOrganigrama::find($id);

        if (!$organigrama) {
            return response()->json(['status' => 'error', 'message' => 'Nodo no encontrado'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $organigrama]);
    }

    /**
     * Actualizar un nodo.
     */
    public function update(CalidadOrganigramaRequest $request, Int $id)
    {
        $nodo = CalidadOrganigrama::find($id);
        if (!$nodo) {
            return response()->json(['status' => 'error', 'message' => 'Nodo no encontrado'], 404);
        }

        $nodo->update($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $nodo,
            'message' => 'Nodo actualizado correctamente'
        ]);
    }

    /**
     * Eliminar un nodo solo si no tiene hijos.
     */
    public function destroy(Int $id)
    {
        $organigrama = CalidadOrganigrama::find($id);

        if (!$organigrama) {
            return response()->json(['status' => 'error', 'message' => 'Nodo no encontrado'], 404);
        }

        if ($organigrama->children()->count() > 0) {
            return response()->json(['status' => 'error', 'message' => 'No se puede eliminar el nodo porque tiene subelementos.'], 400);
        }

        $organigrama->delete();

        return response()->json(['status' => 'success', 'message' => 'Nodo eliminado correctamente']);
    }

    /**
     * Obtener solo las dependencias principales.
     */
    public function listDependencias()
    {
        $dependencias = CalidadOrganigrama::dependenciasRaiz()->with('childrenDependencias')->get();

        return response()->json([
            'status' => 'success',
            'data' => $dependencias,
            'message' => 'Lista de dependencias obtenida'
        ]);
    }

    public function listOficinas()
    {
        $oficinasConCargos = CalidadOrganigrama::where('tipo', 'Oficina') // Filtrar solo oficinas
            ->with('childrenCargos') // Cargar los cargos de cada oficina
            ->get();

        return response()->json([
            "status" => "success",
            "data" => $oficinasConCargos,
            "message" => "Lista de oficinas con sus respectivos cargos obtenida correctamente"
        ], 200);
    }
}

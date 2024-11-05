<?php

namespace App\Http\Controllers\Calidad\Organigrama;

use App\Http\Controllers\Controller;
use App\Models\Calidad\Organigrama\CalidadOrganiOficina;
use Illuminate\Http\Request;
use \Validator;

class CalidadOrganiOficinaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $oficinas = CalidadOrganiOficina::all();
        return response()->json([
            'status' => true,
            'data' => $oficinas,
            'message' => 'Listado de oficinas'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dependencia_id' => 'required',
            'nom_oficina' => 'required|min:2|max:100',
        ], [
            'dependencia_id' => 'Te hizo falta la dependenca',
            'nom_oficina.required' => 'El nombre de la oficina es obligatorio',
            'nom_oficina.min' => 'El nombre de la oficina debe tener al menos 2 caracteres',
            'nom_oficina.max' => 'El nombre de la oficina no puede tener más de 100 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 400); // Devuelve un error 400 (Bad Request) con los errores
        }

        $oficina = new CalidadOrganiOficina();
        $oficina->dependencia_id = $request->dependencia_id;
        $oficina->cod_oficina = $request->cod_oficina;
        $oficina->nom_oficina = $request->nom_oficina;
        $oficina->save();

        return response()->json([
            'status' => true,
            'data' => $oficina,
            'message' => 'Oficina creada correctamente'
        ], 201); // Devuelve un 201 (Created) con los datos de la oficina creada
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $oficina = CalidadOrganiOficina::find($id);

        if (!$oficina) {
            return response()->json([
                'status' => false,
                'message' => 'Oficina no encontrada'
            ], 404); // Devuelve un error 404 (Not Found)
        }

        return response()->json([
            'status' => true,
            'data' => $oficina,
            'message' => 'Oficina encontrada'
        ], 200); // Devuelve un 200 (OK) con los datos de la oficina
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $oficina = CalidadOrganiOficina::find($id);
        if (!$oficina) {
            return response()->json([
                'status' => false,
                'message' => 'Oficina no encontrada'
            ], 404); // Devuelve un error 404 (Not Found)
        }

        $validator = Validator::make($request->all(), [
            'dependencia_id' => 'required',
            'nom_oficina' => 'required|min:2|max:10',
        ], [
            'dependencia_id' => 'Te hizo falta la dependenca',
            'nom_oficina.required' => 'El nombre de la oficina es obligatorio',
            'nom_oficina.min' => 'El nombre de la oficina debe tener al menos 2 caracteres',
            'nom_oficina.max' => 'El nombre de la oficina debe tener maxímo 150 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 400); // Devuelve un error 400 (Bad Request) con los errores
        }

        $oficina = CalidadOrganiOficina::updated($request->all());
        return response()->json([
            'status' => true,
            'data' => $oficina,
            'message' => 'Oficina actualizada correctamente'
        ], 200); // Devuelve un 200 (OK) con los datos de la oficina actualizada
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ind $id)
    {
        $oficina = CalidadOrganiOficina::find($id);
        if (!$oficina) {
            return response()->json([
                'status' => false,
                'message' => 'Oficina no encontrada'
            ], 404); // Devuelve un error 404 (Not Found)
        }

        $oficina->delete();
        return response()->json([
            'status' => true,
            'message' => 'Oficina eliminada correctamente'
        ], 200); // Devuelve un 200 (OK) con un mensaje de confirmación de eliminación
    }
}

<?php

namespace App\Http\Controllers\Calidad\Organigrama;

use App\Http\Controllers\Controller;
use App\Models\Calidad\Organigrama\CalidadOrganiDependencia;
use Illuminate\Http\Request;
use \Validator;

class CalidadOrganiDependenciaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => CalidadOrganiDependencia::get(),
            'messages' => 'Listado de dependencias'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom_depen' => 'required|min:2|max:150|unique:calidad_organi_dependencias'
        ], [
            'nom_depen.requires' => 'Te hizo falta el nombre de la dependencia',
            'nom_depen.unique' => 'El nombre de la dependencia ya esta en uso',
            'nom_depen.min' => 'La dependencia debe temer minímo 2 caracteres',
            'nom_depen.max' => 'La dependencia debe temer maxímo 150 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'messages' => $validator->errors()
            ], 422);
        }

        $depen = new CalidadOrganiDependencia();
        $depen->cod_depen = $request->cod_depen;
        $depen->nom_depen = $request->nom_depen;
        $depen->save();


        return response()->json([
            'status' => true,
            'data' => $depen,
            'messages' => 'Dependencia creada exitosamente'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $depen = CalidadOrganiDependencia::find($id);
        if (!$depen) {
            return response()->json([
                'status' => false,
                'message' => 'Dependencia no encontrada'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $depen,
            'messages' => 'Dependencia encontrada'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $depen = CalidadOrganiDependencia::find($id);
        if (!$depen) {
            return response()->json([
                'status' => false,
                'message' => 'Dependencia no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom_depen' => 'required|min:2|max:150'
        ], [
            'nom_depen.requires' => 'Te hizo falta el nombre de la dependencia',
            'nom_depen.min' => 'La dependencia debe temer minímo 2 caracteres',
            'nom_depen.max' => 'La dependencia debe tener maxímo 150 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'messages' => $validator->errors()
            ], 422);
        }

        $depen = CalidadOrganiDependencia::updated($request->all());
        return response()->json([
            'status' => true,
            'data' => $depen,
            'messages' => 'Dependencia actualizada exitosamente'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $depen = CalidadOrganiDependencia::find($id);
        if (!$depen) {
            return response()->json([
                'status' => false,
                'message' => 'Dependencia no encontrada'
            ], 404);
        }

        $depen->delete();
        return response()->json([
            'status' => true,
            'messages' => 'Dependencia eliminada exitosamente'
        ], 200);
    }

    public function getAllDependencia()
    {
        $depen = CalidadOrganiDependencia::with('oficinas')->where('id', '=', 3)->get();
        return response()->json([
            'status' => true,
            'data' => $depen,
            'messages' => 'Dependencias encontradas'
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\Calidad\Organigrama;

use App\Http\Controllers\Controller;
use App\Models\Calidad\Organigrama\CalidadOrganiCargo;
use Illuminate\Http\Request;
use \Validator;

class CalidadOrganiCargoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $calidadOrganiCargos = CalidadOrganiCargo::all();
        return response()->json([
            'starus' => true,
            'data' => $calidadOrganiCargos,
            'message' => 'Listado de cargos'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'oficina_id' => 'required',
            'nom_cargo' => 'required|min:3|max:100',
        ], [
            'oficina_id' => 'Te hizo falta la oficina',
            'nom_cargo.required' => 'El nombre del cargo es obligatorio',
            'nom_cargo.min' => 'El nombre del cargo debe tener al menos 3 caracteres',
            'nom_cargo.max' => 'El nombre del cargo no puede tener más de 100 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 400); // Devuelve un error 400 (Bad Request) con los errores
        }

        $calidadOrganiCargo = CalidadOrganiCargo::create([
            'oficina_id' => $request->oficina_id,
            'nom_cargo' => $request->nom_cargo,
        ]);

        return response()->json([
            'status' => true,
            'data' => $calidadOrganiCargo,
            'message' => 'Cargo creado correctamente'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $calidadOrganiCargo = CalidadOrganiCargo::find($id);
        if (!$calidadOrganiCargo) {
            return response()->json([
                'status' => false,
                'message' => 'Cargo no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $calidadOrganiCargo,
            'message' => 'Cargo encontrado'
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $calidadOrganiCargo = CalidadOrganiCargo::find($id);
        if (!$calidadOrganiCargo) {
            return response()->json([
                'status' => false,
                'message' => 'Cargo no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'oficina_id' => 'required',
            'nom_cargo' => 'required|min:3|max:100',
        ], [
            'oficina_id' => 'Te hizo falta la oficina',
            'nom_cargo.required' => 'El nombre del cargo es obligatorio',
            'nom_cargo.min' => 'El nombre del cargo debe tener al menos 3 caracteres',
            'nom_cargo.max' => 'El nombre del cargo no puede tener más de 100 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 400); // Devuelve un error 400 (Bad Request) con los errores
        }

        $calidadOrganiCargo->nom_cargo = $request->nom_cargo;
        $calidadOrganiCargo->save();

        return response()->json([
            'status' => true,
            'message' => 'Cargo actualizado correctamente'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $calidadOrganiCargo = CalidadOrganiCargo::find($id);
        if (!$calidadOrganiCargo) {
            return response()->json([
                'status' => false,
                'message' => 'Cargo no encontrado'
            ], 404);
        }

        $calidadOrganiCargo->delete();

        return response()->json([
            'status' => true,
            'message' => 'Cargo eliminado correctamente'
        ], 200);
    }
}

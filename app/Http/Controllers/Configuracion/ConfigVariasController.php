<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\ConfigVarias;
use Illuminate\Http\Request;

class ConfigVariasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ConfigVarias::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ConfigVarias $configVarias)
    {
        //
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $clave)
    {
        $config = ConfigVarias::where('clave', $clave)->first();

        if (!$config) {
            return response()->json(['message' => 'Configuración no encontrada'], 404);
        }

        $request->validate([
            'valor' => 'required|string|max:255',
        ]);

        $config->update(['valor' => $request->valor]);

        return response()->json(['message' => 'Configuración actualizada correctamente']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConfigVarias $configVarias)
    {
        //
    }
}

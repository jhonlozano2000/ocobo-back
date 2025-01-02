<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\Gestion\GestionTercero;
use \Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GestionTerceroController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $terceros = GestionTercero::with('divisionPolitica')->get();
        return response()->json($terceros, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'divi_pilo_id' => 'nullable|exists:config_divi_poli,id',
            'num_docu_nit' => 'nullable|string|max:25|unique:gestion_terceros,num_docu_nit',
            'nom_razo_soci' => 'nullable|string|max:150',
            'direccion' => 'nullable|string|max:150',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:70',
            'tipo' => 'required|in:Natural,Juridico',
            'notifica_email' => 'boolean',
            'notifica_msm' => 'boolean',
        ], [
            'divi_pilo_id.exists' => 'La división política seleccionada no existe.',
            'num_docu_nit.unique' => 'El número de documento o NIT ya está registrado.',
            'num_docu_nit.max' => 'El número de documento o NIT no puede exceder los 25 caracteres.',
            'nom_razo_soci.max' => 'El nombre o razón social no puede exceder los 150 caracteres.',
            'direccion.max' => 'La dirección no puede exceder los 150 caracteres.',
            'telefono.max' => 'El teléfono no puede exceder los 30 caracteres.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.max' => 'El correo electrónico no puede exceder los 70 caracteres.',
            'tipo.required' => 'El tipo de tercero es obligatorio.',
            'tipo.in' => 'El tipo de tercero debe ser "Natural" o "Jurídico".',
            'notifica_email.boolean' => 'El valor de notificar por email debe ser verdadero o falso.',
            'notifica_msm.boolean' => 'El valor de notificar por SMS debe ser verdadero o falso.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $tercero = GestionTercero::create($request->all());

        return response()->json([
            'status' => true,
            'data' => $tercero,
            'message' => 'Tercero creado exitosamente',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $tercero = GestionTercero::with('divisionPolitica')->find($id);

        if (!$tercero) {
            return response()->json([
                'status' => false,
                'message' => 'Tercero no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $tercero,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $tercero = GestionTercero::find($id);

        if (!$tercero) {
            return response()->json([
                'status' => false,
                'message' => 'Tercero no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'divi_pilo_id' => 'nullable|exists:config_divi_poli,id',
            'num_docu_nit' => 'nullable|string|max:25|unique:gestion_terceros,num_docu_nit,' . $tercero->id,
            'nom_razo_soci' => 'nullable|string|max:150',
            'direccion' => 'nullable|string|max:150',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:70',
            'tipo' => 'required|in:Natural,Juridico',
            'notifica_email' => 'boolean',
            'notifica_msm' => 'boolean',
        ], [
            'divi_pilo_id.exists' => 'La división política seleccionada no existe.',
            'num_docu_nit.unique' => 'El número de documento o NIT ya está registrado.',
            'num_docu_nit.max' => 'El número de documento o NIT no puede exceder los 25 caracteres.',
            'nom_razo_soci.max' => 'El nombre o razón social no puede exceder los 150 caracteres.',
            'direccion.max' => 'La dirección no puede exceder los 150 caracteres.',
            'telefono.max' => 'El teléfono no puede exceder los 30 caracteres.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.max' => 'El correo electrónico no puede exceder los 70 caracteres.',
            'tipo.required' => 'El tipo de tercero es obligatorio.',
            'tipo.in' => 'El tipo de tercero debe ser "Natural" o "Jurídico".',
            'notifica_email.boolean' => 'El valor de notificar por email debe ser verdadero o falso.',
            'notifica_msm.boolean' => 'El valor de notificar por SMS debe ser verdadero o falso.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $tercero->update($request->all());

        return response()->json([
            'status' => true,
            'data' => $tercero,
            'message' => 'Tercero actualizado exitosamente',
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $tercero = GestionTercero::find($id);

        if (!$tercero) {
            return response()->json([
                'status' => false,
                'message' => 'Tercero no encontrado',
            ], 404);
        }

        $tercero->delete();

        return response()->json([
            'status' => true,
            'message' => 'Tercero eliminado exitosamente',
        ], 200);
    }

    public function estadisticas()
    {
        $totalTerceros = GestionTercero::count();
        $totalNaturales = GestionTercero::where('tipo', 'Natural')->count();
        $totalJuridicos = GestionTercero::where('tipo', 'Juridico')->count();

        return response()->json([
            'status' => true,
            'data' => [
                'total_terceros' => $totalTerceros,
                'total_naturales' => $totalNaturales,
                'total_juridicos' => $totalJuridicos,
            ]
        ], 200);
    }

    public function filterTerceros(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:3',
        ]);

        $query = $request->input('query');

        $terceros = DB::table('gestion_terceros')
            ->where('num_docu_nit', 'like', '%' . $query . '%')
            ->orWhere('nom_razo_soci', 'like', '%' . $query . '%')
            ->select('id', 'nom_razo_soci as text', 'num_docu_nit')
            ->limit(100) // Ajusta el límite según sea necesario
            ->get();

        return response()->json([
            'status' => true,
            'data' => $terceros,
        ]);
    }
}

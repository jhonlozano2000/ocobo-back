<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\GestionTerceroRequest;
use App\Models\Configuracion\ConfigDiviPoli;
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
        return response()->json(GestionTercero::with('divisionPolitica')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(GestionTerceroRequest $request)
    {
        $municipio = ConfigDiviPoli::find($request->municipio_id);

        $departamento = $municipio ? ConfigDiviPoli::find($municipio->parent) : null;
        $pais = $departamento ? ConfigDiviPoli::find($departamento->parent) : null;

        $tercero = GestionTercero::create([
            'municipio_id' => $request->municipio_id,
            'departamento_id' => $departamento ? $departamento->id : null,
            'pais_id' => $pais ? $pais->id : null,
            'num_docu_nit' => $request->num_docu_nit,
            'nom_razo_soci' => $request->nom_razo_soci,
            'direccion' => $request->direccion,
            'telefono' => $request->telefono,
            'email' => $request->email,
            'tipo' => $request->tipo,
            'notifica_email' => $request->notifica_email,
            'notifica_msm' => $request->notifica_msm,
        ]);

        return response()->json($tercero, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $tercero = GestionTercero::with('divisionPolitica')->find($id);

        if (!$tercero) {
            return response()->json(['message' => 'Tercero no encontrado'], 404);
        }

        return response()->json($tercero);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(GestionTerceroRequest $request, $id)
    {
        $tercero = GestionTercero::find($id);

        if (!$tercero) {
            return response()->json(['message' => 'Tercero no encontrado'], 404);
        }

        $municipio = ConfigDiviPoli::find($request->municipio_id);

        $departamento = $municipio ? ConfigDiviPoli::find($municipio->parent) : null;
        $pais = $departamento ? ConfigDiviPoli::find($departamento->parent) : null;

        $tercero->update([
            'municipio_id' => $request->municipio_id,
            'departamento_id' => $departamento ? $departamento->id : null,
            'pais_id' => $pais ? $pais->id : null,
            'num_docu_nit' => $request->num_docu_nit,
            'nom_razo_soci' => $request->nom_razo_soci,
            'direccion' => $request->direccion,
            'telefono' => $request->telefono,
            'email' => $request->email,
            'tipo' => $request->tipo,
            'notifica_email' => $request->notifica_email,
            'notifica_msm' => $request->notifica_msm,
        ]);

        return response()->json($tercero);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $tercero = GestionTercero::find($id);

        if (!$tercero) {
            return response()->json(['message' => 'Tercero no encontrado'], 404);
        }

        $tercero->delete();

        return response()->json(['message' => 'Tercero eliminado correctamente']);
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
            ->select('id', 'nom_razo_soci', 'num_docu_nit')
            ->limit(100) // Ajusta el límite según sea necesario
            ->get();

        return response()->json([
            'status' => true,
            'data' => $terceros,
        ]);
    }
}

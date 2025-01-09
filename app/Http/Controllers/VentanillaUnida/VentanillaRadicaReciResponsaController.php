<?php

namespace App\Http\Controllers\VentanillaUnida;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\VentanillaRadicaReciResponsaRequest;
use App\Models\VentanillaUnica\RadicadoRecibidoResponsables;
use App\Models\VentanillaUnica\VentanillaRadicaReciResponsa;
use Illuminate\Http\Request;

class VentanillaRadicaReciResponsaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(VentanillaRadicaReciResponsa::with('usuarioCargo', 'radicado')->get());
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(VentanillaRadicaReciResponsaRequest $request)
    {
        $data = $request->validated();

        $responsables = [];

        if (isset($data[0])) {
            // Si es un array de objetos, usamos createMany para obtener los IDs generados
            $responsables = VentanillaRadicaReciResponsa::query()->insert($data);
        } else {
            // Si es un solo objeto, usamos create normalmente
            $responsables[] = VentanillaRadicaReciResponsa::create($data);
        }

        return response()->json([
            'message' => 'Responsables asignados correctamente',
            'data' => $responsables
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $responsable = VentanillaRadicaReciResponsa::with('usuarioCargo', 'radicado')->find($id);

        if (!$responsable) {
            return response()->json(['message' => 'Responsable no encontrado'], 404);
        }

        return response()->json($responsable);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VentanillaRadicaReciResponsaRequest $request, $id)
    {
        $responsable = VentanillaRadicaReciResponsa::find($id);

        if (!$responsable) {
            return response()->json(['message' => 'Responsable no encontrado'], 404);
        }

        $responsable->update($request->validated());

        return response()->json(['message' => 'Responsable actualizado correctamente', 'data' => $responsable]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $responsable = VentanillaRadicaReciResponsa::find($id);

        if (!$responsable) {
            return response()->json(['message' => 'Responsable no encontrado'], 404);
        }

        $responsable->delete();

        return response()->json(['message' => 'Responsable eliminado correctamente']);
    }

    // ✅ Obtener responsables de un radicado
    public function getByRadicado($radica_reci_id)
    {
        $responsables = VentanillaRadicaReciResponsa::with('usuarioCargo')
            ->where('radica_reci_id', $radica_reci_id)
            ->get();

        if ($responsables->isEmpty()) {
            return response()->json(['message' => 'No hay responsables asignados para este radicado'], 404);
        }

        return response()->json($responsables);
    }
}

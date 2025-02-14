<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClasificacionDocumental\AprobarTRDVersionRequest;
use App\Http\Requests\ClasificacionDocumental\ClasificacionDocumentalTRDVersionRequest;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion;
use Illuminate\Http\Request;

class ClasificacionDocumentalTRDVersionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $versiones = ClasificacionDocumentalTRDVersion::with(['dependencia', 'aprobadoPor'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $versiones,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClasificacionDocumentalTRDVersionRequest $request)
    {
        $ultimaVersion = ClasificacionDocumentalTRDVersion::where('dependencia_id', $request->dependencia_id)
            ->max('version');

        $nuevaVersion = ClasificacionDocumentalTRDVersion::create([
            'dependencia_id' => $request->dependencia_id,
            'version' => $ultimaVersion ? $ultimaVersion + 1 : 1,
            'estado_version' => 'TEMP',
            'observaciones' => $request->observaciones,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Versión creada correctamente',
            'data' => $nuevaVersion,
        ], 201);
    }
    /**
     * Display the specified resource.
     */
    public function show(ClasificacionDocumentalTRDVersion $clasificacionDocumentalTRDVersion)
    {
        $version = ClasificacionDocumentalTRDVersion::with(['dependencia', 'aprobadoPor'])->find($id);

        if (!$version) {
            return response()->json(['status' => false, 'message' => 'Versión no encontrada'], 404);
        }

        return response()->json(['status' => true, 'data' => $version]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClasificacionDocumentalTRDVersion $clasificacionDocumentalTRDVersion)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClasificacionDocumentalTRDVersion $clasificacionDocumentalTRDVersion)
    {
        //
    }

    public function aprobarVersion(Request $request, $dependenciaId)
    {
        $request->validate([
            'observaciones' => 'required|string|max:500',
        ]);

        $dependencia = CalidadOrganigrama::find($dependenciaId);

        if (!$dependencia) {
            return response()->json(['status' => false, 'message' => 'La dependencia no existe.'], 404);
        }

        $versionPendiente = ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
            ->where('estado_version', 'TEMP')
            ->first();

        if (!$versionPendiente) {
            return response()->json(['status' => false, 'message' => 'No hay versiones pendientes por aprobar.'], 400);
        }

        \DB::beginTransaction();

        try {
            // Marcar versiones anteriores como HISTÓRICO
            ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependenciaId)
                ->where('estado_version', 'ACTIVO')
                ->update(['estado_version' => 'HISTORICO']);

            // Aprobar la nueva versión
            $versionPendiente->update([
                'estado_version' => 'ACTIVO',
                'aprobado_por' => auth()->id(),
                'observaciones' => $request->observaciones,
                'fecha_aprobacion' => now(),
            ]);

            \DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Versión aprobada exitosamente.',
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error al aprobar la versión.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar TRD que esten pendientes por aprobar
     */
    public function listarPendientesPorAprobar()
    {
        // Obtener las dependencias con TRD en estado TEMP
        $dependencias = CalidadOrganigrama::whereHas('trds', function ($query) {
            $query->where('estado_version', 'TEMP');
        })
            ->with(['trds' => function ($query) {
                $query->where('estado_version', 'TEMP')->select('dependencia_id', 'version', 'estado_version')->distinct();
            }])
            ->select('id', 'nom_organico', 'cod_organico')
            ->get();

        if ($dependencias->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No hay TRD pendientes por aprobar.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $dependencias,
            'message' => 'Listado de TRD pendientes por aprobar obtenido correctamente.'
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClasificacionDocumental\UpdateClasificacionDocumentalRequest;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Auth;

class ClasificacionDocumentalTRDController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Obtener todas la TRD (nodos raíz) donde el campo 'tipo' es 'serie' y no tienen un padre
        $trd = ClasificacionDocumentalTRD::whereIn('tipo', ['Serie', 'SubSerie'])
            ->whereNull('parent')
            ->with('children') // Carga los hijos de cada nodo, sin importar su tipo
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $trd,
            'message' => 'Organigrama de calidad obtenido correctamente'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        // Verificar reglas de validación para el campo "parent"
        if (in_array($data['tipo'], ['SubSerie', 'TipoDocumento'])) {
            if (!isset($data['parent']) || empty($data['parent'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'El campo parent es obligatorio para SubSerie y TipoDocumento.'
                ], 400);
            }

            $parent = ClasificacionDocumentalTRD::find($data['parent']);

            if (!$parent) {
                return response()->json([
                    'status' => false,
                    'message' => 'El parent seleccionado no existe.'
                ], 400);
            }

            if ($data['tipo'] === 'SubSerie' && $parent->tipo !== 'Serie') {
                return response()->json([
                    'status' => false,
                    'message' => 'Las SubSeries solo pueden tener como parent una Serie.'
                ], 400);
            }

            if ($data['tipo'] === 'TipoDocumento' && !in_array($parent->tipo, ['Serie', 'SubSerie'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Los TipoDocumento solo pueden tener como parent una Serie o SubSerie.'
                ], 400);
            }
        }

        $trd = ClasificacionDocumentalTRD::create([
            'tipo' => $request->tipo,
            'cod' => $request->cod,
            'nom' => $request->nom,
            'a_g' => $request->a_g,
            'a_c' => $request->a_c,
            'ct' => $request->ct,
            'e' => $request->e,
            'm_d' => $request->m_d,
            's' => $request->s,
            'procedimiento' => $request->procedimiento,
            'parent' => $request->parent, // Puede ser null si es Serie
            'dependencia_id' => $request->dependencia_id,
            'user_register' => auth()->id(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Elemento creado correctamente.',
            'data' => $trd
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $trd = ClasificacionDocumentalTRD::with('children')->find($id);

        if (!$trd) {
            return response()->json([
                'status' => false,
                'message' => 'Elemento no encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $trd
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClasificacionDocumentalRequest $request, $id)
    {
        $trd = ClasificacionDocumentalTRD::find($id);

        if (!$trd) {
            return response()->json([
                'status' => false,
                'message' => 'Elemento no encontrado.'
            ], 404);
        }

        $trd->update($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Elemento actualizado correctamente.',
            'data' => $trd
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $trd = ClasificacionDocumentalTRD::find($id);

        if (!$trd) {
            return response()->json([
                'status' => false,
                'message' => 'Elemento no encontrado.'
            ], 404);
        }

        // Verificar si tiene hijos antes de eliminar
        if ($trd->children()->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'No se puede eliminar porque tiene elementos asociados.'
            ], 400);
        }

        $trd->delete();

        return response()->json([
            'status' => true,
            'message' => 'Elemento eliminado correctamente.'
        ], 200);
    }

    public function importarTRD(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:2048',
        ]);

        // Cargar el archivo
        $filePath = $request->file('file')->storeAs('temp_files', 'TRD_import_' . now()->timestamp . '.xlsx');
        //$file = Storage::disk('temp_files')->path($filePath);

        if (!file_exists($filePath)) {
            return response()->json(['status' => false, 'message' => 'El archivo no existe en el almacenamiento.'], 404);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        \DB::beginTransaction();

        try {
            // Obtener el código de la dependencia desde la celda B4 y el nombre desde C4
            $codDependencia = $sheet->getCell('B4')->getValue();
            $nombreDependencia = $sheet->getCell('C4')->getValue();

            // Buscar la dependencia en la base de datos
            $dependencia = CalidadOrganigrama::findDependenciaByCodOrganico($codDependencia);
            if (!$dependencia) {
                return response()->json(['status' => false, 'message' => 'La dependencia especificada no existe en el sistema.'], 400);
            }

            // Obtener la versión actual y calcular la nueva versión
            $ultimaVersion = ClasificacionDocumentalTRD::where('dependencia_id', $dependencia->id)->max('version') ?? 0;
            $nuevaVersion = $ultimaVersion + 1;

            $idSerie = null;
            $idSubSerie = null;

            foreach ($data as $index => $row) {
                if ($index < 6) continue; // Saltar filas de encabezado

                // Asegurar que la fila tenga suficientes elementos
                $row = array_pad($row, 14, null);

                [
                    $codDep,
                    $codSerie,
                    $codSubSerie,
                    $nom,
                    $a_g,
                    $a_c,
                    $ct,
                    $e,
                    $m_d,
                    $s,
                    $procedimiento
                ] = $row;

                // **CASO 1: Es una SERIE**
                if (!empty($codSerie) && empty($codSubSerie)) {
                    $serie = ClasificacionDocumentalTRD::create([
                        'tipo' => 'Serie',
                        'cod' => $codSerie,
                        'nom' => $nom,
                        'a_g' => $a_g,
                        'a_c' => $a_c,
                        'ct' => (bool)$ct,
                        'e' => (bool)$e,
                        'm_d' => (bool)$m_d,
                        's' => (bool)$s,
                        'procedimiento' => $procedimiento,
                        'dependencia_id' => $dependencia->id,
                        'user_register' => auth()->id(),
                        'version' => $nuevaVersion,
                        'estado_version' => 'TEMP',
                    ]);
                    $idSerie = $serie->id;
                }

                // **CASO 2: Es una SUBSERIE**
                elseif (!empty($codSerie) && !empty($codSubSerie)) {
                    $subSerie = ClasificacionDocumentalTRD::create([
                        'tipo' => 'SubSerie',
                        'cod' => $codSubSerie,
                        'nom' => $nom,
                        'parent' => $idSerie,
                        'dependencia_id' => $dependencia->id,
                        'user_register' => auth()->id(),
                        'version' => $nuevaVersion,
                        'estado_version' => 'TEMP',
                    ]);
                    $idSubSerie = $subSerie->id;
                }

                // **CASO 3: Es un TIPO DOCUMENTAL**
                elseif (empty($codSerie) && empty($codSubSerie)) {
                    ClasificacionDocumentalTRD::create([
                        'tipo' => 'TipoDocumento',
                        'nom' => $nom,
                        'parent' => $idSubSerie ?? $idSerie,
                        'dependencia_id' => $dependencia->id,
                        'user_register' => auth()->id(),
                        'version' => $nuevaVersion,
                        'estado_version' => 'TEMP',
                    ]);
                }
            }

            \DB::commit();
            return response()->json(['status' => true, 'message' => 'TRD importada satisfactoriamente.'], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Error al importar la TRD.',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            Storage::disk('temp_files')->delete($filePath);
        }
    }


    /**
     * Aprovar el vercionamiento de la TRD
     */
    public function aprobarVersion($dependenciaId)
    {
        // Verificar si la dependencia tiene TRD en estado TEMP
        $trdTemp = ClasificacionDocumentalTRD::where('dependencia_id', $dependenciaId)
            ->where('estado_version', 'TEMP')
            ->exists();

        if (!$trdTemp) {
            return response()->json([
                'status' => false,
                'message' => 'No hay una versión temporal de TRD para esta dependencia.'
            ], 404);
        }

        \DB::beginTransaction();

        try {
            // Mover la versión actual a HISTORICO
            ClasificacionDocumentalTRD::where('dependencia_id', $dependenciaId)
                ->where('estado_version', 'ACTIVO')
                ->update(['estado_version' => 'HISTORICO']);

            // Activar la versión TEMP
            ClasificacionDocumentalTRD::where('dependencia_id', $dependenciaId)
                ->where('estado_version', 'TEMP')
                ->update(['estado_version' => 'ACTIVO']);

            \DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Versión de TRD aprobada exitosamente.'
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Error al aprobar la versión de TRD.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Genarar datos estadisticos
     */
    public function estadistica($id)
    {
        // Obtener todas la TRD (nodos raíz) donde el campo 'tipo' es 'serie' y no tienen un padre
        $trd = ClasificacionDocumentalTRD::whereIn('tipo', ['Serie', 'SubSerie'])
            ->whereNull('parent')
            ->with('children') // Carga los hijos de cada nodo, sin importar su tipo
            ->groupBy('tipo')
            ->count();

        return $trd;
    }

    public function uploadTRD(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:2048',
        ]);

        $filePath = $request->file('file')->storeAs(
            'temp_files',
            'TRD_para_importar_' . now()->timestamp . '.xlsx'
        );

        return response()->json([
            'status' => true,
            'message' => 'Archivo subido correctamente.',
            'path' => $filePath
        ], 201);
    }

    /**
     * Listar TRD por dependencia
     */
    public function listarPorDependencia($id)
    {
        $trd = ClasificacionDocumentalTRD::where('dependencia_id', $id)
            ->whereNull('parent')
            ->with('children')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $trd
        ], 200);
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

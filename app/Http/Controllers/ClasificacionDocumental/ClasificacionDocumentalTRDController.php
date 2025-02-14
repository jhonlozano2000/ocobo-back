<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClasificacionDocumental\ImportarTRDRequest;
use App\Http\Requests\ClasificacionDocumental\UpdateClasificacionDocumentalRequest;
use App\Models\Calidad\CalidadOrganigrama;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRDVersion;
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

    public function importarTRD(ImportarTRDRequest $request)
    {
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
            $dependencia = CalidadOrganigrama::where('cod_organico', $codDependencia)->first();

            if (!$dependencia) {
                return response()->json(['status' => false, 'message' => 'La dependencia especificada no existe en el sistema.'], 400);
            }

            // Verificar si la dependencia ya tiene una versión "TEMP"
            $pendienteAprobacion = ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependencia->id)
                ->where('estado_version', 'TEMP')
                ->exists();

            if ($pendienteAprobacion) {
                return response()->json([
                    'status' => false,
                    'message' => 'La dependencia ya tiene una versión pendiente por aprobar. No se puede crear una nueva.'
                ], 400);
            }

            // Crear una nueva versión en la tabla de versionamiento
            $nuevaVersion = ClasificacionDocumentalTRDVersion::create([
                'dependencia_id' => $dependencia->id,
                'version' => ClasificacionDocumentalTRDVersion::where('dependencia_id', $dependencia->id)->max('version') + 1,
                'estado' => 'TEMP',
                'user_register' => auth()->id(),
            ]);

            $idSerie = null;
            $idSubSerie = null;

            foreach ($data as $index => $row) {
                if ($index < 6) continue; // Saltar filas de encabezado

                [$codDep, $codSerie, $codSubSerie, $nom, $a_g, $a_c, $ct, $e, $m_d, $s, $procedimiento] = $row;

                $ct = $ct ? 1 : 0;
                $e = $e ? 1 : 0;
                $m_d = $m_d ? 1 : 0;
                $s = $s ? 1 : 0;

                if ($codSerie) {
                    $serieModel = ClasificacionDocumentalTRD::create([
                        'tipo' => 'Serie',
                        'cod' => $codSerie,
                        'nom' => $nom,
                        'a_g' => $a_g,
                        'a_c' => $a_c,
                        'ct' => $ct,
                        'e' => $e,
                        'm_d' => $m_d,
                        's' => $s,
                        'procedimiento' => $procedimiento,
                        'dependencia_id' => $dependencia->id,
                        'version_id' => $nuevaVersion->id, // Relacionar con la versión creada
                        'user_register' => auth()->id(),
                    ]);
                    $idSerie = $serieModel->id;
                }

                if ($codSubSerie) {
                    $subSerieModel = ClasificacionDocumentalTRD::create([
                        'tipo' => 'SubSerie',
                        'cod' => $codSubSerie,
                        'nom' => $nom,
                        'parent' => $idSerie,
                        'dependencia_id' => $dependencia->id,
                        'version_id' => $nuevaVersion->id, // Relacionar con la versión creada
                        'user_register' => auth()->id(),
                    ]);
                    $idSubSerie = $subSerieModel->id;
                }

                if ($nom) {
                    ClasificacionDocumentalTRD::create([
                        'tipo' => 'TipoDocumento',
                        'nom' => $nom,
                        'parent' => $idSubSerie ?? $idSerie,
                        'dependencia_id' => $dependencia->id,
                        'version_id' => $nuevaVersion->id, // Relacionar con la versión creada
                        'user_register' => auth()->id(),
                    ]);
                }
            }

            \DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'TRD importada y versión creada correctamente.'
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Error al importar la TRD.',
                'error' => $e->getMessage()
            ], 500);
        } finally {
            // Eliminar el archivo importado
            if (file_exists(storage_path("app/$filePath"))) {
                unlink(storage_path("app/$filePath"));
            }
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
}

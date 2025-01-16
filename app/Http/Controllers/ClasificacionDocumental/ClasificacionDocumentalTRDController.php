<?php

namespace App\Http\Controllers\ClasificacionDocumental;

use App\Http\Controllers\Controller;
use App\Models\ClasificacionDocumental\ClasificacionDocumentalTRD;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use DB;

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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ClasificacionDocumentalTRD $clasificacionDocumentalTRD)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClasificacionDocumentalTRD $clasificacionDocumentalTRD)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClasificacionDocumentalTRD $clasificacionDocumentalTRD)
    {
        //
    }

    public function importTRD(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:2048',
            'dependencia_id' => 'required|exists:calidad_organigrama,id'
        ]);

        $dependenciaId = $request->input('dependencia_id');

        // Verificar si la dependencia ya tiene TRD
        if (ClasificacionDocumentalTRD::where('dependencia_id', $dependenciaId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'La dependencia ya tiene configurada una TRD.'
            ], 400);
        }

        // Crear el nombre del archivo
        $nombreArchivo = 'TRD_para_importar_' . now()->timestamp . '.xlsx';

        // Almacenar el archivo en el directorio `temp_files`
        $filePath = $request->file('file')->storeAs('temp_files', $nombreArchivo);

        // Usar el nombre del archivo para obtener la ruta absoluta
        $file = Storage::disk('temp_files')->path($nombreArchivo);

        if (!file_exists($file)) {
            return response()->json([
                'status' => false,
                'message' => 'El archivo no existe en el almacenamiento.'
            ], 404);
        }

        // Leer el archivo
        $spreadsheet = IOFactory::load($file);
        $data = $spreadsheet->getActiveSheet()->toArray();

        \DB::beginTransaction();

        try {
            $idSerie = null;
            $idSubSerie = null;

            foreach ($data as $index => $row) {
                if ($index == 0) continue; // Saltar la cabecera

                [$codSerie, $codSubSerie, $serie, $subSerie, $tipoDoc, $a_g, $a_c, $ct, $e, $m_d, $s, $procedimiento] = $row;

                if ($codSerie) {
                    $serieModel = ClasificacionDocumentalTRD::create([
                        'tipo' => 'Serie',
                        'cod' => $codSerie,
                        'nom' => $serie,
                        'a_g' => $a_g,
                        'a_c' => $a_c,
                        'ct' => $ct,
                        'e' => $e,
                        'm_d' => $m_d,
                        's' => $s,
                        'procedimiento' => $procedimiento,
                        'dependencia_id' => $dependenciaId,
                        'user_register' => auth()->id(),
                    ]);
                    $idSerie = $serieModel->id;
                }

                if ($codSubSerie) {
                    $subSerieModel = ClasificacionDocumentalTRD::create([
                        'tipo' => 'SubSerie',
                        'cod' => $codSubSerie,
                        'nom' => $subSerie,
                        'parent' => $idSerie,
                        'dependencia_id' => $dependenciaId,
                        'user_register' => auth()->id(),
                    ]);
                    $idSubSerie = $subSerieModel->id;
                }

                if ($tipoDoc) {
                    ClasificacionDocumentalTRD::create([
                        'tipo' => 'TipoDocumento',
                        'nom' => $tipoDoc,
                        'parent' => $idSubSerie ?? $idSerie,
                        'dependencia_id' => $dependenciaId,
                        'user_register' => auth()->id(),
                    ]);
                }
            }

            \DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'TRD importada satisfactoriamente.'
            ], 200);
        } catch (\Exception $e) {
            \DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Error al importar la TRD.',
                'error' => $e->getMessage()
            ], 500);
        } finally {
            // Eliminar el archivo del almacenamiento, independientemente del resultado
            if (Storage::disk('temp_files')->exists($filePath)) {
                Storage::disk('temp_files')->delete($filePath);
            }
        }
    }

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
}

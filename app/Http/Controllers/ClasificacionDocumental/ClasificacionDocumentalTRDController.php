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
        if (Storage::disk('temp_files')->exists('TRD para importa.xlsx')) {

            $dependencia = 3;
            $idSubSerie = null;
            $idSerie = null;

            /**
             * Buscamos la dependencia para saber si ya tiene TRD configurada
             */
            $dependencia = ClasificacionDocumentalTRD::where('dependencia_id', '=', $dependencia)
                ->get();
            if ($dependencia) {
                return response()->json([
                    'status' => false,
                    'message' => 'La depencia ya tiene configurada un TRD'
                ], 205);
            }

            $file = storage_path('app\temp_files\TRD para importa.xlsx');

            $documentos = IOFactory::load($file);
            $cantidad = $documentos->getActiveSheet()->toArray();


            /**
             * Establecer si los tipos documentales van en la serie o subserie
             * */
            $tiposDocumeEnSeri = false;

            $i = 0;

            foreach ($cantidad as $row) {
                if ($i > 0) {
                    $codSerie = $row[0];
                    $codSubSerie = $row[1];
                    $serie = $row[2];
                    $subSerie = $row[3];
                    $tipoDocumental = $row[4];
                    $ag = $row[5];
                    $ac = $row[6];
                    $ct = $row[7];
                    $e = $row[8];
                    $md = $row[9];
                    $s = $row[10];
                    $procedimiento = $row[11];

                    /**
                     * Inserto la serie
                     */
                    if ($codSerie != '') {
                        $trdSerie = new ClasificacionDocumentalTRD();
                        $trdSerie->tipo = 'Serie';
                        $trdSerie->cod = $codSerie;
                        $trdSerie->nom = $serie;
                        $trdSerie->a_g = $ag;
                        $trdSerie->a_c = $ac;
                        $trdSerie->ct = $ct;
                        $trdSerie->e = $e;
                        $trdSerie->m_d = $md;
                        $trdSerie->s = $s;
                        $trdSerie->procedimiento = $procedimiento;
                        $trdSerie->user_register = 1;
                        $trdSerie->dependencia_id = $dependencia;
                        $trdSerie->save();
                        $idSerie = $trdSerie->id;
                    }

                    /**
                     * Inserto la subserie
                     */
                    if ($codSubSerie != '') {
                        $trdSubSerie = new ClasificacionDocumentalTRD();
                        $trdSubSerie->tipo = 'SubSerie';
                        $trdSubSerie->cod = $codSerie;
                        $trdSubSerie->nom = $subSerie;
                        $trdSubSerie->parent = $idSerie;
                        $trdSubSerie->user_register = 1;
                        $trdSubSerie->dependencia_id = $dependencia;
                        $trdSubSerie->save();
                        $idSubSerie = $trdSubSerie->id;
                    }

                    /**
                     * Inserto el tipo documental
                     */
                    if ($tipoDocumental != '') {
                        $trdTipoDocumento = new ClasificacionDocumentalTRD();
                        $trdTipoDocumento->tipo = 'TipoDocumento';
                        $trdTipoDocumento->nom = $tipoDocumental;

                        /**
                         * Cuando la serie y subseries existen el tipo documental va en la subserie
                         * */
                        if ($codSerie != '' and $codSubSerie != '' and $serie != "" and $subSerie != '') {

                            $trdTipoDocumento->parent = $idSubSerie;
                            $tiposDocumeEnSeri = false;
                            /**
                             * Cuando la serie existe pero no la subserie el tipo documental va en la serie
                             * */
                        } elseif ($codSerie != '' and $codSubSerie == '' and $serie != "" and $subSerie == '') {
                            $tiposDocumeEnSeri = true;
                            $trdTipoDocumento->parent = $idSerie;

                            /** Cuando la serie existe pero no la subserie ya anteriormente se inserto un tipo documental en la serie
                             * el tipo documental va en la serie
                             * */
                        } elseif ($codSerie == '' and $codSubSerie == '' and $serie == "" and $subSerie == '' and $tiposDocumeEnSeri) {

                            $trdTipoDocumento->parent = $idSerie;
                        }

                        $trdTipoDocumento->user_register = 1;
                        $trdTipoDocumento->dependencia_id = $dependencia;
                        $trdTipoDocumento->save();
                    }
                }

                $i++;
            }

            return response()->json([
                'satatus' => true,
                'messahe' => 'La TRD se ha cargado satisfactoriamente'
            ], 200);
        } else {
            return response()->json([
                'status' => true,
                'message' => 'El archivo no existe'
            ], 404);
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
}

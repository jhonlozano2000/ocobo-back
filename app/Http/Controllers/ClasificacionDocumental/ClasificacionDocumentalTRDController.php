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
        //
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

    public function importTRD()
    {
        if (Storage::disk('temp_files')->exists('TRD para importa.xlsx')) {

            $file = storage_path('app\temp_files\TRD para importa.xlsx');

            $documentos = IOFactory::load($file);
            $cantidad = $documentos->getActiveSheet()->toArray();

            $dependencia = 3;
            $idSubSerie = null;
            $idSerie = null;

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

            $totalHoja = $documentos->getSheetCount();

            $hojaActual = $documentos->getSheet(0);
            $numeroFilas = $hojaActual->getHighestDataRow();
            $letra = $hojaActual->getHighestColumn();

            $numeroLetra = Coordinate::columnIndexFromString($letra);

            for ($indiceFila = 1; $indiceFila <= $numeroLetra; $indiceFila++) {
                for ($indiceColumna = 1; $indiceColumna <= $numeroLetra; $indiceColumna++)
                    $celda = $indiceColumna . "-" . $indiceFila;
                echo $celda;
                exit();
                $valor = $hojaActual->getCellB($indiceColumna, $indiceFila);
                echo $valor . "<br />";
            }

            /* return response()->json([
                'satatus' => true,
                'data' => $contents,
                'messahe' => 'Procesando archivo'
            ], 200); */
        } else {
            return 'No Existe';
        }
        $file = public_path('storage/app/temp_files/TRD para importa.xlsx');
    }
}

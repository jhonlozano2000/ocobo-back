<?php

namespace App\Http\Controllers\VentanillaUnica;

use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\VentanillaRadicaInternoArchivosEliminados;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentanillaRadicaInternoArchivosEliminadosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $archivos = VentanillaRadicaInternoArchivosEliminados::all();

            return $this->successResponse($archivos, 'Archivos eliminados obtenidos exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener los archivos eliminados', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $archivo = VentanillaRadicaInternoArchivosEliminados::create($request->all());

            DB::commit();

            return $this->successResponse($archivo, 'Archivo eliminado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar el archivo', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $archivo = VentanillaRadicaInternoArchivosEliminados::where('radica_interno_id', $id)->get();

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            return $this->successResponse($archivo, 'Archivo encontrado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el archivo', $e->getMessage(), 500);
        }
    }

    public function historialEliminaciones($id)
    {
        try {
            $archivo = VentanillaRadicaInternoArchivosEliminados::where('radica_interno_id', $id)->get();

            if (!$archivo) {
                return $this->errorResponse('Archivo no encontrado', null, 404);
            }

            return $this->successResponse($archivo, 'Historial de eliminaciones obtenido exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el historial de eliminaciones', $e->getMessage(), 500);
        }
    }
}

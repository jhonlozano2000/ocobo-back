<?php

namespace App\Http\Controllers\Calidad;

use App\Http\Controllers\Controller;
use App\Models\Calidad\CalidadOrganigrama;
use Illuminate\Http\Request;
use \Validator;

class CalidadOrganigramaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Obtener todas las dependencias (nodos raíz) donde el campo 'tipo' es 'dependencia' y no tienen un padre
        $organigrama = CalidadOrganigrama::whereIn('tipo', ['Dependencia', 'Oficina'])
            ->whereNull('parent')
            ->with('children') // Carga los hijos de cada nodo, sin importar su tipo
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $organigrama,
            'message' => 'Organigrama de calidad obtenido correctamente'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'required',
            'nom_organico' => 'required|min:2|max:100',
            'parent' => 'nullable|exists:calidad_organigrama,id', // Valida que el padre exista si se envía
        ], [
            'tipo.requires' => 'Te hizo falta el tipo',
            'nom_organico.required' => 'Te hizo falta el nombre del organismo',
            'nom_organico.min' => 'El nombre del organismo debe tener al menos 2 caracteres',
            'nom_organico.max' => 'El nombre del organismo no puede tener más de 100 caracteres',
            'parent.exists' => 'El padre no existe', // Valida que el padre exista si se envía
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400); // Devuelve un error 400 (Bad Request) con los errores
        }

        // Validación adicional para el tipo de nodo padre
        if ($request->parent) {
            // Obtener el tipo del nodo padre
            $parentNode = CalidadOrganigrama::find($request->parent);

            // Restricciones según el tipo de nodo padre
            if ($parentNode->tipo === 'Cargo') {
                if ($request->tipo !== 'Cargo') {
                    // No se permite agregar dependencias u oficinas a un cargo
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se pueden agregar dependencias u oficinas a un cargo.',
                    ], 400);
                } else {
                    // No se permite agregar otro cargo a un cargo
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se pueden agregar cargos a un cargo existente.',
                    ], 400);
                }
            }

            if ($parentNode->tipo === 'Oficina') {
                if ($request->tipo === 'Dependencia') {
                    // No se permite agregar una dependencia a una oficina
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se pueden agregar dependencias a una oficina.',
                    ], 400);
                } elseif ($request->tipo === 'Oficina') {
                    // No se permite agregar otra oficina a una oficina
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se pueden agregar oficinas a una oficina existente.',
                    ], 400);
                }
            }
        }

        $organigrama = CalidadOrganigrama::create([
            'tipo' => $request->tipo,
            'cod_organico' => $request->cod_organico,
            'nom_organico' => $request->nom_organico,
            'observaciones' => $request->observaciones,
            'parent' => $request->parent
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $organigrama,
            'message' => 'Organismo creado correctamente'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Int $id)
    {
        $organigrama = CalidadOrganigrama::find($id);

        if (!$organigrama) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organismo no encontrado'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $organigrama
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Buscar el nodo por ID
        $nodo = CalidadOrganigrama::find($id);
        if (!$nodo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nodo no encontrado'
            ], 404);
        }

        // Validación de los datos
        $validator = Validator::make($request->all(), [
            'tipo' => 'required',
            'nom_organico' => 'required|min:2|max:100',
            'parent' => 'nullable|exists:calidad_organigrama,id',
        ], [
            'tipo.required' => 'Te hizo falta el tipo',
            'nom_organico.required' => 'Te hizo falta el nombre del organismo',
            'nom_organico.min' => 'El nombre del organismo debe tener al menos 2 caracteres',
            'nom_organico.max' => 'El nombre del organismo no puede tener más de 100 caracteres',
            'parent.exists' => 'El nodo padre no existe',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Actualizar el nodo con los datos del request
        $nodo->update($request->only(['tipo', 'nom_organico', 'cod_organico', 'observaciones', 'parent']));

        return response()->json([
            'status' => 'success',
            'data' => $nodo,
            'message' => 'Nodo actualizado con éxito',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Int $id)
    {
        try {
            $organigrama = CalidadOrganigrama::find($id);

            if (!$organigrama) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Organismo no encontrado'
                ], 404);
            }

            $organigrama->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Organismo eliminado correctamente'
            ]);
        } catch (\Exception $e) {

            // Código de error para restricción de clave foránea
            if ($e->getCode() == 23000) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El nodo no se puede eliminar porque contiene hijos.'
                ], 400); // Puedes usar el código de estado 400 (Bad Request) u otro apropiado
            }

            return response()->json([
                'error' => $e->getMessage(),
                'status' => 'error',
                'message' => 'Ha ocurrido un error al eliminar el nodo'
            ], 500);
        }
    }
}

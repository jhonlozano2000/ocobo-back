<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\StoreNotaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempNota;
use Illuminate\Support\Facades\Auth;

class MiBandejaTempNotaController extends Controller
{
    use ApiResponseTrait;

    public function index($grupoId)
    {
        try {
            $grupo = MiBandejaTemp::find($grupoId);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $notas = $grupo->notas()->with('user')->orderBy('created_at', 'asc')->get();

            return $this->successResponse($notas, 'Notas del grupo');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener notas', $e->getMessage(), 500);
        }
    }

    public function store(StoreNotaRequest $request, $grupoId)
    {
        try {
            $grupo = MiBandejaTemp::find($grupoId);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $nota = MiBandejaTempNota::create([
                'grupo_id' => $grupoId,
                'user_id' => Auth::id(),
                'contenido' => $request->contenido,
            ]);

            $nota->load('user');

            return $this->successResponse($nota, 'Nota creada exitosamente', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear nota', $e->getMessage(), 500);
        }
    }
}

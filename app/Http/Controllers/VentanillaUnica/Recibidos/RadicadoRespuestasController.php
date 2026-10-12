<?php

namespace App\Http\Controllers\VentanillaUnica\Recibidos;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AccesoRadicado;
use App\Http\Traits\ApiResponseTrait;
use App\Models\VentanillaUnica\Recibidos\RadicadoRespuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RadicadoRespuestasController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request, int $radicadoId)
    {
        $respuestas = RadicadoRespuesta::where('radicado_id', $radicadoId)
            ->with(['usuarioCrea', 'usuarioActualiza'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($respuestas, 'Respuestas obtenidas');
    }

    public function show(int $id)
    {
        $respuesta = RadicadoRespuesta::with([
            'radicado',
            'usuarioCrea',
            'usuarioActualiza',
        ])->findOrFail($id);

        // M01-10: la ruta va por id de respuesta, así que el ABAC del radicado se valida aquí
        if (! AccesoRadicado::permitido(Auth::user(), 'recibido', $respuesta->radicado_id)) {
            abort(404, 'Respuesta no encontrada');
        }

        return $this->successResponse($respuesta, 'Respuesta obtenida');
    }

    public function store(Request $request, int $radicadoId)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'nullable|string|max:500',
            'contenido' => 'nullable|string',
            'contenido_json' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $respuesta = RadicadoRespuesta::create([
            'radicado_id' => $radicadoId,
            'titulo' => $request->titulo,
            'contenido' => $request->contenido,
            'contenido_json' => $request->contenido_json,
            'user_crea_id' => Auth::id(),
        ]);

        return $this->successResponse($respuesta, 'Respuesta creada');
    }

    public function update(Request $request, int $id)
    {
        $respuesta = RadicadoRespuesta::findOrFail($id);

        if (! AccesoRadicado::permitido(Auth::user(), 'recibido', $respuesta->radicado_id)) {
            abort(404, 'Respuesta no encontrada');
        }

        // M01-10: igual que al eliminar, solo el autor modifica su respuesta
        if ((int) $respuesta->user_crea_id !== (int) Auth::id()) {
            return $this->errorResponse('No puedes editar esta respuesta', null, 403);
        }

        $respuesta->update([
            'titulo' => $request->titulo ?? $respuesta->titulo,
            'contenido' => $request->contenido ?? $respuesta->contenido,
            'contenido_json' => $request->contenido_json ?? $respuesta->contenido_json,
            'user_actualiza_id' => Auth::id(),
        ]);

        return $this->successResponse($respuesta, 'Respuesta actualizada');
    }

    public function destruir(int $id)
    {
        $respuesta = RadicadoRespuesta::findOrFail($id);

        if ((int) $respuesta->user_crea_id !== (int) Auth::id()) {
            return $this->errorResponse('No puedes eliminar esta respuesta', null, 403);
        }

        $respuesta->delete();

        return $this->successResponse(null, 'Respuesta eliminada');
    }
}

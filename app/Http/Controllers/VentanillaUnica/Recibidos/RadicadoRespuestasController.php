<?php

namespace App\Http\Controllers\VentanillaUnica\Recibidos;

use App\Events\VentanillaUnica\RespuestaEditing;
use App\Http\Controllers\Controller;
use App\Models\VentanillaUnica\Recibidos\RadicadoRespuesta;
use App\Models\VentanillaUnica\Recibidos\RadicadoRespuestaParticipante;
use App\Models\VentanillaUnica\Recibidos\RadicadoRespuestaVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RadicadoRespuestasController extends Controller
{
    public function index(Request $request, int $radicadoId)
    {
        $respuestas = RadicadoRespuesta::where('radicado_id', $radicadoId)
            ->with(['usuarioCrea', 'usuarioEditando', 'participantes.usuario'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($respuestas, 'Respuestas obtenidas');
    }

    public function show(int $id)
    {
        $respuesta = RadicadoRespuesta::with([
            'radicado',
            'usuarioCrea',
            'usuarioEditando',
            'participantes.usuario',
            'versiones' => fn($q) => $q->orderBy('version', 'desc')->limit(10)
        ])->findOrFail($id);

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
            'version' => 1,
            'version_actual' => 1,
            'estado' => 'borrador',
            'user_crea_id' => Auth::id(),
        ]);

        RadicadoRespuestaParticipante::create([
            'respuesta_id' => $respuesta->id,
            'user_id' => Auth::id(),
            'rol' => 'editor',
            'puede_editar' => true,
        ]);

        broadcast(new RespuestaEditing($respuesta, 'respuesta_creada', [
            'user_id' => Auth::id(),
            'user_nombre' => Auth::user()->name,
        ]))->toOthers();

        return $this->successResponse($respuesta, 'Respuesta creada');
    }

    public function update(Request $request, int $id)
    {
        $respuesta = RadicadoRespuesta::findOrFail($id);

        if (!$respuesta->puedeEditar()) {
            return $this->errorResponse('No puedes editar esta respuesta', null, 403);
        }

        $respuesta->update([
            'titulo' => $request->titulo ?? $respuesta->titulo,
            'contenido' => $request->contenido ?? $respuesta->contenido,
            'contenido_json' => $request->contenido_json ?? $respuesta->contenido_json,
            'user_actualiza_id' => Auth::id(),
        ]);

        if ($request->has('contenido') || $request->has('contenido_json')) {
            broadcast(new RespuestaEditing($respuesta, 'contenido_actualizado', [
                'user_id' => Auth::id(),
                'user_nombre' => Auth::user()->name,
            ]))->toOthers();
        }

        return $this->successResponse($respuesta, 'Respuesta actualizada');
    }

    public function adquirirLock(int $id)
    {
        $respuesta = RadicadoRespuesta::findOrFail($id);

        if (!$respuesta->adquirirLock()) {
            return $this->errorResponse('No se pudo adquirir el lock', null, 423);
        }

        $respuesta->refresh();

        broadcast(new RespuestaEditing($respuesta, 'lock_adquirido', [
            'user_id' => Auth::id(),
            'user_nombre' => Auth::user()->name,
        ]))->toOthers();

        return $this->successResponse($respuesta, 'Lock adquirido');
    }

    public function liberarLock(int $id)
    {
        $respuesta = RadicadoRespuesta::findOrFail($id);

        if ($respuesta->user_editando_id !== Auth::id()) {
            return $this->errorResponse('No tienes el lock', null, 403);
        }

        $respuesta->liberarLock();
        $respuesta->refresh();

        broadcast(new RespuestaEditing($respuesta, 'lock_liberado', [
            'user_id' => Auth::id(),
        ]))->toOthers();

        return $this->successResponse($respuesta, 'Lock liberado');
    }

    public function guardarVersion(Request $request, int $id)
    {
        $respuesta = RadicadoRespuesta::findOrFail($id);

        if (!$respuesta->puedeEditar()) {
            return $this->errorResponse('No puedes guardar versión', null, 403);
        }

        $version = $respuesta->guardarVersion($request->cambios_resumen);

        $respuesta->update([
            'version_actual' => $respuesta->version_actual + 1,
        ]);

        return $this->successResponse($version, 'Versión guardada');
    }

    public function destruir(Request $id)
    {
        $respuesta = RadicadoRespuesta::findOrFail($id);

        if ($respuesta->user_crea_id !== Auth::id()) {
            return $this->errorResponse('No puedes eliminar esta respuesta', null, 403);
        }

        $respuesta->delete();

        return $this->successResponse(null, 'Respuesta eliminada');
    }
}
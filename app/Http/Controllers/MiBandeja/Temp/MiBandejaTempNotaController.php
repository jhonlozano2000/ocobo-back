<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Events\MiBandeja\Grupos\NotaCreada;
use App\Events\MiBandeja\Grupos\NotaTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\MiBandeja\StoreNotaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempNota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\MiBandeja\Concerns\AutorizaGrupoColaborativo;

class MiBandejaTempNotaController extends Controller
{
    use ApiResponseTrait;
    use AutorizaGrupoColaborativo;

    public function index($grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $notas = $grupo->notas()->with('user')->orderBy('created_at', 'asc')->get();

            return $this->successResponse($notas, 'Notas del grupo');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener notas', $e->getMessage(), 500);
        }
    }

    public function store(StoreNotaRequest $request, $grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (!$grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $nota = MiBandejaTempNota::create([
                'grupo_id' => $grupoId,
                'user_id' => Auth::id(),
                'contenido' => $request->contenido,
            ]);

            $nota->load('user');

            try {
                event(new NotaCreada($nota));
            } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
                \Illuminate\Support\Facades\Log::warning('NotaCreada broadcast failed', [
                    'nota_id' => $nota->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->successResponse($nota, 'Nota creada exitosamente', 201);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al crear nota', $e->getMessage(), 500);
        }
    }

    public function typing(Request $request, $grupoId)
    {
        try {
            $request->validate([
                'typing' => 'required|boolean',
            ]);

            $user = Auth::user();
            $userName = trim(($user->nombres ?? '') . ' ' . ($user->apellidos ?? ''));
            $userAvatar = $user->avatar ?? null;

            try {
                event(new NotaTyping(
                    (int) $grupoId,
                    $user->id,
                    $userName,
                    $userAvatar,
                    (bool) $request->typing
                ));
            } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
                \Illuminate\Support\Facades\Log::warning('NotaTyping broadcast failed', [
                    'grupo_id' => $grupoId,
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->successResponse(null, 'Typing signal sent');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al enviar señal de escritura', $e->getMessage(), 500);
        }
    }
}

<?php

namespace App\Http\Controllers\MiBandeja\Temp;

use App\Events\MiBandeja\Grupos\NotaCreada;
use App\Events\MiBandeja\Grupos\NotasLeidas;
use App\Events\MiBandeja\Grupos\NotaTyping;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MiBandeja\Concerns\AutorizaGrupoColaborativo;
use App\Http\Requests\MiBandeja\StoreNotaRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MiBandeja\MiBandejaTemp;
use App\Models\MiBandeja\MiBandejaTempNota;
use App\Models\MiBandeja\MiBandejaTempNotaLectura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MiBandejaTempNotaController extends Controller
{
    use ApiResponseTrait;
    use AutorizaGrupoColaborativo;

    /** Del autor solo lo que muestra el chat: nunca documento, correo, teléfono, dirección ni firma */
    private const AUTOR = 'user:id,nombres,apellidos,avatar';

    public function index($grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $this->marcarLeidas($grupo);

            $notas = $grupo->notas()->with(self::AUTOR)->orderBy('created_at', 'asc')->get();
            $this->conLeida($grupo, $notas);

            return $this->successResponse($notas, 'Notas del grupo');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al obtener notas', $e->getMessage(), 500);
        }
    }

    public function store(StoreNotaRequest $request, $grupoId)
    {
        try {
            $grupo = $this->autorizarGrupo($grupoId);

            if (! $grupo) {
                return $this->errorResponse('Grupo no encontrado', null, 404);
            }

            $nota = MiBandejaTempNota::create([
                'grupo_id' => $grupoId,
                'user_id' => Auth::id(),
                'contenido' => $request->contenido,
            ]);

            $nota->load(self::AUTOR);
            $nota->setAttribute('leida', false);

            try {
                event(new NotaCreada($nota));
            } catch (\Exception $e) {
                if ($e instanceof ValidationException) {
                    throw $e;
                }
                if ($e instanceof HttpExceptionInterface) {
                    throw $e;
                }
                Log::warning('NotaCreada broadcast failed', [
                    'nota_id' => $nota->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->successResponse($nota, 'Nota creada exitosamente', 201);
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al crear nota', $e->getMessage(), 500);
        }
    }

    /**
     * El chat está abierto y llegó una nota: el usuario la leyó.
     */
    public function leidas($grupoId)
    {
        $grupo = $this->autorizarGrupo($grupoId);
        $this->marcarLeidas($grupo);

        return $this->successResponse(null, 'Notas leídas');
    }

    /**
     * Registra la lectura del usuario y, si tenía notas ajenas sin leer, lo avisa por el canal del grupo
     * (solo entonces: así los demás clientes, que recargan al recibir el aviso, no lo repiten en bucle).
     */
    private function marcarLeidas(MiBandejaTemp $grupo): void
    {
        $userId = (int) Auth::id();
        $previa = MiBandejaTempNotaLectura::where('grupo_id', $grupo->id)->where('user_id', $userId)->value('ultima_lectura_at');
        $habiaSinLeer = $grupo->notas()
            ->where('user_id', '!=', $userId)
            // Misma regla que conLeida: leída si la lectura es igual o posterior a la nota
            ->when($previa, fn ($q) => $q->where('created_at', '>', $previa))
            ->exists();
        $ahora = now();

        MiBandejaTempNotaLectura::updateOrCreate(
            ['grupo_id' => $grupo->id, 'user_id' => $userId],
            ['ultima_lectura_at' => $ahora]
        );

        if (! $habiaSinLeer) {
            return;
        }

        try {
            event(new NotasLeidas((int) $grupo->id, $userId, $ahora->toISOString()));
        } catch (\Exception $e) {
            Log::warning('NotasLeidas broadcast failed', ['grupo_id' => $grupo->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * `leida`: todos los demás miembros del grupo (creador incluido) ya la leyeron.
     */
    private function conLeida(MiBandejaTemp $grupo, $notas): void
    {
        $miembros = collect([(int) $grupo->usua_crea_id])
            ->merge(collect(['revisores', 'firmantes', 'proyectores', 'aprobadores'])
                ->flatMap(fn ($rol) => $grupo->{$rol}->pluck('user_id')))
            ->map(fn ($id) => (int) $id)
            ->unique();
        $lecturas = MiBandejaTempNotaLectura::where('grupo_id', $grupo->id)->pluck('ultima_lectura_at', 'user_id');

        $notas->each(function ($nota) use ($miembros, $lecturas) {
            $otros = $miembros->reject(fn ($id) => $id === (int) $nota->user_id);
            $nota->setAttribute('leida', $otros->isNotEmpty() && $otros->every(
                fn ($id) => isset($lecturas[$id]) && $lecturas[$id]->gte($nota->created_at)
            ));
        });
    }

    public function typing(Request $request, $grupoId)
    {
        // Solo los miembros pueden anunciar que escriben en el canal del grupo
        $this->autorizarGrupo($grupoId);

        try {
            $request->validate([
                'typing' => 'required|boolean',
            ]);

            $user = Auth::user();
            $userName = trim(($user->nombres ?? '').' '.($user->apellidos ?? ''));
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
                if ($e instanceof ValidationException) {
                    throw $e;
                }
                if ($e instanceof HttpExceptionInterface) {
                    throw $e;
                }
                Log::warning('NotaTyping broadcast failed', [
                    'grupo_id' => $grupoId,
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->successResponse(null, 'Typing signal sent');
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            return $this->errorResponse('Error al enviar señal de escritura', $e->getMessage(), 500);
        }
    }
}

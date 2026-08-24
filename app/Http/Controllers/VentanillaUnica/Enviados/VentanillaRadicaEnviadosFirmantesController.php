<?php

namespace App\Http\Controllers\VentanillaUnica\Enviados;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventanilla\Enviados\StoreFirmanteEnviadoRequest;
use App\Http\Requests\Ventanilla\Enviados\UpdateFirmanteEnviadoRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Notificacion;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviadosFirmas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentanillaRadicaEnviadosFirmantesController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Radicar -> Cores. Enviada -> Editar');
    }

    public function index(Request $request)
    {
        try {
            $query = VentanillaRadicaEnviadosFirmas::with(['userCargo.user', 'userCargo.cargo', 'radicado']);

            if ($request->filled('radica_enviado_id')) {
                $query->where('radica_enviado_id', $request->radica_enviado_id);
            }

            if ($request->filled('user_id')) {
                $query->whereHas('userCargo', fn ($q) => $q->where('user_id', $request->user_id));
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page');
            $firmas = $perPage ? $query->paginate($perPage) : $query->get();

            return $this->successResponse($firmas, 'Listado de firmantes obtenido exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener el listado de firmantes', $e->getMessage(), 500);
        }
    }

    public function store(StoreFirmanteEnviadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $firmasData = $request->validated()['firmas'] ?? [];

            if (empty($firmasData)) {
                return $this->errorResponse('Se debe enviar un array de firmantes no vacío', null, 400);
            }

            $firmasCreadas = [];

            foreach ($firmasData as $item) {
                $radicaEnviadoId = $item['radica_enviado_id'] ?? $request->route('radica_enviado_id');
                if (! $radicaEnviadoId) {
                    return $this->errorResponse('Cada firmante debe incluir radica_enviado_id', null, 400);
                }

                $firma = VentanillaRadicaEnviadosFirmas::create([
                    'radica_enviado_id' => (int) $radicaEnviadoId,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                ]);
                $firmasCreadas[] = $firma->load(['userCargo.user', 'userCargo.cargo', 'radicado']);

                // Crear notificación in-app para el firmante
                $userCargo = \App\Models\ControlAcceso\UserCargo::find($item['users_cargos_id']);
                if ($userCargo && $userCargo->user) {
                    $radicado = VentanillaRadicaEnviados::find($radicaEnviadoId);
                    Notificacion::create([
                        'user_id' => $userCargo->user_id,
                        'type' => 'asignacion_firmante',
                        'title' => 'Firmante asignado',
                        'message' => $radicado
                            ? 'Se le ha asignado como firmante del radicado ' . $radicado->num_radicado . '.'
                            : 'Se le ha asignado como firmante de un radicado.',
                        'notifiable_type' => 'App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados',
                        'notifiable_id' => $radicaEnviadoId,
                        'data' => [
                            'radica_enviado_id' => $radicaEnviadoId,
                            'num_radicado' => $radicado?->num_radicado,
                            'asignado_por' => auth()->id(),
                        ],
                    ]);
                }
            }

            DB::commit();

            return $this->successResponse($firmasCreadas, 'Firmantes asignados exitosamente', 201);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            DB::rollBack();

            return $this->errorResponse('Error al asignar firmantes', $e->getMessage(), 500);
        }
    }

    public function assignToRadicado($radica_enviado_id, StoreFirmanteEnviadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $firmasData = $request->validated()['firmas'] ?? [];

            if (empty($firmasData)) {
                return $this->errorResponse('Se debe enviar un array de firmantes no vacío', null, 400);
            }

            $firmasCreadas = [];

            foreach ($firmasData as $item) {
                $firma = VentanillaRadicaEnviadosFirmas::create([
                    'radica_enviado_id' => (int) $radica_enviado_id,
                    'users_cargos_id' => (int) $item['users_cargos_id'],
                ]);
                $firmasCreadas[] = $firma->load(['userCargo.user', 'userCargo.cargo', 'radicado']);

                // Crear notificación in-app para el firmante
                $userCargo = \App\Models\ControlAcceso\UserCargo::find($item['users_cargos_id']);
                if ($userCargo && $userCargo->user) {
                    $radicado = VentanillaRadicaEnviados::find($radica_enviado_id);
                    Notificacion::create([
                        'user_id' => $userCargo->user_id,
                        'type' => 'asignacion_firmante',
                        'title' => 'Firmante asignado',
                        'message' => $radicado
                            ? 'Se le ha asignado como firmante del radicado ' . $radicado->num_radicado . '.'
                            : 'Se le ha asignado como firmante de un radicado.',
                        'notifiable_type' => 'App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados',
                        'notifiable_id' => $radica_enviado_id,
                        'data' => [
                            'radica_enviado_id' => $radica_enviado_id,
                            'num_radicado' => $radicado?->num_radicado,
                            'asignado_por' => auth()->id(),
                        ],
                    ]);
                }
            }

            DB::commit();

            return $this->successResponse($firmasCreadas, 'Firmantes asignados exitosamente', 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            return $this->errorResponse('Error de validación', $e->errors(), 422);
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            DB::rollBack();

            return $this->errorResponse('Error al asignar firmantes', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $firma = VentanillaRadicaEnviadosFirmas::with(['userCargo.user', 'userCargo.cargo', 'radicado'])->find($id);

            if (! $firma) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            return $this->successResponse($firma, 'Firmante encontrado exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener el firmante', $e->getMessage(), 500);
        }
    }

    public function update($id, UpdateFirmanteEnviadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $firma = VentanillaRadicaEnviadosFirmas::find($id);

            if (! $firma) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $updateData = $request->only(['radica_enviado_id', 'users_cargos_id']);
            $updateData = array_filter($updateData, fn ($v) => $v !== null && $v !== '');

            if (! empty($updateData)) {
                $firma->update($updateData);
            }

            DB::commit();

            return $this->successResponse(
                $firma->fresh(['userCargo.user', 'userCargo.cargo', 'radicado']),
                'Firmante actualizado exitosamente'
            );
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            DB::rollBack();

            return $this->errorResponse('Error al actualizar el firmante', $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $firma = VentanillaRadicaEnviadosFirmas::find($id);

            if (! $firma) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $firma->delete();

            DB::commit();

            return $this->successResponse(null, 'Firmante eliminado exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            DB::rollBack();

            return $this->errorResponse('Error al eliminar el firmante', $e->getMessage(), 500);
        }
    }

    public function getByRadicado($radica_enviado_id)
    {
        try {
            $firmas = VentanillaRadicaEnviadosFirmas::with(['userCargo.user', 'userCargo.cargo'])
                ->where('radica_enviado_id', $radica_enviado_id)
                ->orderBy('created_at', 'desc')
                ->get();

            $firmasData = $firmas->map(function ($f) {
                $user = $f->userCargo?->user;
                $cargo = $f->userCargo?->cargo;

                return [
                    'id' => $f->id,
                    'usuario' => $user ? ['id' => $user->id, 'nombres' => $user->nombres, 'apellidos' => $user->apellidos] : null,
                    'cargo' => $cargo ? ['id' => $cargo->id, 'nombre' => $cargo->nom_organico] : null,
                ];
            });

            return $this->successResponse($firmasData, 'Firmantes del radicado obtenidos exitosamente');
        } catch (\Exception $e) {
        if ($e instanceof \Illuminate\Validation\ValidationException) { throw $e; }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) { throw $e; }
            return $this->errorResponse('Error al obtener los firmantes', $e->getMessage(), 500);
        }
    }
}

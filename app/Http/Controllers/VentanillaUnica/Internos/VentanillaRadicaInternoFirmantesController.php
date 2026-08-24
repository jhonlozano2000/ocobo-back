<?php

namespace App\Http\Controllers\VentanillaUnica\Internos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Notificacion;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInternoFirmantes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentanillaRadicaInternoFirmantesController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('can:Radicar -> Cores. Interna -> Editar');
    }

    public function index(Request $request)
    {
        try {
            $query = VentanillaRadicaInternoFirmantes::with(['user', 'radicado']);

            if ($request->filled('radica_interno_id')) {
                $query->where('radica_interno_id', $request->radica_interno_id);
            }

            if ($request->filled('users_id')) {
                $query->where('users_id', $request->users_id);
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

    public function store(Request $request)
    {
        try {
            $request->validate([
                'firmantes' => 'required|array|min:1',
                'firmantes.*' => 'required|integer|exists:users,id',
            ], [
                'firmantes.required' => 'Se debe enviar un array de firmantes.',
                'firmantes.array' => 'Los firmantes deben ser un array.',
                'firmantes.min' => 'Debe asignar al menos un firmante.',
                'firmantes.*.exists' => 'El firmante no existe.',
            ]);

            DB::beginTransaction();

            $firmasCreadas = [];

            foreach ($request->firmantes as $userId) {
                $firma = VentanillaRadicaInternoFirmantes::create([
                    'radica_interno_id' => $request->radica_interno_id,
                    'users_id' => $userId,
                ]);
                $firmasCreadas[] = $firma->load(['user']);

                $user = \App\Models\User::find($userId);
                if ($user) {
                    $radicado = VentanillaRadicaInterno::find($request->radica_interno_id);
                    Notificacion::create([
                        'user_id' => $userId,
                        'type' => 'asignacion_firmante',
                        'title' => 'Firmante asignado',
                        'message' => $radicado
                            ? 'Se le ha asignado como firmante del radicado interno ' . $radicado->num_radicado . '.'
                            : 'Se le ha asignado como firmante de un radicado interno.',
                        'notifiable_type' => 'App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno',
                        'notifiable_id' => $request->radica_interno_id,
                        'data' => [
                            'radica_interno_id' => $request->radica_interno_id,
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

    public function assignToRadicado($radica_interno_id, Request $request)
    {
        try {
            $request->validate([
                'firmantes' => 'required|array|min:1',
                'firmantes.*' => 'required|integer|exists:users,id',
            ], [
                'firmantes.required' => 'Se debe enviar un array de firmantes.',
                'firmantes.*.exists' => 'El firmante no existe.',
            ]);

            DB::beginTransaction();

            $firmasCreadas = [];

            foreach ($request->firmantes as $userId) {
                $firma = VentanillaRadicaInternoFirmantes::create([
                    'radica_interno_id' => (int) $radica_interno_id,
                    'users_id' => $userId,
                ]);
                $firmasCreadas[] = $firma->load(['user']);

                $user = \App\Models\User::find($userId);
                if ($user) {
                    $radicado = VentanillaRadicaInterno::find($radica_interno_id);
                    Notificacion::create([
                        'user_id' => $userId,
                        'type' => 'asignacion_firmante',
                        'title' => 'Firmante asignado',
                        'message' => $radicado
                            ? 'Se le ha asignado como firmante del radicado interno ' . $radicado->num_radicado . '.'
                            : 'Se le ha asignado como firmante de un radicado interno.',
                        'notifiable_type' => 'App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno',
                        'notifiable_id' => $radica_interno_id,
                        'data' => [
                            'radica_interno_id' => $radica_interno_id,
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
            $firma = VentanillaRadicaInternoFirmantes::with(['user', 'radicado'])->find($id);

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

    public function update($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $firma = VentanillaRadicaInternoFirmantes::find($id);

            if (! $firma) {
                return $this->errorResponse('Firmante no encontrado', null, 404);
            }

            $request->validate([
                'users_id' => 'required|integer|exists:users,id',
            ]);

            $firma->update(['users_id' => $request->users_id]);
            DB::commit();

            return $this->successResponse(
                $firma->fresh(['user']),
                'Firmante actualizado exitosamente'
            );
        } catch (ValidationException $e) {
            DB::rollBack();

            return $this->errorResponse('Error de validación', $e->errors(), 422);
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

            $firma = VentanillaRadicaInternoFirmantes::find($id);

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

    public function getByRadicado($radica_interno_id)
    {
        try {
            $firmas = VentanillaRadicaInternoFirmantes::with(['user'])
                ->where('radica_interno_id', $radica_interno_id)
                ->orderBy('created_at', 'desc')
                ->get();

            $firmasData = $firmas->map(function ($f) {
                $user = $f->user;

                return [
                    'id' => $f->id,
                    'usuario' => $user ? ['id' => $user->id, 'nombres' => $user->nombres, 'apellidos' => $user->apellidos] : null,
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

<?php

namespace App\Http\Controllers\Transversal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Transversal\InAppNotificationResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InAppNotificationController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'type' => 'nullable|string',
            'unread_only' => 'nullable|boolean',
        ]);

        $query = Notificacion::forUser($request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $perPage = $request->input('per_page', 20);
        $notifications = InAppNotificationResource::collection(
            $query->paginate($perPage)
        );

        return $this->successResponse($notifications, 'Notificaciones obtenidas exitosamente');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notificacion::forUser($request->user()->id)->unread()->count();

        return $this->successResponse(['count' => $count], 'Conteo de notificaciones no leídas');
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $notification = Notificacion::forUser($request->user()->id)->findOrFail($id);
        $notification->markAsRead();

        return $this->successResponse(new InAppNotificationResource($notification), 'Notificación marcada como leída');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = Notificacion::forUser($request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return $this->successResponse(['marked' => $updated], 'Todas las notificaciones marcadas como leídas');
    }
}

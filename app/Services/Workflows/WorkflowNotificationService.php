<?php

namespace App\Services\Workflows;

use App\Events\NotificationPushed;
use App\Models\ControlAcceso\UserNotificationSetting;
use App\Models\Notificacion;
use App\Models\User;
use App\Models\Workflows\Tarea;
use App\Models\Workflows\WorkflowInstancia;
use App\Models\Workflows\WorkFlowTarea;

class WorkflowNotificationService
{
    public function notificarTareaAsignadaSistemaA(Tarea $tarea, int $userId): void
    {
        $tarea->loadMissing('workflow:id,nombre');

        $notificacion = Notificacion::create([
            'user_id' => $userId,
            'type' => 'tarea.asignada',
            'title' => 'Nueva tarea asignada',
            'message' => "Se te ha asignado la tarea \"{$tarea->nombre}\" en el workflow \"{$tarea->workflow->nombre}\"",
            'notifiable_type' => Tarea::class,
            'notifiable_id' => $tarea->id,
            'data' => [
                'tarea_id' => $tarea->id,
                'workflow_id' => $tarea->workflow_id,
                'url' => "/workflows/{$tarea->workflow_id}/tareas",
            ],
        ]);

        $this->dispatch($notificacion);
    }

    public function notificarTareaAsignadaSistemaB(WorkFlowTarea $tarea, int $userId): void
    {
        $tarea->loadMissing('nodo.workflow:id,nombre');

        $notificacion = Notificacion::create([
            'user_id' => $userId,
            'type' => 'tarea.asignada',
            'title' => 'Nueva tarea asignada',
            'message' => "Se te ha asignado la tarea \"{$tarea->titulo}\" en el workflow \"{$tarea->nodo->workflow->nombre}\"",
            'notifiable_type' => WorkFlowTarea::class,
            'notifiable_id' => $tarea->id,
            'data' => [
                'tarea_id' => $tarea->id,
                'nodo_id' => $tarea->nodo_id,
                'workflow_id' => $tarea->nodo->workflow_id,
                'url' => "/workflows/{$tarea->nodo->workflow_id}",
            ],
        ]);

        $this->dispatch($notificacion);
    }

    public function notificarTareaCompletada(Tarea $tarea, User $completadoPor): void
    {
        $tarea->loadMissing('workflow:id,nombre');
        $usuarios = $tarea->propietarios()
            ->where('user_id', '!=', $completadoPor->id)
            ->get();

        foreach ($usuarios as $usuario) {
            $notificacion = Notificacion::create([
                'user_id' => $usuario->id,
                'type' => 'tarea.completada',
                'title' => 'Tarea completada',
                'message' => "La tarea \"{$tarea->nombre}\" fue completada por {$completadoPor->nombres}",
                'notifiable_type' => Tarea::class,
                'notifiable_id' => $tarea->id,
                'data' => [
                    'tarea_id' => $tarea->id,
                    'workflow_id' => $tarea->workflow_id,
                    'completado_por' => $completadoPor->id,
                    'url' => "/workflows/{$tarea->workflow_id}/tareas",
                ],
            ]);

            $this->dispatch($notificacion);
        }
    }

    public function notificarTareaVencida(Tarea $tarea): void
    {
        $tarea->loadMissing('workflow:id,nombre');
        $usuarios = $tarea->responsables;

        foreach ($usuarios as $usuario) {
            $notificacion = Notificacion::create([
                'user_id' => $usuario->id,
                'type' => 'tarea.vencida',
                'title' => 'Tarea vencida',
                'message' => "La tarea \"{$tarea->nombre}\" ha vencido en el workflow \"{$tarea->workflow->nombre}\"",
                'notifiable_type' => Tarea::class,
                'notifiable_id' => $tarea->id,
                'data' => [
                    'tarea_id' => $tarea->id,
                    'workflow_id' => $tarea->workflow_id,
                    'url' => "/workflows/{$tarea->workflow_id}/tareas",
                ],
            ]);

            $this->dispatch($notificacion);
        }
    }

    public function notificarWorkFlowTareaVencida(WorkFlowTarea $tarea): void
    {
        if (! $tarea->responsable_usuario_id) {
            return;
        }

        $tarea->loadMissing('nodo.workflow:id,nombre');

        $notificacion = Notificacion::create([
            'user_id' => $tarea->responsable_usuario_id,
            'type' => 'tarea.vencida',
            'title' => 'Tarea vencida',
            'message' => "La tarea \"{$tarea->titulo}\" ha vencido en el workflow \"{$tarea->nodo->workflow->nombre}\"",
            'notifiable_type' => WorkFlowTarea::class,
            'notifiable_id' => $tarea->id,
            'data' => [
                'tarea_id' => $tarea->id,
                'nodo_id' => $tarea->nodo_id,
                'workflow_id' => $tarea->nodo->workflow_id,
                'url' => "/workflows/{$tarea->nodo->workflow_id}",
            ],
        ]);

        $this->dispatch($notificacion);
    }

    public function notificarInstanciaAvanzada(WorkflowInstancia $instancia, string $nodoNombre): void
    {
        $instancia->loadMissing('workflow:id,nombre,creador_user_id,administrador_user_id');
        $workflow = $instancia->workflow;
        $userIds = array_filter(array_unique([
            $workflow->creador_user_id,
            $workflow->administrador_user_id,
            $instancia->usuario_ejecuta_id,
        ]));

        foreach ($userIds as $userId) {
            $notificacion = Notificacion::create([
                'user_id' => $userId,
                'type' => 'instancia.nodo_ejecutado',
                'title' => 'Flujo avanzado',
                'message' => "El flujo \"{$workflow->nombre}\" avanzó al nodo \"{$nodoNombre}\"",
                'notifiable_type' => WorkflowInstancia::class,
                'notifiable_id' => $instancia->id,
                'data' => [
                    'instancia_id' => $instancia->id,
                    'workflow_id' => $workflow->id,
                    'url' => "/workflows/{$workflow->id}/instancias/{$instancia->id}",
                ],
            ]);

            $this->dispatch($notificacion);
        }
    }

    public function notificarInstanciaCompletada(WorkflowInstancia $instancia): void
    {
        $instancia->loadMissing('workflow:id,nombre,creador_user_id,administrador_user_id');
        $workflow = $instancia->workflow;
        $userIds = array_filter(array_unique([
            $workflow->creador_user_id,
            $workflow->administrador_user_id,
            $instancia->usuario_ejecuta_id,
        ]));

        foreach ($userIds as $userId) {
            $notificacion = Notificacion::create([
                'user_id' => $userId,
                'type' => 'instancia.completada',
                'title' => 'Flujo completado',
                'message' => "El flujo \"{$workflow->nombre}\" ha sido completado exitosamente",
                'notifiable_type' => WorkflowInstancia::class,
                'notifiable_id' => $instancia->id,
                'data' => [
                    'instancia_id' => $instancia->id,
                    'workflow_id' => $workflow->id,
                    'url' => "/workflows/{$workflow->id}/instancias/{$instancia->id}",
                ],
            ]);

            $this->dispatch($notificacion);
        }
    }

    private function dispatch(Notificacion $notificacion): void
    {
        try {
            if (! $this->userAllowsNotification($notificacion->user_id, $notificacion->type)) {
                return;
            }

            event(new NotificationPushed($notificacion));
        } catch (\Throwable $e) {
            logger()->warning('Failed to broadcast notification', [
                'notification_id' => $notificacion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function userAllowsNotification(int $userId, string $type): bool
    {
        $preferenceKey = match ($type) {
            'tarea.asignada' => 'new_for_you',
            default => 'account_activity',
        };

        $settings = UserNotificationSetting::forUser($userId);

        if (! $settings) {
            return true;
        }

        return $settings->isEnabled($preferenceKey);
    }
}

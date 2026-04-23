<?php

namespace App\Services\VentanillaUnica;

use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use Illuminate\Pagination\LengthAwarePaginator;

class RadicacionEnviadosService
{
    /**
     * Obtiene radicados enviados con filtros.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = VentanillaRadicaEnviados::query();

        if (!empty($filters['search'])) {
            $query->where(fn($q) => $q
                ->where('num_radicado', 'like', "%{$filters['search']}%")
                ->orWhere('asunto', 'like', "%{$filters['search']}%")
            );
        }

        if (!empty($filters['fecha_desde']) && !empty($filters['fecha_hasta'])) {
            $query->whereBetween('created_at', [$filters['fecha_desde'], $filters['fecha_hasta']]);
        }

        if (!empty($filters['clasifica_documen_id'])) {
            $query->where('clasifica_documen_id', $filters['clasifica_documen_id']);
        }

        if (!empty($filters['tercero_enviado_id'])) {
            $query->where('tercero_id', $filters['tercero_enviado_id']);
        }

        if (!empty($filters['medio_enviado_id'])) {
            $query->where('medio_enviado_id', $filters['medio_enviado_id']);
        }

        if (!empty($filters['usuario_responsable'])) {
            $query->whereHas('responsables', fn($q) => $q->whereHas('userCargo', fn($qc) => 
                $qc->where('user_id', $filters['usuario_responsable'])
            ));
        }

        $query->with([
            'clasificacionDocumental',
            'tercero',
            'medioEnvio',
            'tipoRespuesta',
            'usuarioCreaRadicado',
            'responsables.userCargo.user',
            'responsables.userCargo.cargo',
        ])->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Obtiene un radicado enviado por ID.
     */
    public function getById(int $id): ?VentanillaRadicaEnviados
    {
        return VentanillaRadicaEnviados::with([
            'tercero',
            'clasificacionDocumental',
            'medioEnvio',
            'archivos',
            'responsables.userCargo.user',
            'responsables.userCargo.cargo',
            'usuarioCreaRadicado'
        ])->find($id);
    }

    /**
     * Crea una nueva radicación enviada.
     */
    public function create(array $data): VentanillaRadicaEnviados
    {
        $data['usuario_crea'] = $data['usuario_crea'] ?? auth()->id();
        return VentanillaRadicaEnviados::create($data);
    }

    /**
     * Actualiza una radicación enviada.
     */
    public function update(int $id, array $data): ?VentanillaRadicaEnviados
    {
        $radicado = VentanillaRadicaEnviados::find($id);
        
        if (!$radicado) {
            return null;
        }

        $radicado->update($data);
        return $radicado->fresh();
    }

    /**
     * Elimina una radicación enviada.
     */
    public function delete(int $id): bool
    {
        $radicado = VentanillaRadicaEnviados::find($id);
        
        if (!$radicado) {
            return false;
        }

        return $radicado->delete();
    }

    /**
     * Obtiene estadísticas.
     */
    public function getStats(): array
    {
        return [
            'total_enviados' => VentanillaRadicaEnviados::count(),
            'total_pendientes' => VentanillaRadicaEnviados::where('estado', 'Pendiente')->count(),
        ];
    }
}
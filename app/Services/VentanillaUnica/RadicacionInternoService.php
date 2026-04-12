<?php

namespace App\Services\VentanillaUnica;

use App\Models\VentanillaUnica\VentanillaRadicaInterno;
use Illuminate\Pagination\LengthAwarePaginator;

class RadicacionInternoService
{
    /**
     * Obtiene radicados internos con filtros.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = VentanillaRadicaInterno::query();

        if (!empty($filters['search'])) {
            $query->where(fn($q) => $q
                ->where('num_radicado', 'like', "%{$filters['search']}%")
                ->orWhere('asunto', 'like', "%{$filters['search']}%")
            );
        }

        if (!empty($filters['dependencia_origen_id'])) {
            $query->where('dependencia_origen_id', $filters['dependencia_origen_id']);
        }

        if (!empty($filters['dependencia_destino_id'])) {
            $query->where('dependencia_destino_id', $filters['dependencia_destino_id']);
        }

        $query->with([
            'dependenciaOrigen',
            'dependenciaDestino',
            'clasificacionDocumental',
            'usuarioCreaRadicado',
            'destinatarios.user',
            'proyectores.userCargo.user',
            'proyectores.userCargo.cargo'
        ])->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Obtiene un radicado interno por ID.
     */
    public function getById(int $id): ?VentanillaRadicaInterno
    {
        return VentanillaRadicaInterno::with([
            'dependenciaOrigen',
            'dependenciaDestino',
            'clasificacionDocumental',
            'usuarioCreaRadicado',
            'destinatarios.user',
            'proyectores.userCargo.user',
            'proyectores.userCargo.cargo',
            'archivos'
        ])->find($id);
    }

    /**
     * Crea una nueva radicación interna.
     */
    public function create(array $data): VentanillaRadicaInterno
    {
        $data['usuario_crea'] = $data['usuario_crea'] ?? auth()->id();
        return VentanillaRadicaInterno::create($data);
    }

    /**
     * Actualiza una radicación interna.
     */
    public function update(int $id, array $data): ?VentanillaRadicaInterno
    {
        $radicado = VentanillaRadicaInterno::find($id);
        
        if (!$radicado) {
            return null;
        }

        $radicado->update($data);
        return $radicado->fresh();
    }

    /**
     * Elimina una radicación interna.
     */
    public function delete(int $id): bool
    {
        $radicado = VentanillaRadicaInterno::find($id);
        
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
            'total_internos' => VentanillaRadicaInterno::count(),
            'total_pendientes' => VentanillaRadicaInterno::where('estado', 'Pendiente')->count(),
        ];
    }
}
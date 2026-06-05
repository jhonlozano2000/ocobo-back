<?php

namespace App\Services\MiBandeja\TempDocumentosRecibidos;

use App\Models\MiBandeja\TempDocumentosRecibidos\Cursor;
use App\Models\MiBandeja\TempDocumentosRecibidos\Documento;
use App\Models\User;

class CursorService
{
    private const COLORS = ['#E53935', '#43A047', '#1E88E5', '#FB8C00', '#8E24AA'];

    public function inicializarCursor(Documento $documento, User $user): Cursor
    {
        $colorIndex = $documento->cursores()->count() % count(self::COLORS);

        return $documento->cursores()->create([
            'user_id' => $user->id,
            'nombre_usuario' => $this->getNombreCompleto($user),
            'color' => self::COLORS[$colorIndex],
            'posicion' => 0,
        ]);
    }

    public function actualizarPosicion(Cursor $cursor, float $posicion): void
    {
        $cursor->update(['posicion' => $posicion]);
    }

    public function removerCursor(Documento $documento, int $userId): void
    {
        $documento->cursores()->where('user_id', $userId)->delete();
    }

    public function obtenerCursoresActivos(Documento $documento): array
    {
        return $documento->cursores()
            ->with('usuario:id,name,nombres,apellidos')
            ->get()
            ->toArray();
    }

    public function usuarioTieneCursorActivo(Documento $documento, int $userId): bool
    {
        return $documento->cursores()->where('user_id', $userId)->exists();
    }

    private function getNombreCompleto(User $user): string
    {
        if (!empty($user->nombres) && !empty($user->apellidos)) {
            return trim($user->nombres . ' ' . $user->apellidos);
        }

        return $user->name;
    }
}
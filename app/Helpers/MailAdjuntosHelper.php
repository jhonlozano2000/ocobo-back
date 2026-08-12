<?php

namespace App\Helpers;

use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Storage;

class MailAdjuntosHelper
{
    public const MAX_BYTES = 18 * 1024 * 1024;

    /**
     * Selecciona los adjuntos que caben dentro del límite de tamaño del correo.
     *
     * @param  array|null  $digital  ['path' => string, 'nombre' => string, 'peso' => ?int] o null
     * @param  iterable  $adjuntos  modelos con ->archivo, ->nom_origi y ->archivo_peso (opcional)
     * @return array{attachments: array<int, Attachment>, omitidos: array<int, array{nombre: string, peso: int}>, peso_total: int}
     */
    public static function seleccionarAdjuntos(
        string $disk,
        ?array $digital,
        iterable $adjuntos = [],
        int $maxBytes = self::MAX_BYTES
    ): array {
        $candidatos = [];

        if ($digital && $digital['path'] && Storage::disk($disk)->exists($digital['path'])) {
            $candidatos[] = [
                'path' => $digital['path'],
                'nombre' => $digital['nombre'] ?: basename($digital['path']),
                'peso' => $digital['peso'] ?? null,
            ];
        }

        foreach ($adjuntos as $adjunto) {
            if (Storage::disk($disk)->exists($adjunto->archivo)) {
                $candidatos[] = [
                    'path' => $adjunto->archivo,
                    'nombre' => $adjunto->nom_origi ?: basename($adjunto->archivo),
                    'peso' => $adjunto->archivo_peso ?? null,
                ];
            }
        }

        $attachments = [];
        $omitidos = [];
        $pesoTotal = 0;

        foreach ($candidatos as $candidato) {
            $peso = (int) $candidato['peso'];
            if ($peso <= 0) {
                $peso = (int) Storage::disk($disk)->size($candidato['path']);
            }

            if ($pesoTotal + $peso <= $maxBytes) {
                $attachments[] = Attachment::fromStorageDisk($disk, $candidato['path'])->as($candidato['nombre']);
                $pesoTotal += $peso;
            } else {
                $omitidos[] = [
                    'nombre' => $candidato['nombre'],
                    'peso' => $peso,
                ];
            }
        }

        return [
            'attachments' => $attachments,
            'omitidos' => $omitidos,
            'peso_total' => $pesoTotal,
        ];
    }

    public static function formatearPeso(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}

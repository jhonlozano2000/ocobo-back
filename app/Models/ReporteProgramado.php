<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteProgramado extends Model
{
    protected $table = 'reportes_programados';

    protected $fillable = [
        'modulo',
        'filtros',
        'formato',
        'periodicidad',
        'asunto',
        'destinatarios',
        'ultima_ejecucion',
        'proxima_ejecucion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'filtros' => 'array',
            'destinatarios' => 'array',
            'ultima_ejecucion' => 'datetime',
            'proxima_ejecucion' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public function calcularProximaEjecucion(): \DateTime
    {
        $dias = match ($this->periodicidad) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            'quarterly' => 90,
            default => 1,
        };

        return now()->addDays($dias);
    }
}

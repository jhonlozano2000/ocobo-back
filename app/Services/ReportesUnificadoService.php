<?php

namespace App\Services;

use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use App\Models\VentanillaUnica\Internos\VentanillaRadicaInterno;
use App\Models\VentanillaUnica\Comunes\VentanillaPqrs;
use App\Models\OfiArchivo\OfiArchivoExpediente;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use App\Models\OfiArchivo\OfiArchivoTransferencia;

class ReportesUnificadoService
{
    protected array $modulos = [];

    public function __construct()
    {
        $this->modulos = [
            'recibidas' => [
                'model' => VentanillaRadicaReci::class,
                'label' => 'Radicaciones Recibidas',
                'columnas' => ['ID', 'Radicado', 'Creado por', 'Fecha', 'Asunto', 'Estado'],
                'map' => function ($item) {
                    return [
                        'ID' => $item->id,
                        'Radicado' => $item->num_radicado,
                        'Creado por' => $item->usuarioCreaRadicado?->name ?? 'N/A',
                        'Fecha' => $item->created_at?->format('Y-m-d'),
                        'Asunto' => $item->asunto,
                        'Estado' => $item->estado_trabajo ?? 'Pendiente',
                    ];
                },
            ],
            'enviadas' => [
                'model' => VentanillaRadicaEnviados::class,
                'label' => 'Correspondencia Enviada',
                'columnas' => ['ID', 'Radicado', 'Creado por', 'Fecha', 'Asunto', 'Estado'],
                'map' => function ($item) {
                    return [
                        'ID' => $item->id,
                        'Radicado' => $item->num_radicado,
                        'Creado por' => $item->usuarioCreaRadicado?->name ?? 'N/A',
                        'Fecha' => $item->created_at?->format('Y-m-d'),
                        'Asunto' => $item->asunto,
                        'Estado' => $item->estado_trabajo ?? 'Pendiente',
                    ];
                },
            ],
            'internas' => [
                'model' => VentanillaRadicaInterno::class,
                'label' => 'Correspondencia Interna',
                'columnas' => ['ID', 'Radicado', 'Creado por', 'Fecha', 'Asunto', 'Estado'],
                'map' => function ($item) {
                    return [
                        'ID' => $item->id,
                        'Radicado' => $item->num_radicado,
                        'Creado por' => $item->usuarioCrea?->name ?? 'N/A',
                        'Fecha' => $item->created_at?->format('Y-m-d'),
                        'Asunto' => $item->asunto,
                        'Estado' => $item->estado_trabajo ?? 'Pendiente',
                    ];
                },
            ],
            'pqrs' => [
                'model' => VentanillaPqrs::class,
                'label' => 'PQRS',
                'columnas' => ['ID', 'Radicado', 'Tipo', 'Solicitante', 'Fecha', 'Estado'],
                'map' => function ($item) {
                    return [
                        'ID' => $item->id,
                        'Radicado' => $item->radicado?->num_radicado ?? 'N/A',
                        'Tipo' => $item->tipoPqrs?->nombre ?? $item->tipo_pqrs_id,
                        'Solicitante' => $item->nom_afectado ?? $item->tercero?->nom_completo ?? 'N/A',
                        'Fecha' => $item->created_at?->format('Y-m-d'),
                        'Estado' => $item->estado_tramite ?? 'Pendiente',
                    ];
                },
            ],
            'expedientes' => [
                'model' => OfiArchivoExpediente::class,
                'label' => 'Expedientes',
                'columnas' => ['ID', 'Número', 'Nombre', 'Estado', 'Fecha Apertura', 'Usuario Apertura'],
                'map' => function ($item) {
                    return [
                        'ID' => $item->id,
                        'Número' => $item->numero_expediente,
                        'Nombre' => $item->nombre_expediente,
                        'Estado' => $item->estado ?? 'Abierto',
                        'Fecha Apertura' => $item->fecha_apertura?->format('Y-m-d'),
                        'Usuario Apertura' => $item->usuarioApertura?->name ?? 'N/A',
                    ];
                },
            ],
            'prestamos' => [
                'model' => OfiArchivoPrestamo::class,
                'label' => 'Préstamos',
                'columnas' => ['ID', 'Solicitante', 'Fecha Préstamo', 'Devolución Esperada', 'Estado'],
                'map' => function ($item) {
                    return [
                        'ID' => $item->id,
                        'Solicitante' => $item->solicitante?->name ?? 'N/A',
                        'Fecha Préstamo' => $item->fecha_prestamo?->format('Y-m-d'),
                        'Devolución Esperada' => $item->fecha_devolucion_esperada?->format('Y-m-d') ?? 'N/A',
                        'Estado' => $item->estado ?? 'Activo',
                    ];
                },
            ],
            'transferencias' => [
                'model' => OfiArchivoTransferencia::class,
                'label' => 'Transferencias',
                'columnas' => ['ID', 'Tipo', 'Estado', 'Fecha', 'Responsable Origen', 'Responsable Destino'],
                'map' => function ($item) {
                    return [
                        'ID' => $item->id,
                        'Tipo' => ucfirst($item->tipo ?? 'N/A'),
                        'Estado' => ucfirst($item->estado ?? 'Pendiente'),
                        'Fecha' => $item->fecha_transferencia?->format('Y-m-d') ?? $item->created_at?->format('Y-m-d'),
                        'Responsable Origen' => $item->responsableOrigen?->name ?? 'N/A',
                        'Responsable Destino' => $item->responsableDestino?->name ?? 'N/A',
                    ];
                },
            ],
        ];
    }

    public function generarUnificado(string $modulo, array $filtros = []): array
    {
        if (!isset($this->modulos[$modulo])) {
            throw new \InvalidArgumentException("Módulo no válido: $modulo");
        }

        $config = $this->modulos[$modulo];
        $query = $config['model']::query();

        $this->aplicarFiltros($query, $modulo, $filtros);

        $perPage = min((int)($filtros['per_page'] ?? 50), 100);
        $page = (int)($filtros['page'] ?? 1);
        $total = $query->count();
        $items = $query->with($this->getRelations($modulo))
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $datos = $items->map($config['map'])->toArray();

        $resumen = [
            'Total' => $total,
            'Módulo' => $config['label'],
            'Página' => $page,
            'Por página' => $perPage,
        ];

        if (!empty($filtros['desde'])) {
            $resumen['Desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $resumen['Hasta'] = $filtros['hasta'];
        }

        return [
            'titulo' => $config['label'],
            'fecha_generacion' => now()->format('Y-m-d H:i:s'),
            'total' => $total,
            'columnas' => $config['columnas'],
            'datos' => $datos,
            'resumen' => $resumen,
            'pagina' => $page,
            'por_pagina' => $perPage,
        ];
    }

    public function getModulos(): array
    {
        return array_map(fn($c) => $c['label'], $this->modulos);
    }

    protected function aplicarFiltros($query, string $modulo, array $filtros): void
    {
        $fechaCol = match ($modulo) {
            'expedientes' => 'fecha_apertura',
            'prestamos' => 'fecha_prestamo',
            'transferencias' => 'fecha_transferencia',
            default => 'created_at',
        };

        if (!empty($filtros['desde'])) {
            $query->whereDate($fechaCol, '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $query->whereDate($fechaCol, '<=', $filtros['hasta']);
        }

        $estadoCol = match ($modulo) {
            'recibidas', 'enviadas', 'internas' => 'estado_trabajo',
            'pqrs' => 'estado_tramite',
            default => 'estado',
        };

        if (!empty($filtros['estado'])) {
            $query->where($estadoCol, $filtros['estado']);
        }
    }

    protected function getRelations(string $modulo): array
    {
        return match ($modulo) {
            'recibidas' => ['usuarioCreaRadicado'],
            'enviadas' => ['usuarioCreaRadicado'],
            'internas' => ['usuarioCrea'],
            'pqrs' => ['radicado', 'tipoPqrs', 'tercero'],
            'expedientes' => ['usuarioApertura'],
            'prestamos' => ['solicitante'],
            'transferencias' => ['responsableOrigen', 'responsableDestino'],
            default => [],
        };
    }
}

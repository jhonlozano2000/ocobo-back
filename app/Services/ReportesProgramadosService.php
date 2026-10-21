<?php

namespace App\Services;

use App\Helpers\MailConfigHelper;
use App\Mail\ReporteProgramadoMailable;
use App\Models\ReporteProgramado;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;

/**
 * REP-001/REP-002: un reporte programado se genera con el permiso y el alcance de su dueño,
 * nunca sin usuario (antes el comando del scheduler exportaba el módulo completo).
 */
class ReportesProgramadosService
{
    public function __construct(
        protected ReportesUnificadoService $unificado,
        protected ReportesExportService $export
    ) {}

    /**
     * Genera el archivo, lo envía a los destinatarios y agenda la próxima ejecución.
     *
     * @throws AuthorizationException si no tiene dueño activo o el dueño perdió el permiso del módulo
     */
    public function ejecutar(ReporteProgramado $item): ReporteProgramado
    {
        $dueno = $item->usuario;
        if (! $dueno || (int) $dueno->estado !== 1) {
            throw new AuthorizationException('El reporte programado no tiene un usuario dueño activo.');
        }

        $data = $this->unificado->generarUnificado($item->modulo, $item->filtros ?? [], $dueno, true);
        $columnas = $data['columnas'];
        $nombre = 'reporte_'.$item->modulo;

        $path = match ($item->formato) {
            'excel' => $this->export->exportarExcel($data, $nombre, $columnas),
            'pdf' => $this->export->exportarPDF($data, $nombre),
            'csv' => $this->export->guardarCSV($data, $nombre, $columnas),
        };

        try {
            // El SMTP de la entidad se configura en la app (config_varias), no en .env
            MailConfigHelper::configureFromConfigVarias();
            Mail::to($item->destinatarios)->send(new ReporteProgramadoMailable($path, $item->asunto));
        } finally {
            @unlink($path);
        }

        $item->ultima_ejecucion = now();
        $item->proxima_ejecucion = $item->calcularProximaEjecucion();
        $item->save();

        return $item;
    }
}

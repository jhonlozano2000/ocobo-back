<?php

namespace App\Console\Commands;

use App\Models\Notificacion;
use App\Models\OfiArchivo\OfiArchivoPrestamo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * B15 (diseño de expedientes §3.10): marca como vencidos los préstamos cuya fecha de devolución
 * pasó y avisa al solicitante y a quien registró el préstamo. El cambio de estado queda en el
 * historial del expediente (OfiArchivoPrestamo::booted).
 *
 * §3.10: el acceso electrónico no vence, expira: pasa a devuelto sin aviso y la consulta se cierra.
 */
class MarcarPrestamosVencidosCommand extends Command
{
    protected $signature = 'archivo:marcar-prestamos-vencidos
                            {--dry-run : Solo muestra sin cambiar estados ni notificar}';

    protected $description = 'Marca como vencidos los préstamos de expedientes no devueltos a tiempo';

    public const TYPE = 'prestamo_vencido';

    public function handle(): int
    {
        $expirados = 0;
        OfiArchivoPrestamo::where('estado', 'prestado')
            ->where('modalidad', 'electronico')
            ->where('fecha_devolucion_esperada', '<', now())
            ->chunkById(200, function ($prestamos) use (&$expirados) {
                foreach ($prestamos as $prestamo) {
                    if (! $this->option('dry-run')) {
                        $prestamo->update(['estado' => 'devuelto', 'fecha_devolucion_real' => $prestamo->fecha_devolucion_esperada]);
                    }
                    $expirados++;
                }
            });
        $this->info("Accesos electrónicos expirados: {$expirados}");

        $marcados = 0;
        OfiArchivoPrestamo::where('estado', 'prestado')
            ->where('modalidad', '!=', 'electronico')
            ->where('fecha_devolucion_esperada', '<', now())
            ->with('expediente:id,numero_expediente,nombre_expediente')
            ->chunkById(200, function ($prestamos) use (&$marcados) {
                foreach ($prestamos as $prestamo) {
                    $numero = $prestamo->expediente?->numero_expediente ?? "#{$prestamo->expediente_id}";
                    $this->line("  Préstamo #{$prestamo->id} ({$numero}) debía devolverse el {$prestamo->fecha_devolucion_esperada->toDateString()}");
                    if (! $this->option('dry-run')) {
                        $prestamo->update(['estado' => 'vencido']);
                        $this->notificar($prestamo, $numero);
                    }
                    $marcados++;
                }
            });

        $this->info("Préstamos marcados como vencidos: {$marcados}");
        Log::info("MarcarPrestamosVencidos: {$marcados} préstamos");

        return Command::SUCCESS;
    }

    private function notificar(OfiArchivoPrestamo $prestamo, string $numero): void
    {
        $fecha = $prestamo->fecha_devolucion_esperada->toDateString();
        $nombre = $prestamo->expediente?->nombre_expediente;

        $destinatarios = array_unique(array_filter([$prestamo->solicitante_id, $prestamo->usuario_registro_id, $prestamo->entregado_por_id]));
        foreach ($destinatarios as $userId) {
            Notificacion::create([
                'user_id' => $userId,
                'type' => self::TYPE,
                'title' => 'Préstamo de expediente vencido',
                'message' => "El préstamo del expediente {$numero}".($nombre ? " ({$nombre})" : '')
                    ." debía devolverse el {$fecha}. Devuélvalo al archivo o solicite una ampliación.",
                'data' => [
                    'prestamo_id' => $prestamo->id,
                    'expediente_id' => $prestamo->expediente_id,
                    'numero_expediente' => $numero,
                    'tipo_entidad' => 'expediente',
                    'fecha_devolucion_esperada' => $fecha,
                ],
            ]);
        }
    }
}

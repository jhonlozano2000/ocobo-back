<?php

namespace App\Services\VentanillaUnica;

use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\Comunes\RadicadoConsecutivo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RadicadoConsecutivoService
{
    private const TIPO_RECIBIDO = 'recibido';

    private const TIPO_INTERNO = 'interno';

    /**
     * Reserva el próximo consecutivo para radicados recibidos dentro de la transacción llamadora.
     */
    public function reservarRecibido(?string $codDependencia = null): string
    {
        return $this->reservarPorTipo(self::TIPO_RECIBIDO, 'formato_num_radicado_reci', $codDependencia);
    }

    /**
     * Reserva el próximo consecutivo para radicados internos dentro de la transacción llamadora.
     * Si la numeración es unificada, comparte el consecutivo; de lo contrario usa contador independiente.
     */
    public function reservarInterno(?string $codDependencia = null): string
    {
        $esUnificada = ConfigVarias::getNumeracionUnificada(true);
        $tipoContador = $esUnificada ? self::TIPO_RECIBIDO : self::TIPO_INTERNO;

        return $this->reservarPorTipo($tipoContador, 'formato_num_radicado_reci', $codDependencia);
    }

    /**
     * Lógica atómica de reserva de consecutivo.
     */
    private function reservarPorTipo(string $tipo, string $claveFormato, ?string $codDependencia = null): string
    {
        try {
            $periodo = (int) now()->format('Y');

            DB::table('ventanilla_radicado_consecutivos')->insertOrIgnore([
                'tipo' => $tipo,
                'periodo' => $periodo,
                'consecutivo' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $contador = RadicadoConsecutivo::query()
                ->where('tipo', $tipo)
                ->where('periodo', $periodo)
                ->lockForUpdate()
                ->firstOrFail();

            $contador->consecutivo++;
            $contador->save();

            return $this->formatear(
                ConfigVarias::getValor($claveFormato, 'YYYYMMDD-#####'),
                $contador->consecutivo,
                $codDependencia
            );
        } catch (QueryException $exception) {
            throw new \RuntimeException('No fue posible reservar el consecutivo oficial del radicado.', 0, $exception);
        }
    }

    private function formatear(string $formato, int $consecutivo, ?string $codDependencia): string
    {
        preg_match('/#+/', $formato, $matches);
        $longitud = isset($matches[0]) ? strlen($matches[0]) : 5;
        $fecha = now();

        $variables = [
            'YYYY' => $fecha->format('Y'),
            'MM' => $fecha->format('m'),
            'DD' => $fecha->format('d'),
            'COD_DEPEN' => $codDependencia ?? '',
            str_repeat('#', $longitud) => str_pad((string) $consecutivo, $longitud, '0', STR_PAD_LEFT),
        ];

        return str_replace(array_keys($variables), array_values($variables), $formato);
    }
}

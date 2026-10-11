<?php

namespace App\Services\VentanillaUnica;

use App\Models\Configuracion\ConfigVarias;
use App\Models\VentanillaUnica\Comunes\RadicadoConsecutivo;
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RadicadoConsecutivoService
{
    private const TIPO_RECIBIDO = 'recibido';

    private const TIPO_INTERNO = 'interno';

    private const TIPO_ENVIADO = 'enviado';

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
     * Reserva el próximo consecutivo para radicados enviados (formulario y correo) dentro de la
     * transacción llamadora. M02-08: antes se leía el último número sin bloqueo y dos envíos
     * simultáneos chocaban con el índice único.
     */
    public function reservarEnviado(?string $codDependencia = null): string
    {
        // El formulario leía 'formato_num_radicado_envi' y el correo 'formato_num_radicado_env'
        $claveFormato = ConfigVarias::getValor('formato_num_radicado_envi') !== null
            ? 'formato_num_radicado_envi'
            : 'formato_num_radicado_env';

        return $this->reservarPorTipo(self::TIPO_ENVIADO, $claveFormato, $codDependencia, function (int $periodo) {
            return $this->maximoExistente(
                VentanillaRadicaEnviados::query()->whereYear('created_at', $periodo)->pluck('num_radicado')->all()
            );
        });
    }

    /**
     * Lógica atómica de reserva de consecutivo.
     *
     * @param  (callable(int): int)|null  $valorInicial  Consecutivo de partida cuando el contador del
     *                                                   periodo aún no existe (radicados ya emitidos).
     */
    private function reservarPorTipo(string $tipo, string $claveFormato, ?string $codDependencia = null, ?callable $valorInicial = null): string
    {
        try {
            $periodo = (int) now()->format('Y');

            $existe = $valorInicial !== null && DB::table('ventanilla_radicado_consecutivos')
                ->where('tipo', $tipo)
                ->where('periodo', $periodo)
                ->exists();

            DB::table('ventanilla_radicado_consecutivos')->insertOrIgnore([
                'tipo' => $tipo,
                'periodo' => $periodo,
                'consecutivo' => $valorInicial !== null && ! $existe ? $valorInicial($periodo) : 0,
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

    /**
     * Mayor consecutivo (dígitos finales) entre los números dados.
     *
     * @param  array<int, string|null>  $numeros
     */
    private function maximoExistente(array $numeros): int
    {
        $maximo = 0;
        foreach ($numeros as $numero) {
            if (preg_match('/(\d+)$/', (string) $numero, $matches)) {
                $maximo = max($maximo, (int) $matches[1]);
            }
        }

        return $maximo;
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

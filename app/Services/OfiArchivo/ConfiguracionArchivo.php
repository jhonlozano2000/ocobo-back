<?php

namespace App\Services\OfiArchivo;

use App\Models\Configuracion\ConfigVarias;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Ajustes del módulo de expedientes que administra cada entidad en config_varias
 * (Otras configuraciones → Archivo). Sin la clave o con el valor vacío se usa el respaldo de
 * config/archivo.php y config/firma.php.
 */
final class ConfiguracionArchivo
{
    /** D3 (diseño de expedientes §3.3): folios por tomo físico. */
    public const FOLIOS_POR_TOMO = 'archivo_folios_por_tomo';

    /** D4 (diseño de expedientes §3.4): meses sin incorporaciones para avisar inactividad. */
    public const MESES_INACTIVIDAD = 'archivo_meses_inactividad';

    /** F1: sellar con la TSA cada versión firmada del índice electrónico. */
    public const INDICE_TSA = 'archivo_indice_tsa';

    /** B20: la ubicación de los expedientes físicos solo se toma del catálogo topográfico. */
    public const UBICACION_CON_CATALOGO = 'archivo_ubicacion_con_catalogo';

    /** §3.10: días máximos entre la solicitud de un préstamo y su devolución. */
    public const PRESTAMO_PLAZO_MAXIMO = 'archivo_prestamo_plazo_maximo_dias';

    /** B21 (diseño de expedientes §8): porcentaje de folios cuya huella se verifica cada semana. */
    public const INTEGRIDAD_MUESTRA = 'archivo_integridad_porcentaje_muestra';

    /** Reglas del valor de cada clave al guardarla. */
    public const REGLAS = [
        self::FOLIOS_POR_TOMO => ['required', 'integer', 'min:1', 'max:1000'],
        self::MESES_INACTIVIDAD => ['required', 'integer', 'min:1', 'max:120'],
        self::INDICE_TSA => ['required', 'in:0,1,true,false'],
        self::UBICACION_CON_CATALOGO => ['required', 'in:0,1,true,false'],
        self::PRESTAMO_PLAZO_MAXIMO => ['required', 'integer', 'min:1', 'max:365'],
        self::INTEGRIDAD_MUESTRA => ['required', 'integer', 'min:1', 'max:100'],
    ];

    private const NOMBRES = [
        self::FOLIOS_POR_TOMO => 'folios por tomo',
        self::MESES_INACTIVIDAD => 'meses de inactividad',
        self::INDICE_TSA => 'sello de tiempo del índice',
        self::UBICACION_CON_CATALOGO => 'ubicación con catálogo',
        self::PRESTAMO_PLAZO_MAXIMO => 'plazo máximo del préstamo',
        self::INTEGRIDAD_MUESTRA => 'porcentaje de la muestra de integridad',
    ];

    public static function foliosPorTomo(): int
    {
        return self::entero(self::FOLIOS_POR_TOMO, config('archivo.folios_por_tomo', 200));
    }

    public static function mesesInactividad(): int
    {
        return self::entero(self::MESES_INACTIVIDAD, config('archivo.meses_inactividad', 6));
    }

    public static function indiceConTsa(): bool
    {
        $valor = ConfigVarias::getValor(self::INDICE_TSA);

        return blank($valor)
            ? (bool) config('firma.tsa_indice_expediente', false)
            : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    public static function prestamoPlazoMaximo(): int
    {
        return self::entero(self::PRESTAMO_PLAZO_MAXIMO, config('archivo.prestamo_plazo_maximo_dias', 15));
    }

    public static function integridadPorcentajeMuestra(): int
    {
        return min(100, self::entero(self::INTEGRIDAD_MUESTRA, config('archivo.integridad_porcentaje_muestra', 5)));
    }

    /** Sin la clave o vacía, apagado: la ubicación sigue siendo texto libre. */
    public static function ubicacionConCatalogo(): bool
    {
        return filter_var(ConfigVarias::getValor(self::UBICACION_CON_CATALOGO), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Valida el valor de una clave del módulo antes de guardarla; las demás claves pasan.
     *
     * @throws ValidationException
     */
    public static function validar(string $clave, mixed $valor, string $atributo = 'valor'): void
    {
        if (! isset(self::REGLAS[$clave])) {
            return;
        }

        $nombre = self::NOMBRES[$clave];
        $validador = Validator::make(['valor' => $valor], ['valor' => self::REGLAS[$clave]], [
            'required' => "Indique el valor de $nombre.",
            'integer' => "El valor de $nombre debe ser un número entero.",
            'min' => "El valor de $nombre debe ser al menos :min.",
            'max' => "El valor de $nombre no puede ser mayor que :max.",
            'in' => "El valor de $nombre debe ser verdadero o falso.",
        ]);

        // La clave plana evita que un atributo como configs.0.valor se lea como ruta anidada
        if ($validador->fails()) {
            throw ValidationException::withMessages([$atributo => $validador->errors()->get('valor')]);
        }
    }

    private static function entero(string $clave, mixed $respaldo): int
    {
        $valor = ConfigVarias::getValor($clave);

        return max(1, is_numeric($valor) ? (int) $valor : (int) $respaldo);
    }
}

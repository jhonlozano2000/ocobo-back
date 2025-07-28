<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigVarias extends Model
{
    use HasFactory;

    protected $table = 'config_varias';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    public static function getValor($clave, $default = null)
    {
        $config = self::where('clave', $clave)->first();
        return $config ? $config->valor : $default;
    }

    /**
     * Obtiene el valor de numeración unificada como booleano.
     *
     * @param bool $default Valor por defecto si no existe la configuración
     * @return bool
     */
    public static function getNumeracionUnificada($default = true)
    {
        $valor = self::getValor('numeracion_unificada', $default ? 'true' : 'false');
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Establece el valor de numeración unificada.
     *
     * @param bool $valor
     * @return bool
     */
    public static function setNumeracionUnificada($valor)
    {
        $config = self::where('clave', 'numeracion_unificada')->first();

        if ($config) {
            $config->update(['valor' => $valor ? 'true' : 'false']);
        } else {
            self::create([
                'clave' => 'numeracion_unificada',
                'valor' => $valor ? 'true' : 'false',
                'descripcion' => 'Configuración de numeración unificada de radicados'
            ]);
        }

        return true;
    }

    /**
     * Obtiene el valor de multi_sede como entero.
     *
     * @param int $default Valor por defecto si no existe la configuración
     * @return int
     */
    public static function getMultiSede($default = 0)
    {
        $valor = self::getValor('multi_sede', (string)$default);
        return (int)$valor;
    }

    /**
     * Establece el valor de multi_sede.
     *
     * @param int $valor
     * @return bool
     */
    public static function setMultiSede($valor)
    {
        $config = self::where('clave', 'multi_sede')->first();

        if ($config) {
            $config->update(['valor' => (string)$valor]);
        } else {
            self::create([
                'clave' => 'multi_sede',
                'valor' => (string)$valor,
                'descripcion' => 'Configuración de múltiples sedes (0: deshabilitado, 1: habilitado)'
            ]);
        }

        return true;
    }

    /**
     * Verifica si multi_sede está habilitado.
     *
     * @return bool
     */
    public static function isMultiSedeEnabled()
    {
        return self::getMultiSede() === 1;
    }
}

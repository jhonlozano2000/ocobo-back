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

    // ==================== MÉTODOS PARA INFORMACIÓN DE LA EMPRESA ====================

    /**
     * Obtiene toda la información de la empresa.
     *
     * @return array
     */
    public static function getInformacionEmpresa()
    {
        $configs = self::whereIn('clave', [
            'nit_empresa',
            'razon_social_empresa',
            'divi_poli_id_empresa',
            'logo_empresa',
            'direccion_empresa',
            'telefono_empresa',
            'correo_electronico_empresa',
            'web_empresa'
        ])->pluck('valor', 'clave')->toArray();

        // Agregar URL del logo si existe
        if (!empty($configs['logo_empresa'])) {
            $configs['logo_url'] = self::getLogoUrlEmpresa();
        }

        return $configs;
    }

    /**
     * Obtiene el NIT de la empresa.
     *
     * @return string|null
     */
    public static function getNitEmpresa()
    {
        return self::getValor('nit_empresa');
    }

    /**
     * Establece el NIT de la empresa.
     *
     * @param string $nit
     * @return bool
     */
    public static function setNitEmpresa($nit)
    {
        return self::actualizarConfiguracion('nit_empresa', $nit);
    }

    /**
     * Obtiene la razón social de la empresa.
     *
     * @return string|null
     */
    public static function getRazonSocialEmpresa()
    {
        return self::getValor('razon_social_empresa');
    }

    /**
     * Establece la razón social de la empresa.
     *
     * @param string $razonSocial
     * @return bool
     */
    public static function setRazonSocialEmpresa($razonSocial)
    {
        return self::actualizarConfiguracion('razon_social_empresa', $razonSocial);
    }

    /**
     * Obtiene el ID de la división política de la empresa.
     *
     * @return int|null
     */
    public static function getDiviPoliIdEmpresa()
    {
        $valor = self::getValor('divi_poli_id_empresa');
        return $valor ? (int)$valor : null;
    }

    /**
     * Establece el ID de la división política de la empresa.
     *
     * @param int|string $diviPoliId
     * @return bool
     */
    public static function setDiviPoliIdEmpresa($diviPoliId)
    {
        // Asegurar que sea un entero válido y convertirlo a string
        $diviPoliId = (int)$diviPoliId;
        return self::actualizarConfiguracion('divi_poli_id_empresa', (string)$diviPoliId);
    }

    /**
     * Obtiene el nombre del archivo del logo de la empresa.
     *
     * @return string|null
     */
    public static function getLogoEmpresa()
    {
        return self::getValor('logo_empresa');
    }

    /**
     * Establece el nombre del archivo del logo de la empresa.
     *
     * @param string $logo
     * @return bool
     */
    public static function setLogoEmpresa($logo)
    {
        return self::actualizarConfiguracion('logo_empresa', $logo);
    }

    /**
     * Guarda el logo de la empresa usando ArchivoHelper.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $campo
     * @return string|null
     */
    public static function guardarLogoEmpresa($request, $campo = 'logo')
    {
        $logoActual = self::getLogoEmpresa();
        $nuevoLogo = \App\Helpers\ArchivoHelper::guardarArchivo($request, $campo, 'otros_archivos', $logoActual);

        if ($nuevoLogo) {
            self::setLogoEmpresa($nuevoLogo);
        }

        return $nuevoLogo;
    }

    /**
     * Obtiene la URL del logo de la empresa.
     *
     * @return string|null
     */
    public static function getLogoUrlEmpresa()
    {
        $logo = self::getLogoEmpresa();
        return $logo ? \App\Helpers\ArchivoHelper::obtenerUrl($logo, 'otros_archivos') : null;
    }

    /**
     * Obtiene la dirección de la empresa.
     *
     * @return string|null
     */
    public static function getDireccionEmpresa()
    {
        return self::getValor('direccion_empresa');
    }

    /**
     * Establece la dirección de la empresa.
     *
     * @param string $direccion
     * @return bool
     */
    public static function setDireccionEmpresa($direccion)
    {
        return self::actualizarConfiguracion('direccion_empresa', $direccion);
    }

    /**
     * Obtiene el teléfono de la empresa.
     *
     * @return string|null
     */
    public static function getTelefonoEmpresa()
    {
        return self::getValor('telefono_empresa');
    }

    /**
     * Establece el teléfono de la empresa.
     *
     * @param string $telefono
     * @return bool
     */
    public static function setTelefonoEmpresa($telefono)
    {
        return self::actualizarConfiguracion('telefono_empresa', $telefono);
    }

    /**
     * Obtiene el correo electrónico de la empresa.
     *
     * @return string|null
     */
    public static function getCorreoElectronicoEmpresa()
    {
        return self::getValor('correo_electronico_empresa');
    }

    /**
     * Establece el correo electrónico de la empresa.
     *
     * @param string $correo
     * @return bool
     */
    public static function setCorreoElectronicoEmpresa($correo)
    {
        return self::actualizarConfiguracion('correo_electronico_empresa', $correo);
    }

    /**
     * Obtiene el sitio web de la empresa.
     *
     * @return string|null
     */
    public static function getWebEmpresa()
    {
        return self::getValor('web_empresa');
    }

    /**
     * Establece el sitio web de la empresa.
     *
     * @param string $web
     * @return bool
     */
    public static function setWebEmpresa($web)
    {
        return self::actualizarConfiguracion('web_empresa', $web);
    }

    /**
     * Método auxiliar para actualizar configuraciones.
     *
     * @param string $clave
     * @param mixed $valor
     * @return bool
     */
    private static function actualizarConfiguracion($clave, $valor)
    {
        // Asegurar que el valor sea siempre string
        $valorString = (string)$valor;

        $config = self::where('clave', $clave)->first();

        if ($config) {
            $config->update(['valor' => $valorString]);
        } else {
            self::create([
                'clave' => $clave,
                'valor' => $valorString,
                'descripcion' => 'Configuración de ' . str_replace('_', ' ', $clave)
            ]);
        }

        return true;
    }
}

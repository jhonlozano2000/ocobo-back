<?php

namespace App\Models\Configuracion;

use App\Helpers\ArchivoHelper;
use App\Models\UsersActivityLog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ConfigVarias extends Model
{
    use HasFactory;

    protected $table = 'config_varias';

    protected $fillable = ['clave', 'valor', 'descripcion', 'tipo', 'estado'];

    protected $casts = [
        'estado' => 'boolean',
    ];

    protected $appends = ['logo_url'];

    /**
     * M05-02 / M15-01: claves cuyo valor se guarda cifrado y nunca se expone en respuestas.
     */
    public const CLAVES_SECRETAS = ['correo_password'];

    /**
     * Prefijo de claves de infraestructura excluidas del endpoint público.
     */
    public const PREFIJO_PRIVADO = 'correo_';

    protected static function booted(): void
    {
        static::saving(function (ConfigVarias $config) {
            if ($config->esSecreta() && $config->isDirty('valor') && filled($config->valor)
                && ! self::estaCifrado($config->valor)) {
                $config->valor = encrypt((string) $config->valor);
            }
        });

        // M15-05: Auditoría de creación de configuración
        static::created(function (ConfigVarias $config) {
            $newValues = $config->getAttributes();
            if ($config->esSecreta() && isset($newValues['valor'])) {
                $newValues['valor'] = '********';
            }

            UsersActivityLog::log([
                'module' => 'Configuracion',
                'action' => 'crear',
                'description' => "Creación de configuración {$config->clave}",
                'entity_id' => $config->id,
                'entity_type' => self::class,
                'old_values' => null,
                'new_values' => $newValues,
                'is_critical' => $config->esSecreta(),
            ]);
        });

        // M15-05: Auditoría de cambios de configuración
        static::updated(function (ConfigVarias $config) {
            $oldValues = $config->getOriginal();
            $newValues = $config->getChanges();

            if ($config->esSecreta()) {
                if (isset($oldValues['valor'])) {
                    $oldValues['valor'] = '********';
                }
                if (isset($newValues['valor'])) {
                    $newValues['valor'] = '********';
                }
            }

            UsersActivityLog::log([
                'module' => 'Configuracion',
                'action' => 'actualizar',
                'description' => "Modificación de configuración {$config->clave}",
                'entity_id' => $config->id,
                'entity_type' => self::class,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'is_critical' => $config->esSecreta(),
            ]);
        });
    }

    /**
     * M15-06: Accesor para valor tipado según columna 'tipo'.
     */
    public function getValorTipadoAttribute(): mixed
    {
        if ($this->esSecreta()) {
            return null;
        }

        return match (strtolower((string) $this->tipo)) {
            'integer', 'int' => is_numeric($this->valor) ? (int) $this->valor : null,
            'float', 'double', 'decimal' => is_numeric($this->valor) ? (float) $this->valor : null,
            'boolean', 'bool' => filter_var($this->valor, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode((string) $this->valor, true) ?? [],
            default => $this->valor,
        };
    }

    /**
     * M15-06: Obtiene el valor con tipado resuelto.
     */
    public static function getValorTipado(string $clave, mixed $default = null): mixed
    {
        $config = self::where('clave', $clave)->first();

        return $config ? ($config->valor_tipado ?? $default) : $default;
    }

    public function esSecreta(): bool
    {
        return in_array($this->clave, self::CLAVES_SECRETAS, true);
    }

    public static function estaCifrado(?string $valor): bool
    {
        if (blank($valor)) {
            return false;
        }

        try {
            decrypt($valor);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Nunca serializa el valor de una clave secreta; solo indica si está configurada.
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        if ($this->esSecreta()) {
            $data['configurado'] = filled($this->valor);
            $data['valor'] = null;
        }

        return $data;
    }

    /**
     * Obtiene la URL de cualquier archivo usando ArchivoHelper.
     *
     * @param  string  $campo  Nombre del atributo (ej: 'valor')
     * @param  string  $disk  Nombre del disco
     */
    public function getArchivoUrl(string $campo, string $disk): ?string
    {
        return ArchivoHelper::obtenerUrl($this->{$campo} ?? null, $disk);
    }

    /**
     * Obtiene la URL del logo cuando la clave corresponde.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->clave !== 'logo_empresa') {
            return null;
        }

        return $this->getArchivoUrl('valor', 'otros_archivos');
    }

    public static function getValor($clave, $default = null)
    {
        $config = self::where('clave', $clave)->first();

        return $config ? $config->valor : $default;
    }

    /**
     * Obtiene el valor de numeración unificada como booleano.
     *
     * @param  bool  $default  Valor por defecto si no existe la configuración
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
     * @param  bool  $valor
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
                'descripcion' => 'Configuración de numeración unificada de radicados',
            ]);
        }

        return true;
    }

    /**
     * Obtiene el valor de multi_sede como entero.
     *
     * @param  int  $default  Valor por defecto si no existe la configuración
     * @return int
     */
    public static function getMultiSede($default = 0)
    {
        $valor = self::getValor('multi_sede', (string) $default);

        return (int) $valor;
    }

    /**
     * Establece el valor de multi_sede.
     *
     * @param  int  $valor
     * @return bool
     */
    public static function setMultiSede($valor)
    {
        $config = self::where('clave', 'multi_sede')->first();

        if ($config) {
            $config->update(['valor' => (string) $valor]);
        } else {
            self::create([
                'clave' => 'multi_sede',
                'valor' => (string) $valor,
                'descripcion' => 'Configuración de múltiples sedes (0: deshabilitado, 1: habilitado)',
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
            'web_empresa',
        ])->pluck('valor', 'clave')->toArray();

        // Agregar URL del logo si existe
        if (! empty($configs['logo_empresa'])) {
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
     * @param  string  $nit
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
     * @param  string  $razonSocial
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

        return $valor ? (int) $valor : null;
    }

    /**
     * Establece el ID de la división política de la empresa.
     *
     * @param  int|string  $diviPoliId
     * @return bool
     */
    public static function setDiviPoliIdEmpresa($diviPoliId)
    {
        // Asegurar que sea un entero válido y convertirlo a string
        $diviPoliId = (int) $diviPoliId;

        return self::actualizarConfiguracion('divi_poli_id_empresa', (string) $diviPoliId);
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
     * @param  string  $logo
     * @return bool
     */
    public static function setLogoEmpresa($logo)
    {
        return self::actualizarConfiguracion('logo_empresa', $logo);
    }

    /**
     * Guarda el logo de la empresa usando ArchivoHelper.
     *
     * @param  Request  $request
     * @param  string  $campo
     * @return string|null
     */
    public static function guardarLogoEmpresa($request, $campo = 'logo')
    {
        $logoActual = self::getLogoEmpresa();
        $nuevoLogo = ArchivoHelper::guardarArchivo($request, $campo, 'otros_archivos', $logoActual);

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

        return $logo ? ArchivoHelper::obtenerUrl($logo, 'otros_archivos') : null;
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
     * @param  string  $direccion
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
     * @param  string  $telefono
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
     * @param  string  $correo
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
     * @param  string  $web
     * @return bool
     */
    public static function setWebEmpresa($web)
    {
        return self::actualizarConfiguracion('web_empresa', $web);
    }

    // ==================== MÉTODOS PARA DÍAS HÁBILES ====================

    public static function getConsiderarDiasHabiles($default = true)
    {
        $valor = self::getValor('considerar_dias_habiles', $default ? 'true' : 'false');

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setConsiderarDiasHabiles($valor)
    {
        return self::actualizarConfiguracion('considerar_dias_habiles', $valor ? 'true' : 'false');
    }

    public static function getDiasVencimientoPredeterminado($default = 5)
    {
        $valor = self::getValor('dias_vencimiento_predeterminado', (string) $default);

        return (int) $valor;
    }

    public static function setDiasVencimientoPredeterminado($valor)
    {
        return self::actualizarConfiguracion('dias_vencimiento_predeterminado', (int) $valor);
    }

    // ==================== MÉTODOS PARA SEMÁFORO ====================

    public static function getSemaforoActivo($default = true)
    {
        $valor = self::getValor('semaforo_activo', $default ? 'true' : 'false');

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setSemaforoActivo($valor)
    {
        return self::actualizarConfiguracion('semaforo_activo', $valor ? 'true' : 'false');
    }

    public static function getSemaforoVerdeDias($default = 2)
    {
        $valor = self::getValor('semaforo_verde_dias', (string) $default);

        return (int) $valor;
    }

    public static function setSemaforoVerdeDias($valor)
    {
        return self::actualizarConfiguracion('semaforo_verde_dias', (int) $valor);
    }

    public static function getSemaforoAmarilloDias($default = 4)
    {
        $valor = self::getValor('semaforo_amarillo_dias', (string) $default);

        return (int) $valor;
    }

    public static function setSemaforoAmarilloDias($valor)
    {
        return self::actualizarConfiguracion('semaforo_amarillo_dias', (int) $valor);
    }

    public static function getSemaforoRojoDias($default = 5)
    {
        $valor = self::getValor('semaforo_rojo_dias', (string) $default);

        return (int) $valor;
    }

    public static function setSemaforoRojoDias($valor)
    {
        return self::actualizarConfiguracion('semaforo_rojo_dias', (int) $valor);
    }

    // ==================== HELPERS DEL SEMÁFORO ====================

    public static function getEstadoSemaforo($diasRestantes)
    {
        if (! self::getSemaforoActivo()) {
            return null;
        }

        if ($diasRestantes <= 0) {
            return 'rojo';
        }

        $verde = self::getSemaforoVerdeDias();
        $amarillo = self::getSemaforoAmarilloDias();

        if ($diasRestantes <= $amarillo) {
            return 'rojo';
        }

        if ($diasRestantes <= $verde + $amarillo) {
            return 'amarillo';
        }

        return 'verde';
    }

    /**
     * Crea o actualiza una configuración (las claves secretas se cifran en el evento saving).
     */
    public static function setValor(string $clave, $valor): bool
    {
        return self::actualizarConfiguracion($clave, $valor);
    }

    /**
     * Método auxiliar para actualizar configuraciones.
     *
     * @param  mixed  $valor
     */
    private static function actualizarConfiguracion($clave, $valor)
    {
        $valorString = (string) $valor;

        $config = self::where('clave', $clave)->first();

        if ($config) {
            $config->update(['valor' => $valorString]);
        } else {
            self::create([
                'clave' => $clave,
                'valor' => $valorString,
                'descripcion' => 'Configuración de '.str_replace('_', ' ', $clave),
            ]);
        }

        return true;
    }
}

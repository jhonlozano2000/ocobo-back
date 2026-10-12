<?php

namespace App\Models\Gestion;

use App\Models\Configuracion\ConfigDiviPoli;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionTercero extends Model
{
    use HasFactory;

    protected $table = 'gestion_terceros';

    protected $fillable = [
        'divi_poli_id',
        'num_docu_nit',
        'dv_nit',
        'nom_razo_soci',
        'direccion',
        'telefono',
        'email',
        'tipo',
        'notifica_email',
        'notifica_msm',
        'autoriza_tratamiento_datos',
        'fecha_autorizacion_datos',
    ];

    /**
     * Casts de atributos.
     * Ley 1581/2012 - Protección de datos personales (Habeas Data)
     *
     * @var array<string, string>
     */
    protected $casts = [
        'notifica_email' => 'boolean',
        'notifica_msm' => 'boolean',
        'autoriza_tratamiento_datos' => 'boolean',
        'fecha_autorizacion_datos' => 'datetime',
        'dv_nit' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (GestionTercero $tercero) {
            // M16-04: Registrar fecha si se autoriza tratamiento de datos
            if ($tercero->autoriza_tratamiento_datos && ! $tercero->fecha_autorizacion_datos) {
                $tercero->fecha_autorizacion_datos = now();
            } elseif (! $tercero->autoriza_tratamiento_datos) {
                $tercero->fecha_autorizacion_datos = null;
            }

            // M16-07: Calcular DV automáticamente para personas jurídicas / NIT numérico
            if ($tercero->tipo === 'Juridico' && $tercero->num_docu_nit && $tercero->dv_nit === null) {
                $tercero->dv_nit = self::calcularDvNit($tercero->num_docu_nit);
            }
        });
    }

    /**
     * Calcula el dígito de verificación DIAN (Módulo 11) para un NIT.
     */
    public static function calcularDvNit(string $nit): int
    {
        $nitLimpio = preg_replace('/\D/', '', $nit);
        if (empty($nitLimpio)) {
            return 0;
        }

        $pesos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
        $longitud = strlen($nitLimpio);
        $suma = 0;

        for ($i = 0; $i < $longitud; $i++) {
            $digito = (int) $nitLimpio[$longitud - 1 - $i];
            $peso = $pesos[$i] ?? 0;
            $suma += $digito * $peso;
        }

        $residuo = $suma % 11;

        return $residuo > 1 ? 11 - $residuo : $residuo;
    }

    public function divisionPolitica()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'divi_poli_id');
    }
}

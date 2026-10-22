<?php

namespace App\Services\OfiArchivo;

use App\Models\Configuracion\ConfigDiviPoli;
use App\Models\Configuracion\ConfigVarias;
use App\Models\User;
use App\Models\VentanillaUnica\Recibidos\VentanillaRadicaReci;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Variables de las plantillas generadas: datos que se llenan solos al crear un documento desde la plantilla.
 *
 * En el contenido (JSON de TipTap) son nodos en línea «variable» ({type: 'variable', attrs: {clave, mayusculas}});
 * en los textos del encabezado y pie (texto_libre), la clave entre llaves: «{entidad_direccion}». Si el dato no
 * está configurado, el nodo pasa a ser un campo por completar con la etiqueta de la variable.
 *
 * Las del radicado (combinación de correspondencia) toman los datos del radicado recibido al que responde el
 * documento y de su remitente.
 */
class VariablesPlantillaService
{
    /** clave => etiqueta */
    public const VARIABLES = [
        'fecha' => 'Fecha',
        'ciudad' => 'Ciudad',
        'usuario_nombre' => 'Nombre del usuario',
        'usuario_cargo' => 'Cargo del usuario',
        'usuario_dependencia' => 'Dependencia del usuario',
        'entidad_nombre' => 'Razón social de la entidad',
        'entidad_nit' => 'NIT de la entidad',
        'entidad_direccion' => 'Dirección de la entidad',
        'entidad_telefono' => 'Teléfono de la entidad',
        'entidad_correo' => 'Correo de la entidad',
        'entidad_web' => 'Sitio web de la entidad',
        'radicado_numero' => 'Número de radicado',
        'radicado_fecha' => 'Fecha de radicación',
        'radicado_asunto' => 'Asunto del radicado',
        'radicado_fecha_documento' => 'Fecha del documento recibido',
        'remitente_nombre' => 'Nombre del remitente',
        'remitente_documento' => 'Documento del remitente',
        'remitente_direccion' => 'Dirección del remitente',
        'remitente_ciudad' => 'Ciudad del remitente',
        'remitente_telefono' => 'Teléfono del remitente',
        'remitente_correo' => 'Correo del remitente',
    ];

    /** Prefijos de las variables del radicado: el grupo «Radicado» del catálogo. */
    private const PREFIJOS_RADICADO = ['radicado_', 'remitente_'];

    /** Separador de los datos de contacto en el texto del pie: si un dato falta, se quita con su separador. */
    private const SEPARADOR = ' · ';

    /**
     * Catálogo para el editor: clave, etiqueta y grupo (general o radicado).
     *
     * @return list<array{clave: string, etiqueta: string, grupo: string}>
     */
    public function catalogo(): array
    {
        return collect(self::VARIABLES)
            ->map(fn ($etiqueta, $clave) => [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'grupo' => Str::startsWith($clave, self::PREFIJOS_RADICADO) ? 'radicado' : 'general',
            ])
            ->values()
            ->all();
    }

    /**
     * Valor de cada variable para el usuario y, si hay, el radicado recibido (vacías sin radicado).
     *
     * @return array<string, string>
     */
    public function valores(User $user, ?VentanillaRadicaReci $radicado = null): array
    {
        return $this->valoresGenerales($user) + $this->valoresRadicado($radicado);
    }

    /**
     * Datos del radicado y de su remitente.
     *
     * @return array<string, string>
     */
    public function valoresRadicado(?VentanillaRadicaReci $radicado): array
    {
        $radicado?->loadMissing('tercero.divisionPolitica');
        $tercero = $radicado?->tercero;
        $fecha = fn ($valor) => $valor ? Carbon::parse($valor)->locale('es')->translatedFormat('j \d\e F \d\e Y') : '';

        return array_map(fn ($valor) => trim((string) $valor), [
            'radicado_numero' => $radicado?->num_radicado ?? '',
            'radicado_fecha' => $fecha($radicado?->created_at),
            'radicado_asunto' => $radicado?->asunto ?? '',
            'radicado_fecha_documento' => $fecha($radicado?->fec_docu),
            'remitente_nombre' => $tercero?->nom_razo_soci ?? '',
            'remitente_documento' => $tercero?->num_docu_nit ?? '',
            'remitente_direccion' => $tercero?->direccion ?? '',
            'remitente_ciudad' => $tercero?->divisionPolitica?->nombre ?? '',
            'remitente_telefono' => $tercero?->telefono ?? '',
            'remitente_correo' => $tercero?->email ?? '',
        ]);
    }

    /**
     * Fecha, ciudad y datos del usuario y de la entidad.
     *
     * @return array<string, string>
     */
    private function valoresGenerales(User $user): array
    {
        $empresa = ConfigVarias::getInformacionEmpresa();
        $diviPoliId = $empresa['divi_poli_id_empresa'] ?? null;

        return array_map(fn ($valor) => trim((string) $valor), [
            'fecha' => Carbon::now()->locale('es')->translatedFormat('j \d\e F \d\e Y'),
            'ciudad' => $diviPoliId ? (ConfigDiviPoli::find($diviPoliId)?->nombre ?? '') : '',
            'usuario_nombre' => trim(($user->nombres ?? '').' '.($user->apellidos ?? '')),
            'usuario_cargo' => $user->cargo?->nombre ?? '',
            'usuario_dependencia' => $user->cargo?->dependencia?->nombre ?? '',
            'entidad_nombre' => $empresa['razon_social_empresa'] ?? '',
            'entidad_nit' => $empresa['nit_empresa'] ?? '',
            'entidad_direccion' => $empresa['direccion_empresa'] ?? '',
            'entidad_telefono' => $empresa['telefono_empresa'] ?? '',
            'entidad_correo' => $empresa['correo_electronico_empresa'] ?? '',
            'entidad_web' => $empresa['web_empresa'] ?? '',
        ]);
    }

    /** Etiqueta de una variable (la clave si no se conoce). */
    public function etiqueta(string $clave): string
    {
        return self::VARIABLES[$clave] ?? $clave;
    }

    /**
     * Contenido con las variables reemplazadas por su valor (texto con las mismas marcas) o, si está vacío, por
     * un campo por completar.
     */
    public function resolverContenido(array $nodo, array $valores): array
    {
        if (($nodo['type'] ?? null) === 'variable') {
            $clave = (string) ($nodo['attrs']['clave'] ?? '');
            $valor = $valores[$clave] ?? '';
            if (! empty($nodo['attrs']['mayusculas'])) {
                $valor = mb_strtoupper($valor);
            }
            $resuelto = $valor !== ''
                ? ['type' => 'text', 'text' => $valor]
                : ['type' => 'campo', 'attrs' => ['etiqueta' => $this->etiqueta($clave)]];

            return empty($nodo['marks']) ? $resuelto : $resuelto + ['marks' => $nodo['marks']];
        }

        if (isset($nodo['content']) && is_array($nodo['content'])) {
            $nodo['content'] = array_map(fn ($hijo) => is_array($hijo) ? $this->resolverContenido($hijo, $valores) : $hijo, $nodo['content']);
        }

        return $nodo;
    }

    /** Encabezado o pie con las variables del texto libre reemplazadas. */
    public function resolverFranja(?array $franja, array $valores): ?array
    {
        if (! is_array($franja) || ! isset($franja['texto_libre']) || ! is_string($franja['texto_libre'])) {
            return $franja;
        }

        $franja['texto_libre'] = $this->resolverTexto($franja['texto_libre'], $valores);

        return $franja;
    }

    /**
     * «{entidad_direccion} · Tel. {entidad_telefono}» → «Calle 1 · Tel. 123». Los tramos (separados por « · »)
     * que solo tenían variables vacías se quitan para no dejar «Tel. ·».
     */
    public function resolverTexto(string $texto, array $valores): string
    {
        $patron = '/\{('.implode('|', array_keys(self::VARIABLES)).')\}/u';

        $tramos = [];
        foreach (explode(self::SEPARADOR, $texto) as $tramo) {
            $conValor = false;
            $resuelto = preg_replace_callback($patron, function ($m) use ($valores, &$conValor) {
                $valor = $valores[$m[1]] ?? '';
                $conValor = $conValor || $valor !== '';

                return $valor;
            }, $tramo, -1, $variables);

            if ($variables === 0 || $conValor) {
                $tramos[] = trim($resuelto);
            }
        }

        return implode(self::SEPARADOR, array_filter($tramos, fn ($t) => $t !== ''));
    }
}

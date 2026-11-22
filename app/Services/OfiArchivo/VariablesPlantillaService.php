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
        'fecha_corta' => 'Fecha corta',
        'hora' => 'Hora actual',
        'anio' => 'Año actual',
        'usuario_correo' => 'Correo del usuario',
        'usuario_dependencia_codigo' => 'Código de dependencia del usuario',
        'usuario_dependencia_sigla' => 'Sigla de dependencia del usuario',
        'radicado_hora' => 'Hora de radicación',
        'radicado_vencimiento' => 'Fecha de vencimiento',
        'radicado_codigo_verificacion' => 'Código de verificación',
        'radicado_medio_recepcion' => 'Medio de recepción',
        'radicado_folios' => 'Cantidad de folios',
        'radicado_anexos' => 'Cantidad de anexos',
        'radicado_descripcion_anexos' => 'Descripción de anexos',
        'radicado_dias_vencimiento' => 'Plazo de vencimiento en días',
        'radicado_pqrs_tipo' => 'Tipo de PQRSD',
        'radicado_pqrs_prioridad' => 'Prioridad de PQRSD',
        'remitente_tipo_documento' => 'Tipo de documento del remitente',
        'remitente_tipo_persona' => 'Tipo de persona del remitente',
        'remitente_departamento' => 'Departamento del remitente',
        'remitente_pais' => 'País del remitente',
        'radicado_clasificacion_codigo' => 'Código de clasificación documental',
        'radicado_clasificacion_nombre' => 'Nombre de clasificación documental',
        'radicado_serie_nombre' => 'Serie',
        'radicado_serie_codigo' => 'Código de serie',
        'radicado_subserie_nombre' => 'Subserie',
        'radicado_subserie_codigo' => 'Código de subserie',
        'radicado_tipo_documental_nombre' => 'Tipo documental',
        'radicado_tipo_documental_codigo' => 'Código de tipo documental',

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
        'radicado_asunto' => 'Asunto del radicado recibido',
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
        $camposGrupo = ['documento_asunto' => 'Asunto del documento'];
        foreach (['firmantes', 'revisores', 'proyectores', 'responsables', 'aprobadores'] as $rol) {
            foreach (['nombre', 'cargo', 'dependencia'] as $propiedad) {
                $camposGrupo['grupo_'.$rol.'_'.$propiedad] = ucfirst($rol).' ('.$propiedad.')';
            }
        }

        return collect(self::VARIABLES + $camposGrupo)
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
        $radicado?->loadMissing(['tercero.divisionPolitica.padre.padre', 'medioRecepcion', 'pqrs.tipoPqrs', 'clasificacionDocumental']);
        $clasificacion = $radicado?->clasificacionDocumental;
        $jerarquia = collect($clasificacion?->getJerarquia() ?? []);
        $tercero = $radicado?->tercero;
        $fecha = fn ($valor) => $valor ? Carbon::parse($valor)->locale('es')->translatedFormat('j \d\e F \d\e Y') : '';

        return array_map(fn ($valor) => trim((string) $valor), [
            'radicado_hora' => $radicado?->created_at?->format('H:i:s') ?? '',
            'radicado_vencimiento' => $fecha($radicado?->fec_venci),
            'radicado_codigo_verificacion' => $radicado?->cod_verifica ?? '',
            'radicado_medio_recepcion' => $radicado?->medioRecepcion?->nombre ?? '',
            'radicado_folios' => $radicado?->num_folios ?? '',
            'radicado_anexos' => $radicado?->num_anexos ?? '',
            'radicado_descripcion_anexos' => $radicado?->descrip_anexos ?? '',
            'radicado_dias_vencimiento' => $radicado?->dias_vencimiento ?? '',
            'radicado_pqrs_tipo' => $radicado?->pqrs?->tipoPqrs?->nombre ?? '',
            'radicado_pqrs_prioridad' => $radicado?->pqrs?->prioridad ?? '',
            'remitente_tipo_documento' => $tercero?->tipo_docu ?? '',
            'remitente_tipo_persona' => $tercero?->tipo ?? '',
            'remitente_departamento' => $tercero?->divisionPolitica?->padre?->nombre ?? '',
            'remitente_pais' => $tercero?->divisionPolitica?->padre?->padre?->nombre ?? '',
            'radicado_clasificacion_codigo' => $clasificacion?->getCodigoCompleto() ?? '',
            'radicado_clasificacion_nombre' => $clasificacion?->getNombreCompleto() ?? '',
            'radicado_serie_nombre' => $jerarquia->firstWhere('tipo', 'Serie')['nom'] ?? '',
            'radicado_serie_codigo' => $jerarquia->firstWhere('tipo', 'Serie')['cod'] ?? '',
            'radicado_subserie_nombre' => $jerarquia->firstWhere('tipo', 'SubSerie')['nom'] ?? '',
            'radicado_subserie_codigo' => $jerarquia->firstWhere('tipo', 'SubSerie')['cod'] ?? '',
            'radicado_tipo_documental_nombre' => $jerarquia->firstWhere('tipo', 'TipoDocumento')['nom'] ?? '',
            'radicado_tipo_documental_codigo' => $jerarquia->firstWhere('tipo', 'TipoDocumento')['cod'] ?? '',
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
            'fecha_corta' => Carbon::now()->format('d/m/Y'),
            'hora' => Carbon::now()->format('H:i:s'),
            'anio' => Carbon::now()->format('Y'),
            'usuario_correo' => $user->email ?? '',
            'usuario_dependencia_codigo' => $user->cargo?->dependencia?->cod_organico ?? '',
            'usuario_dependencia_sigla' => $user->cargo?->dependencia?->sigla ?? '',
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
        // Las plantillas institucionales anteriores usan este marcador en el rengl?n de asunto.
        $linea = $nodo['content'] ?? [];
        if (($nodo['type'] ?? null) === 'paragraph' && count($linea) === 2
            && strtoupper(trim($linea[0]['text'] ?? '')) === 'ASUNTO:'
            && ($linea[1]['type'] ?? null) === 'campo' && ($linea[1]['attrs']['etiqueta'] ?? null) === 'Asunto') {
            $nodo['content'][1]['type'] = 'campoGrupo';
            $nodo['content'][1]['attrs'] = ['id' => (string) Str::uuid(), 'rol' => 'grupo', 'propiedad' => 'asunto',
                'valor' => '', 'linked' => true, 'manual_override' => false];

            return $nodo;
        }

        if (($nodo['type'] ?? null) === 'variable') {
            $clave = (string) ($nodo['attrs']['clave'] ?? '');
            if ($clave === 'documento_asunto') {
                $campo = ['type' => 'campoGrupo', 'attrs' => [
                    'id' => (string) Str::uuid(), 'rol' => 'grupo', 'propiedad' => 'asunto',
                    'valor' => '', 'linked' => true, 'manual_override' => false,
                ]];

                return empty($nodo['marks']) ? $campo : $campo + ['marks' => $nodo['marks']];
            }
            if (preg_match('/^grupo_(firmantes|revisores|proyectores|responsables|aprobadores)_(nombre|cargo|dependencia)$/D', $clave, $m)) {
                $campo = ['type' => 'campoGrupo', 'attrs' => ['id' => (string) Str::uuid(), 'rol' => $m[1],
                    'propiedad' => $m[2], 'linked' => true, 'manual_override' => false, 'valor' => '']];

                return empty($nodo['marks']) ? $campo : $campo + ['marks' => $nodo['marks']];
            }
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

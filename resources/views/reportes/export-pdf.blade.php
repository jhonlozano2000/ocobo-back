<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 138px 32px 56px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #1E293B; }

        header { position: fixed; top: -118px; left: 0; right: 0; height: 100px; }
        header .banda { background: #0F172A; color: #FFFFFF; font-size: 10pt; font-weight: bold; padding: 5px 10px; }
        header table { width: 100%; border-collapse: collapse; border-bottom: 2px solid {{ $color }}; }
        header td { vertical-align: middle; padding: 6px 0 6px 0; }
        header .logo { width: 70px; padding-left: 4px; }
        header .logo img { max-height: 44px; max-width: 64px; }
        .entidad { font-size: 11pt; font-weight: bold; color: {{ $color }}; }
        .nit { font-size: 7pt; color: #64748B; margin-top: 2px; }
        header .meta { text-align: right; font-size: 7pt; color: #475569; padding-right: 4px; }
        .titulo { font-size: 10pt; font-weight: bold; color: #0F172A; text-transform: uppercase; margin-bottom: 2px; }
        .registros { display: inline-block; margin-top: 3px; padding: 2px 8px; background: #E0F2FE; color: #0369A1; font-weight: bold; border-radius: 8px; }

        footer { position: fixed; bottom: -38px; left: 0; right: 0; height: 18px; border-top: 1px solid #E2E8F0; padding-top: 5px; font-size: 7pt; color: #94A3B8; }
        footer table { width: 100%; border-collapse: collapse; }

        .filtros { margin-bottom: 10px; }
        .filtro { display: inline-block; background: #E0F2FE; color: #0369A1; border-radius: 4px; padding: 3px 8px; margin: 0 4px 4px 0; font-size: 7pt; }
        .filtro b { color: #0F172A; }
        .nota { background: #FEF3C7; color: #B45309; border-radius: 4px; padding: 5px 8px; margin-bottom: 10px; font-size: 7pt; }

        table.datos { width: 100%; border-collapse: collapse; }
        table.datos thead { display: table-header-group; }
        table.datos th { background: {{ $color }}; color: #FFFFFF; padding: 6px 5px; text-align: left; font-size: 7pt; font-weight: bold; border: 1px solid #0369A1; }
        table.datos td { padding: 5px; border: 1px solid #CBD5E1; vertical-align: top; }
        table.datos tr { page-break-inside: avoid; }
        table.datos tr.alterna td { background: #F8FAFC; }
        table.datos tr.total td { background: #E2E8F0; font-weight: bold; }
        .vacio { text-align: center; color: #64748B; font-style: italic; padding: 18px; }
    </style>
</head>
<body>
    <header>
        <div class="banda">{{ $sistema }}</div>
        <table>
            <tr>
                @if($logo)
                <td class="logo"><img src="{{ $logo }}" alt=""></td>
                @endif
                <td>
                    <div class="entidad">{{ $entidad }}</div>
                    @if($nit)
                    <div class="nit">NIT: {{ $nit }}</div>
                    @endif
                </td>
                <td class="meta">
                    <div class="titulo">{{ $titulo }}</div>
                    <div>Emisión: {{ $emision }}</div>
                    @if($generadoPor)
                    <div>Generado por: {{ $generadoPor }}</div>
                    @endif
                    <div class="registros">{{ $total }} registros</div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table>
            <tr>
                {{-- La numeración "Página N de M" la dibuja el servicio a la derecha --}}
                <td>Sistema de Gestión Documental Electrónica de Archivo — OCOBO · {{ $entidad }}</td>
            </tr>
        </table>
    </footer>

    @if(!empty($resumen))
    <div class="filtros">
        @foreach($resumen as $etiqueta => $valor)
        <span class="filtro"><b>{{ $etiqueta }}:</b> {{ $valor }}</span>
        @endforeach
    </div>
    @endif

    @if($nota)
    <div class="nota">{{ $nota }}</div>
    @endif

    <table class="datos">
        <thead>
            <tr>
                @foreach($columnas as $columna)
                <th>{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($datos as $fila)
            <tr class="{{ $loop->even ? 'alterna' : '' }}">
                @foreach($fila as $valor)
                <td>{{ $valor }}</td>
                @endforeach
            </tr>
            @empty
            <tr><td colspan="{{ max(count($columnas), 1) }}" class="vacio">Sin datos</td></tr>
            @endforelse
            @if(count($datos) > 0)
            <tr class="total">
                <td colspan="{{ max(count($columnas), 1) }}" style="text-align: right;">Total registros: {{ $total }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>

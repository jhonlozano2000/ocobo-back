<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 118px 32px 56px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #444050; }

        header { position: fixed; top: -94px; left: 0; right: 0; height: 76px; border-bottom: 2px solid {{ $color }}; }
        header table { width: 100%; border-collapse: collapse; }
        header td { vertical-align: middle; padding: 0; }
        header .logo { width: 96px; }
        header .logo img { max-height: 60px; max-width: 88px; }
        .entidad { font-size: 12pt; font-weight: bold; color: {{ $color }}; }
        .titulo { font-size: 11pt; font-weight: bold; color: #2F2B3D; margin-top: 3px; }
        .meta { font-size: 7pt; color: #808390; margin-top: 4px; }

        footer { position: fixed; bottom: -38px; left: 0; right: 0; height: 18px; border-top: 1px solid #E6E6E8; padding-top: 5px; font-size: 7pt; color: #808390; }
        footer table { width: 100%; border-collapse: collapse; }

        .filtros { margin-bottom: 10px; }
        .filtro { display: inline-block; background: #EEEDFD; color: {{ $color }}; border-radius: 4px; padding: 3px 8px; margin: 0 4px 4px 0; font-size: 7pt; }
        .filtro b { color: #2F2B3D; }
        .nota { background: #FFF4E5; color: #B26A00; border-radius: 4px; padding: 5px 8px; margin-bottom: 10px; font-size: 7pt; }

        table.datos { width: 100%; border-collapse: collapse; }
        table.datos thead { display: table-header-group; }
        table.datos th { background: {{ $color }}; color: #FFFFFF; padding: 6px 5px; text-align: left; font-size: 7pt; font-weight: bold; text-transform: uppercase; }
        table.datos td { padding: 5px; border-bottom: 1px solid #E6E6E8; vertical-align: top; }
        table.datos tr { page-break-inside: avoid; }
        table.datos tr.alterna td { background: #F8F7FA; }
        .vacio { text-align: center; color: #808390; font-style: italic; padding: 18px; }
    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                @if($logo)
                <td class="logo"><img src="{{ $logo }}" alt=""></td>
                @endif
                <td>
                    <div class="entidad">{{ $entidad }}</div>
                    <div class="titulo">{{ $titulo }}</div>
                    <div class="meta">{{ $meta }}</div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        <table>
            <tr>
                {{-- La numeración "Página N de M" la dibuja el servicio a la derecha --}}
                <td>{{ $entidad }} · {{ $titulo }}</td>
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
        </tbody>
    </table>
</body>
</html>

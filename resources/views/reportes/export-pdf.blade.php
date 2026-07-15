<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; }
        h1 { font-size: 14pt; text-align: center; margin-bottom: 5px; }
        .fecha { text-align: center; color: #666; margin-bottom: 15px; }
        .resumen { margin-bottom: 15px; }
        .resumen table { width: 100%; border-collapse: collapse; }
        .resumen td { padding: 4px 8px; }
        .resumen .label { font-weight: bold; width: 200px; }
        table.data { width: 100%; border-collapse: collapse; font-size: 8pt; }
        table.data th { background: #E8EAF6; padding: 6px 4px; border: 1px solid #999; text-align: center; font-weight: bold; }
        table.data td { padding: 4px; border: 1px solid #ccc; }
        table.data tr:nth-child(even) { background: #f9f9f9; }
        .total { text-align: right; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="fecha">Generado: {{ $fecha_generacion }}</div>

    @if(!empty($resumen))
    <div class="resumen">
        <table>
            @foreach($resumen as $label => $value)
            <tr>
                <td class="label">{{ $label }}:</td>
                <td>{{ $value }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <table class="data">
        <thead>
            <tr>
                @foreach($columnas as $col)
                <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($datos as $item)
            <tr>
                @foreach($columnas as $col)
                <td>{{ $item[$col] ?? $item[strtolower($col)] ?? '' }}</td>
                @endforeach
            </tr>
            @empty
            <tr><td colspan="{{ count($columnas) }}" style="text-align:center">Sin datos</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="total">Total registros: {{ $total }}</div>
</body>
</html>

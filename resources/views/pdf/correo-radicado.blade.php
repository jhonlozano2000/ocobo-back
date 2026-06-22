<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Correo Radicado - {{ $radicado['num_radicado'] ?? '' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.5;
        }

        .header {
            background: #1a5276;
            color: white;
            padding: 15px 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 { font-size: 16px; margin-bottom: 4px; }
        .header .subtitle { font-size: 10px; opacity: 0.85; }

        .radicado-bar {
            background: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .radicado-bar .num {
            font-size: 14px;
            font-weight: bold;
            color: #1a5276;
        }
        .radicado-bar .tipo {
            font-size: 10px;
            background: #1a5276;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            text-transform: uppercase;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 6px 10px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        .info-table .label {
            background: #f8f8f8;
            font-weight: bold;
            width: 25%;
            color: #555;
        }

        .email-content {
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .email-content .email-header {
            background: #fafafa;
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
        }
        .email-content .email-header .field {
            margin-bottom: 4px;
            font-size: 10px;
        }
        .email-content .email-header .field strong {
            color: #555;
        }
        .email-content .email-body {
            padding: 15px;
            max-height: 400px;
            overflow: hidden;
            font-size: 11px;
        }
        .email-content .email-body img {
            max-width: 100%;
            height: auto;
        }

        .adjuntos {
            margin-bottom: 20px;
        }
        .adjuntos h3 {
            font-size: 11px;
            color: #555;
            margin-bottom: 6px;
        }
        .adjuntos ul {
            list-style: none;
            padding: 0;
        }
        .adjuntos li {
            padding: 3px 0;
            font-size: 10px;
            color: #666;
        }
        .adjuntos li::before {
            content: "📎 ";
        }

        .footer {
            border-top: 2px solid #1a5276;
            padding-top: 10px;
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #888;
        }
        .footer .hash {
            font-family: 'Courier New', monospace;
            font-size: 8px;
            word-break: break-all;
            margin-top: 4px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $entidad }}</h1>
        <div class="subtitle">Documento Radicado - Copia del Correo Electrónico</div>
    </div>

    <div class="radicado-bar">
        <span class="num">Radicado No. {{ $radicado['num_radicado'] ?? 'N/A' }}</span>
        <span class="tipo">{{ $radicado['tipo'] ?? 'Recibido' }}</span>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Fecha de Radicación:</td>
            <td>{{ $fechaGeneracion }}</td>
        </tr>
        <tr>
            <td class="label">Tipo de Radicado:</td>
            <td>{{ $radicado['tipo'] ?? 'Recibido' }}</td>
        </tr>
        <tr>
            <td class="label">Remitente:</td>
            <td>{{ $email['remitente_nombre'] ?? 'N/A' }} ({{ $email['remitente_email'] ?? 'N/A' }})</td>
        </tr>
        <tr>
            <td class="label">Fecha del Correo:</td>
            <td>{{ $email['fecha_correo'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Asunto:</td>
            <td>{{ $email['asunto'] ?? 'Sin asunto' }}</td>
        </tr>
    </table>

    <div class="email-content">
        <div class="email-header">
            <div class="field"><strong>De:</strong> {{ $email['remitente_nombre'] ?? '' }} &lt;{{ $email['remitente_email'] ?? '' }}&gt;</div>
            <div class="field"><strong>Fecha:</strong> {{ $email['fecha_correo'] ?? '' }}</div>
            <div class="field"><strong>Asunto:</strong> {{ $email['asunto'] ?? 'Sin asunto' }}</div>
        </div>
        <div class="email-body">
            {!! $email['body_html'] ?? '<p style="color:#999;">Sin contenido HTML</p>' !!}
        </div>
    </div>

    @if(!empty($email['adjuntos_info']) && count($email['adjuntos_info']) > 0)
    <div class="adjuntos">
        <h3>Archivos Adjuntos Originales ({{ count($email['adjuntos_info']) }})</h3>
        <ul>
            @foreach($email['adjuntos_info'] as $adj)
                <li>{{ $adj['filename'] ?? $adj['name'] ?? 'Adjunto' }}{{ isset($adj['size']) ? ' ('.number_format($adj['size'] / 1024, 1).' KB)' : '' }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="footer">
        <p>Documento generado automáticamente por el Sistema de Gestión Documental - {{ $entidad }}</p>
        <p>Fecha de generación: {{ $fechaGeneracion }}</p>
    </div>
</body>
</html>

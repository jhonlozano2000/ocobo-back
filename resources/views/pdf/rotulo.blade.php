<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rótulo de Radicado</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            width: 100%;
            max-width: 280px;
        }
        
        .rotulo-container {
            border: 2px solid #000;
            padding: 8px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }
        
        .header h1 {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .header .nit {
            font-size: 9px;
            color: #666;
        }
        
        .radicado-info {
            margin-bottom: 6px;
        }
        
        .radicado-info .label {
            font-weight: bold;
            font-size: 10px;
        }
        
        .radicado-info .value {
            font-size: 12px;
            font-weight: bold;
        }
        
        .details {
            display: table;
            width: 100%;
            font-size: 9px;
        }
        
        .details .row {
            display: table-row;
        }
        
        .details .cell {
            display: table-cell;
            padding: 2px 4px;
            border: 1px solid #ccc;
        }
        
        .details .cell-label {
            font-weight: bold;
            background: #f5f5f5;
            width: 40%;
        }
        
        .footer {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #000;
            text-align: center;
            font-size: 8px;
            color: #666;
        }
        
        .barcode-placeholder {
            height: 40px;
            background: #f5f5f5;
            border: 1px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 4px 0;
            font-size: 9px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="rotulo-container">
        <div class="header">
            <h1>{{ $entidad }}</h1>
            <div class="nit">NIT: {{ $nit }}</div>
        </div>
        
        <div class="radicado-info">
            <div class="label">Radicado No.</div>
            <div class="value">{{ $radicado['num_radicado'] ?? 'N/A' }}</div>
        </div>
        
        <div class="details">
            <div class="row">
                <div class="cell cell-label">Fecha:</div>
                <div class="cell">{{ $radicado['fec_radi'] ?? $fecha }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Hora:</div>
                <div class="cell">{{ $radicado['hor_radi'] ?? $hora }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Remitente:</div>
                <div class="cell">{{ $radicado['tercero']['nom_razo_soci'] ?? 'N/A' }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Identificación:</div>
                <div class="cell">{{ $radicado['tercero']['num_identific'] ?? 'N/A' }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Folios:</div>
                <div class="cell">{{ $radicado['num_folios'] ?? '0' }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Anexos:</div>
                <div class="cell">{{ $radicado['num_anexos'] ?? '0' }}</div>
            </div>
        </div>
        
        <div class="barcode-placeholder">
            [Código de barras]
        </div>
        
        <div class="footer">
            <p>Consulte el estado de su trámite en www.entidad.gov.co</p>
            <p>Código de verificación: {{ $radicado['codigo_verificacion'] ?? 'N/A' }}</p>
        </div>
    </div>
</body>
</html>
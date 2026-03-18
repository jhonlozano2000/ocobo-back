<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acuse de recibo — {{ $numRadicado }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: #1B2F4E; color: #ffffff; padding: 28px 32px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; font-weight: 700; }
        .header p { margin: 6px 0 0; font-size: 14px; opacity: 0.85; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; margin-bottom: 20px; }
        .radicado-box { background: #F0F4FA; border-left: 4px solid #2563EB; border-radius: 4px; padding: 20px 24px; margin: 24px 0; }
        .radicado-num { font-size: 28px; font-weight: 700; color: #1B2F4E; letter-spacing: 1px; margin: 0 0 4px; }
        .radicado-label { font-size: 12px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid { display: table; width: 100%; border-collapse: collapse; margin: 24px 0; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-size: 13px; color: #6B7280; padding: 6px 0; width: 40%; vertical-align: top; }
        .info-value { display: table-cell; font-size: 13px; color: #111827; padding: 6px 0; font-weight: 500; vertical-align: top; }
        .cod-verifica { background: #ECFDF5; border: 1px dashed #10B981; border-radius: 6px; padding: 12px 16px; text-align: center; margin: 20px 0; }
        .cod-verifica p { margin: 0; font-size: 12px; color: #059669; }
        .cod-verifica strong { font-size: 18px; letter-spacing: 2px; color: #065F46; display: block; margin-top: 4px; }
        .aviso { background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 6px; padding: 14px 16px; font-size: 13px; color: #92400E; margin: 20px 0; }
        .footer { background: #F9FAFB; padding: 20px 32px; text-align: center; font-size: 12px; color: #9CA3AF; border-top: 1px solid #E5E7EB; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $nombreEntidad }}</h1>
            <p>Acuse de recibo de correspondencia</p>
        </div>

        <div class="body">
            <p class="greeting">Estimado/a <strong>{{ $nombreTercero }}</strong>,</p>

            <p>Su documento ha sido recibido y radicado exitosamente en nuestro sistema. A continuación encontrará los datos de su radicado:</p>

            <div class="radicado-box">
                <p class="radicado-label">Número de radicado</p>
                <p class="radicado-num">{{ $numRadicado }}</p>
            </div>

            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Fecha de radicación</span>
                    <span class="info-value">{{ $fechaRadicado }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Asunto</span>
                    <span class="info-value">{{ $asunto ?? 'Sin asunto' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha límite de respuesta</span>
                    <span class="info-value">{{ $fecVenci }}</span>
                </div>
            </div>

            @if($codVerifica)
            <div class="cod-verifica">
                <p>Código de verificación (consérvelo para consultas)</p>
                <strong>{{ $codVerifica }}</strong>
            </div>
            @endif

            <div class="aviso">
                <strong>Importante:</strong> Este es un acuse de recibo automático. La entidad dará respuesta dentro de los términos legales establecidos.
                Guarde este número de radicado para hacer seguimiento a su solicitud.
            </div>

            <p>Si tiene alguna inquietud, comuníquese con nuestra ventanilla de atención.</p>

            <p>Cordialmente,<br><strong>{{ $nombreEntidad }}</strong></p>
        </div>

        <div class="footer">
            <p>Este mensaje es generado automáticamente por el Sistema de Gestión Documental OCOBO.</p>
            <p>Por favor no responda este correo.</p>
        </div>
    </div>
</body>
</html>

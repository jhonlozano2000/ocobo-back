<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>{{ config('app.name') }} - Notificación PQRS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        
        .email-wrapper {
            width: 100%;
            background-color: #f8f9fa;
            padding: 40px 0;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .email-header {
            background: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%);
            padding: 30px;
            text-align: center;
        }
        
        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .email-header .subtitle {
            color: rgba(255, 255, 255, 0.9);
            margin: 8px 0 0 0;
            font-size: 14px;
            font-weight: 400;
        }
        
        .email-body {
            padding: 40px 30px;
        }
        
        .notification-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .pqrs-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 24px;
            margin: 20px 0;
            border-left: 4px solid #1976d2;
        }
        
        .pqrs-number {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
        }
        
        .pqrs-type {
            font-size: 14px;
            font-weight: 600;
            color: #34495e;
            margin-bottom: 16px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 20px;
        }
        
        .info-item {
            background-color: #ffffff;
            padding: 16px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .estado-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .estado-pendiente { background-color: #fff3e0; color: #f57c00; }
        .estado-tramite { background-color: #e3f2fd; color: #1976d2; }
        .estado-respondida { background-color: #e8f5e9; color: #2e7d32; }
        .estado-vencida { background-color: #ffebee; color: #d32f2f; }
        
        .mensaje-personalizado {
            background-color: #f1f8e9;
            padding: 20px;
            border-radius: 8px;
            margin-top: 24px;
            border-left: 4px solid #689f38;
        }
        
        .mensaje-personalizado p {
            margin: 0;
            color: #33691e;
            font-size: 14px;
            white-space: pre-wrap;
        }
        
        .email-footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .footer-text {
            color: #6c757d;
            font-size: 12px;
            margin: 0;
        }
        
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0 20px;
                border-radius: 8px;
            }
            
            .email-body {
                padding: 30px 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .email-header {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header -->
            <div class="email-header">
                <h1>{{ config('app.name') }}</h1>
                <p class="subtitle">Ventanilla Única - PQRS</p>
            </div>
            
            <!-- Body -->
            <div class="email-body">
                <!-- Notification Badge -->
                <div class="notification-badge">
                    Notificación PQRS
                </div>
                
                <!-- Main Message -->
                <h2 style="color: #2c3e50; font-size: 20px; font-weight: 600; margin: 0 0 16px 0;">
                    Su {{ $pqrs->tipoPqrs?->nombre ?? 'PQRS' }} ha sido registrada
                </h2>
                
                <p style="color: #6c757d; font-size: 14px; margin: 0 0 24px 0;">
                    Estimado/a {{ $pqrs->nom_afectado ?? 'usuario' }}, le informamos que su solicitud ha sido radicada exitosamente en nuestro sistema.
                </p>
                
                <!-- PQRS Card -->
                <div class="pqrs-card">
                    <div class="pqrs-number">
                        Radicado N° {{ $pqrs->radicado?->num_radicado ?? 'En proceso' }}
                    </div>
                    
                    <div class="pqrs-type">
                        Tipo: {{ $pqrs->tipoPqrs?->nombre ?? 'No especificado' }}
                    </div>
                    
                    <!-- Info Grid -->
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Estado</div>
                            <div class="info-value">
                                <span class="estado-badge 
                                    @if($pqrs->estado_tramite === 'Pendiente') estado-pendiente
                                    @elseif($pqrs->estado_tramite === 'En Tramite') estado-tramite
                                    @elseif($pqrs->estado_tramite === 'Respondida') estado-respondida
                                    @elseif($pqrs->estado_tramite === 'Vencida') estado-vencida
                                    @endif">
                                    {{ $pqrs->estado_tramite ?? 'Pendiente' }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Prioridad</div>
                            <div class="info-value">
                                {{ $pqrs->prioridad ?? 'Normal' }}
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Fecha de Radicación</div>
                            <div class="info-value">
                                {{ $pqrs->fechor_tramite ? \Carbon\Carbon::parse($pqrs->fechor_tramite)->format('d/m/Y') : 'N/A' }}
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Fecha Límite</div>
                            <div class="info-value" style="color: {{ $pqrs->fecha_vencimiento && \Carbon\Carbon::parse($pqrs->fecha_vencimiento)->isPast() ? '#d32f2f' : '#2c3e50' }};">
                                {{ $pqrs->fecha_vencimiento ? \Carbon\Carbon::parse($pqrs->fecha_vencimiento)->format('d/m/Y') : 'Por definir' }}
                            </div>
                        </div>
                    </div>
                    
                    @if($pqrs->detalle_solicitud)
                    <div style="margin-top: 20px;">
                        <div class="info-label">Detalle de la Solicitud</div>
                        <div style="background-color: #ffffff; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef; margin-top: 8px; font-size: 14px; color: #2c3e50;">
                            {{ $pqrs->detalle_solicitud }}
                        </div>
                    </div>
                    @endif
                </div>
                
                <!-- Mensaje Personalizado -->
                @if($mensajePersonalizado)
                <div class="mensaje-personalizado">
                    <p><strong>Mensaje adicional:</strong></p>
                    <p>{{ $mensajePersonalizado }}</p>
                </div>
                @endif
                
                <!-- Action Message -->
                <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; margin-top: 24px; border-left: 4px solid #1976d2;">
                    <p style="margin: 0; color: #1565c0; font-size: 14px; font-weight: 500;">
                        <strong>Información importante:</strong> 
                        Su solicitud será tramitada dentro de los términos establecidos por la Ley 1755 de 2015. 
                        Podrá consultar el estado de su trámite en cualquier momento a través de nuestro sistema.
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="email-footer">
                <p class="footer-text">
                    © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.<br>
                    Este es un mensaje automático, por favor no responda a este correo.
                </p>
            </div>
        </div>
    </div>
</body>
</html>

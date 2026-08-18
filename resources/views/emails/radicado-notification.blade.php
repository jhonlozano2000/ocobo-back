<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>{{ config('app.name') }} - Notificación de Radicado</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }
        
        .badge-assignment {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-update {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .badge-due-date {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .radicado-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 24px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        
        .radicado-number {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
        }
        
        .radicado-subject {
            font-size: 16px;
            font-weight: 600;
            color: #34495e;
            margin-bottom: 16px;
            line-height: 1.4;
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
        
        .footer-link {
            color: #667eea;
            text-decoration: none;
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
                <p class="subtitle">Sistema de Gestión Documental</p>
            </div>
            
            <!-- Body -->
            <div class="email-body">
                <!-- Notification Badge -->
                <div class="notification-badge 
                    @if($tipo === 'asignacion') badge-assignment
                    @elseif($tipo === 'actualizacion') badge-update  
                    @elseif($tipo === 'vencimiento') badge-due-date
                    @endif">
                    @if($tipo === 'asignacion')
                        📋 Nuevo Radicado Asignado
                    @elseif($tipo === 'actualizacion')
                        🔄 Radicado Actualizado
                    @elseif($tipo === 'vencimiento')
                        ⏰ Fecha Límite Próxima
                    @endif
                </div>
                
                <!-- Main Message -->
                <h2 style="color: #2c3e50; font-size: 20px; font-weight: 600; margin: 0 0 16px 0;">
                    @if($tipo === 'asignacion')
                        Se le ha asignado un nuevo radicado
                    @elseif($tipo === 'actualizacion')
                        Un radicado ha sido actualizado
                    @elseif($tipo === 'vencimiento')
                        Radicado próximo a vencer
                    @endif
                </h2>
                
                <p style="color: #6c757d; font-size: 14px; margin: 0 0 24px 0;">
                    @if($tipo === 'asignacion')
                        Tiene un nuevo documento para revisar y gestionar.
                    @elseif($tipo === 'actualizacion')
                        Se han realizado cambios en un radicado de su responsabilidad.
                    @elseif($tipo === 'vencimiento')
                        Este radicado requiere atención antes de su fecha límite.
                    @endif
                </p>
                
                <!-- Radicado Card -->
                <div class="radicado-card">
                    <div class="radicado-number">
                        Radicado N° {{ $radicado->num_radicado }}
                    </div>
                    
                    <div class="radicado-subject">
                        {{ $radicado->asunto }}
                    </div>
                    
                    <!-- Info Grid -->
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Clasificación</div>
                            <div class="info-value">
                                {{ $radicado->clasificacionDocumental->nom ?? $radicado->clasificacionDocumental->nombre ?? 'No especificada' }}
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Remitente / Origen</div>
                            <div class="info-value">
                                @if(isset($radicado->tercero))
                                    {{ $radicado->tercado->razon_social ?? $radicado->tercero->nombres ?? 'No especificado' }}
                                @elseif(isset($radicado->dependenciaOrigen))
                                    {{ $radicado->dependenciaOrigen['nombre'] ?? $radicado->dependenciaOrigen->nom_organico ?? 'No especificado' }}
                                @else
                                    No especificado
                                @endif
                            </div>
                        </div>
                        
                        @if($radicado->fec_docu)
                        <div class="info-item">
                            <div class="info-label">Fecha Documento</div>
                            <div class="info-value">
                                {{ \Carbon\Carbon::parse($radicado->fec_docu)->format('d/m/Y') }}
                            </div>
                        </div>
                        @endif
                        
                        @if($radicado->fec_venci)
                        <div class="info-item">
                            <div class="info-label">Fecha Límite</div>
                            <div class="info-value" style="color: {{ \Carbon\Carbon::parse($radicado->fec_venci)->isPast() ? '#d32f2f' : '#2c3e50' }};">
                                {{ \Carbon\Carbon::parse($radicado->fec_venci)->format('d/m/Y') }}
                            </div>
                        </div>
                        @endif
                        
                        <div class="info-item">
                            <div class="info-label">Fecha Radicación</div>
                            <div class="info-value">
                                {{ \Carbon\Carbon::parse($radicado->created_at)->format('d/m/Y H:i') }}
                            </div>
                        </div>

                        @if(isset($radicado->medioRecepcion))
                        <div class="info-item">
                            <div class="info-label">Medio de Recepción</div>
                            <div class="info-value">
                                {{ $radicado->medioRecepcion->nombre ?? $radicado->medioRecepcion->descripcion ?? 'No especificado' }}
                            </div>
                        </div>
                        @endif

                        <div class="info-item">
                            <div class="info-label">Usuario que Radicó</div>
                            <div class="info-value">
                                @if(isset($radicado->usuarioCreaRadicado))
                                    {{ trim($radicado->usuarioCreaRadicado->nombres . ' ' . $radicado->usuarioCreaRadicado->apellidos) }}
                                @elseif(isset($radicado->usuarioCrea))
                                    {{ trim($radicado->usuarioCrea->nombres . ' ' . $radicado->usuarioCrea->apellidos) }}
                                @else
                                    No especificado
                                @endif
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Número de Folios</div>
                            <div class="info-value">
                                {{ $radicado->num_folios ?? 'No especificado' }}
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Número de Anexos</div>
                            <div class="info-value">
                                {{ $radicado->num_anexos ?? 'No especificado' }}
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">Descripción de Anexos</div>
                            <div class="info-value">
                                {{ $radicado->descrip_anexos ?? 'No especificado' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="radicado-card">
                    <div class="radicado-subject">Responsables</div>
                    <div style="margin-top: 12px;">
                        @forelse($radicado->responsables as $responsable)
                            <div style="margin-bottom: 8px; color: #2c3e50; font-size: 14px;">
                                {{ $responsable->userCargo?->user?->nombres }} {{ $responsable->userCargo?->user?->apellidos }}
                                ({{ $responsable->userCargo?->user?->email ?? 'Sin correo' }})
                                - {{ $responsable->userCargo?->cargo?->nom_organico ?? 'Sin cargo' }}
                                @if($responsable->custodio)
                                    <strong style="color:#1976d2;">(Custodio)</strong>
                                @endif
                            </div>
                        @empty
                            <div style="color: #6c757d; font-size: 14px;">Sin responsables asignados.</div>
                        @endforelse
                    </div>
                </div>

                <div class="radicado-card">
                    <div class="radicado-subject">Documentos</div>
                    <div style="margin-top: 12px; color: #2c3e50; font-size: 14px;">
                        <div><strong>Documento digital:</strong> {{ $radicado->archivo_digital ? basename($radicado->archivo_digital) : 'No registrado' }}</div>
                        @if(isset($hashArchivo) && $hashArchivo)
                        <div style="margin-top: 12px; background-color: #e8f5e9; padding: 12px; border-radius: 6px; border: 1px dashed #4caf50;">
                            <div style="font-size: 11px; font-weight: 600; color: #2e7d32; text-transform: uppercase; margin-bottom: 4px;">Hash SHA-256 (verificación de integridad)</div>
                            <div style="font-size: 10px; font-family: monospace; word-break: break-all; color: #1b5e20;">{{ $hashArchivo }}</div>
                        </div>
                        @endif
                        <div style="margin-top: 8px;"><strong>Documentos anexos:</strong></div>
                        @if($radicado->archivos && $radicado->archivos->isNotEmpty())
                            <ul style="margin: 6px 0 0 18px; padding: 0;">
                                @foreach($radicado->archivos as $archivo)
                                    <li>{{ basename($archivo->archivo) }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div style="color: #6c757d;">Sin anexos.</div>
                        @endif
                    </div>
                </div>
                
                @if(!empty($adjuntosOmitidos))
                <div style="background-color: #fff3e0; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #f57c00;">
                    <p style="margin: 0 0 8px 0; color: #e65100; font-size: 14px; font-weight: 600;">⚠ Algunos archivos no se adjuntaron</p>
                    <p style="margin: 0 0 8px 0; color: #795548; font-size: 13px;">
                        El correo supera el tamaño máximo permitido ({{ App\Helpers\MailAdjuntosHelper::formatearPeso(App\Helpers\MailAdjuntosHelper::MAX_BYTES) }}). Los siguientes documentos permanecen disponibles en el sistema:
                    </p>
                    <ul style="margin: 0 0 0 18px; padding: 0; color: #4e342e; font-size: 13px;">
                        @foreach($adjuntosOmitidos as $omitido)
                            <li>{{ $omitido['nombre'] }} ({{ App\Helpers\MailAdjuntosHelper::formatearPeso($omitido['peso']) }})</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                
                <!-- Action Message -->
                <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; margin-top: 24px; border-left: 4px solid #1976d2;">
                    <p style="margin: 0; color: #1565c0; font-size: 14px; font-weight: 500;">
                        💡 <strong>Próximos pasos:</strong> 
                        @if($tipo === 'asignacion')
                            Ingrese al sistema para revisar el documento y realizar las acciones correspondientes.
                        @elseif($tipo === 'actualizacion')
                            Revise los cambios realizados y tome las acciones necesarias.
                        @elseif($tipo === 'vencimiento')
                            Gestione este radicado antes de la fecha límite para evitar retrasos.
                        @endif
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
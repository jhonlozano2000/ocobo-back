<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>{{ config('app.name') }} - Notificación de Radicado Enviado</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light">
    <style>
        body { font-family: 'Inter', -apple-system, sans-serif; background-color: #f8f9fa; margin: 0; padding: 0; line-height: 1.6; }
        .email-wrapper { width: 100%; background-color: #f8f9fa; padding: 40px 0; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .email-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; }
        .email-header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; }
        .email-header .subtitle { color: rgba(255,255,255,0.9); margin: 8px 0 0 0; font-size: 14px; }
        .email-body { padding: 40px 30px; }
        .notification-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 20px; }
        .badge-assignment { background-color: #e3f2fd; color: #1976d2; }
        .badge-update { background-color: #fff3e0; color: #f57c00; }
        .radicado-card { background-color: #f8f9fa; border-radius: 8px; padding: 24px; margin: 20px 0; border-left: 4px solid #667eea; }
        .radicado-number { font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 12px; }
        .radicado-subject { font-size: 16px; font-weight: 600; color: #34495e; margin-bottom: 16px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 20px; }
        .info-item { background-color: #ffffff; padding: 16px; border-radius: 6px; border: 1px solid #e9ecef; }
        .info-label { font-size: 12px; font-weight: 600; color: #6c757d; text-transform: uppercase; margin-bottom: 4px; }
        .info-value { font-size: 14px; font-weight: 500; color: #2c3e50; }
        .email-footer { background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
        .footer-text { color: #6c757d; font-size: 12px; margin: 0; }
        @media (max-width: 600px) { .info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-header">
                <h1>{{ config('app.name') }}</h1>
                <p class="subtitle">Correspondencia Enviada</p>
            </div>
            <div class="email-body">
                <div class="notification-badge @if($tipo === 'asignacion') badge-assignment @else badge-update @endif">
                    @if($tipo === 'asignacion') 📋 Nuevo Radicado Enviado Asignado @else 🔄 Radicado Enviado Actualizado @endif
                </div>
                <h2 style="color: #2c3e50; font-size: 20px; font-weight: 600; margin: 0 0 16px 0;">
                    @if($tipo === 'asignacion') Se le ha asignado un nuevo radicado enviado @else Un radicado enviado ha sido actualizado @endif
                </h2>
                <p style="color: #6c757d; font-size: 14px; margin: 0 0 24px 0;">
                    @if($tipo === 'asignacion') Tiene un nuevo documento enviado para revisar y gestionar. @else Se han realizado cambios en un radicado enviado de su responsabilidad. @endif
                </p>
                <div class="radicado-card">
                    <div class="radicado-number">Radicado N° {{ $radicado->num_radicado }}</div>
                    <div class="radicado-subject">{{ $radicado->asunto ?? 'Sin asunto' }}</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Clasificación</div>
                            <div class="info-value">{{ $radicado->clasificacionDocumental?->nom ?? $radicado->clasificacionDocumental?->nombre ?? 'No especificada' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Destinatario</div>
                            <div class="info-value">{{ $radicado->tercero->razon_social ?? $radicado->tercero->nombres ?? 'No especificado' }}</div>
                        </div>
                        @if($radicado->fec_docu)
                        <div class="info-item">
                            <div class="info-label">Fecha Documento</div>
                            <div class="info-value">{{ \Carbon\Carbon::parse($radicado->fec_docu)->format('d/m/Y') }}</div>
                        </div>
                        @endif
                        <div class="info-item">
                            <div class="info-label">Medio de Envío</div>
                            <div class="info-value">{{ $radicado->medioEnvio->nombre ?? $radicado->medioEnvio->descripcion ?? 'No especificado' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tipo de Respuesta</div>
                            <div class="info-value">{{ $radicado->tipoRespuesta->nombre ?? $radicado->tipoRespuesta->descripcion ?? 'No especificado' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Usuario que Radicó</div>
                            <div class="info-value">{{ $radicado->usuarioCreaRadicado ? trim($radicado->usuarioCreaRadicado->nombres . ' ' . $radicado->usuarioCreaRadicado->apellidos) : 'No especificado' }}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fecha Radicación</div>
                            <div class="info-value">{{ \Carbon\Carbon::parse($radicado->created_at)->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                </div>
                <div class="radicado-card">
                    <div class="radicado-subject">Responsables</div>
                    <div style="margin-top: 12px;">
                        @forelse($radicado->responsables ?? [] as $responsable)
                            <div style="margin-bottom: 8px; color: #2c3e50; font-size: 14px;">
                                {{ $responsable->userCargo?->user?->nombres }} {{ $responsable->userCargo?->user?->apellidos }}
                                ({{ $responsable->userCargo?->user?->email ?? 'Sin correo' }}) - {{ $responsable->userCargo?->cargo?->nom_organico ?? 'Sin cargo' }}
                                @if($responsable->custodio) <strong style="color:#1976d2;">(Custodio)</strong> @endif
                            </div>
                        @empty
                            <div style="color: #6c757d; font-size: 14px;">Sin responsables asignados.</div>
                        @endforelse
                    </div>
                </div>
                <div class="radicado-card">
                    <div class="radicado-subject">Documento</div>
                    <div style="margin-top: 12px; color: #2c3e50; font-size: 14px;">
                        <strong>Archivo digital:</strong> {{ $radicado->archivo_digital ? basename($radicado->archivo_digital) : 'No registrado' }}
                    </div>
                </div>
                <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; margin-top: 24px; border-left: 4px solid #1976d2;">
                    <p style="margin: 0; color: #1565c0; font-size: 14px; font-weight: 500;">
                        💡 <strong>Próximos pasos:</strong> Ingrese al sistema para revisar el documento enviado y realizar las acciones correspondientes.
                    </p>
                </div>
            </div>
            <div class="email-footer">
                <p class="footer-text">© {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.<br>Este es un mensaje automático, por favor no responda a este correo.</p>
            </div>
        </div>
    </div>
</body>
</html>

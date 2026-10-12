<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo inicio de sesión</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h1 style="color: #333; margin: 0 0 20px; text-align: center;">Nuevo inicio de sesión</h1>
                            <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                                Hola {{ $nombre }}, se inició sesión en tu cuenta mientras tenías otra sesión activa desde otra ubicación.
                            </p>
                            <p style="color: #666; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                                <strong>Fecha:</strong> {{ $fecha->format('d/m/Y H:i') }}<br>
                                <strong>IP:</strong> {{ $ip }}<br>
                                <strong>Dispositivo:</strong> {{ $dispositivo ?: 'Desconocido' }}
                            </p>
                            <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0;">
                                Si no fuiste tú, cierra las demás sesiones desde tu perfil y cambia tu contraseña de inmediato.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

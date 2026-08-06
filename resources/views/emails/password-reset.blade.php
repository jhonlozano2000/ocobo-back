<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecimiento de Contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 40px 30px; text-align: center;">
                            <h1 style="color: #333; margin: 0 0 20px;">Restablecimiento de Contraseña</h1>
                            <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                                Has solicitado restablecer tu contraseña. Haz clic en el botón de abajo para continuar.
                            </p>
                            <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                                Si no solicitaste este cambio, ignora este correo.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="{{ $resetUrl }}" style="background-color: #7367f0; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: bold; display: inline-block;">
                                            Restablecer Contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="color: #999; font-size: 14px; margin: 30px 0 0;">
                                Este enlace expirará en 60 minutos.
                            </p>
                            <p style="color: #999; font-size: 14px;">
                                Si el botón no funciona, copia y pega esta URL en tu navegador:<br>
                                <span style="color: #7367f0; word-break: break-all;">{{ $resetUrl }}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

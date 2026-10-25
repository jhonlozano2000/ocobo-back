<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notificacion->title }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h1 style="color: #333; margin: 0 0 20px; text-align: center;">{{ $notificacion->title }}</h1>
                            <p style="color: #666; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                                Hola {{ $notificacion->user?->nombres }}, {{ $notificacion->message }}
                            </p>
                            <p style="text-align: center; margin: 30px 0;">
                                <a href="{{ $enlace }}" style="background-color: #7367f0; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-size: 15px;">Ver en OCOBO</a>
                            </p>
                            <p style="color: #999; font-size: 13px; line-height: 1.5; margin: 0;">
                                Recibes este correo porque activaste el correo de esta categoría en las preferencias de notificación de tu perfil.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

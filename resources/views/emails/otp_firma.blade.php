<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;">
        <h2 style="color: #2c3e50; text-align: center;">Firma Electrónica Ocobo SGDEA</h2>
        <p>Hola, <strong>{{ $nombre }}</strong>,</p>
        <p>Has solicitado firmar un documento en el sistema de gestión documental. Utiliza el siguiente código de seguridad (OTP) para completar el proceso:</p>
        <div style="text-align: center; margin: 30px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; background: #f4f4f4; padding: 10px 20px; border-radius: 5px; border: 1px dashed #2c3e50;">
                {{ $otp }}
            </span>
        </div>
        <p style="color: #e74c3c;"><strong>Este código expirará en 5 minutos.</strong></p>
        <p>Si no has solicitado este código, por favor ignora este mensaje o contacta al administrador del sistema.</p>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 12px; color: #7f8c8d; text-align: center;">
            Este es un mensaje automático generado por Ocobo SGDEA - Sistema de Gestión Documental Electrónico de Archivos.
        </p>
    </div>
</body>
</html>

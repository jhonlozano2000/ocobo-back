<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\VentanillaUnica\VentanillaRadicaReci;
use App\Mail\RadicadoNotification;
use Illuminate\Support\Facades\Mail;

// Inicializar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== VERIFICACIÓN DE ENVÍO DE EMAILS CON ARCHIVOS ADJUNTOS ===\n\n";

try {
    // 1. Buscar una radicación con archivos
    echo "1. Buscando radicaciones con archivos...\n";
    
    $radicadoConArchivo = VentanillaRadicaReci::whereNotNull('archivo_digital')->first();
    $radicadoConAdjuntos = VentanillaRadicaReci::whereHas('archivos')->first();
    
    if (!$radicadoConArchivo && !$radicadoConAdjuntos) {
        echo "❌ No se encontraron radicaciones con archivos para probar.\n";
        echo "Creando una radicación de prueba...\n";
        
        // Crear una radicación de prueba
        $radicado = VentanillaRadicaReci::create([
            'num_radicado' => 'TEST-' . date('YmdHis'),
            'clasifica_documen_id' => 1, // Asumiendo que existe
            'tercero_id' => 1, // Asumiendo que existe
            'medio_recep_id' => 1, // Asumiendo que existe
            'asunto' => 'Prueba de envío de email con archivos adjuntos',
            'archivo_digital' => null // Sin archivo por ahora
        ]);
        
        echo "✅ Radicación de prueba creada: ID {$radicado->id}\n";
    } else {
        $radicado = $radicadoConArchivo ?: $radicadoConAdjuntos;
        echo "✅ Radicación encontrada: ID {$radicado->id}\n";
    }
    
    // 2. Verificar archivos asociados
    echo "\n2. Verificando archivos asociados...\n";
    
    if ($radicado->archivo_digital) {
        echo "   - Archivo digital principal: {$radicado->archivo_digital}\n";
        
        // Verificar si el archivo existe
        if (\Storage::disk('radicaciones_recibidas')->exists($radicado->archivo_digital)) {
            echo "     ✅ Archivo existe en el disco\n";
        } else {
            echo "     ❌ Archivo NO existe en el disco\n";
        }
    } else {
        echo "   - Sin archivo digital principal\n";
    }
    
    $archivosAdicionales = $radicado->archivos;
    echo "   - Archivos adicionales: " . $archivosAdicionales->count() . "\n";
    
    foreach ($archivosAdicionales as $archivo) {
        echo "     * {$archivo->archivo}";
        if (\Storage::disk('radicaciones_recibidas')->exists($archivo->archivo)) {
            echo " ✅\n";
        } else {
            echo " ❌\n";
        }
    }
    
    // 3. Crear instancia del email
    echo "\n3. Creando instancia de RadicadoNotification...\n";
    
    $notification = new RadicadoNotification($radicado);
    echo "✅ Instancia creada correctamente\n";
    
    // 4. Verificar método build
    echo "\n4. Verificando método build()...\n";
    
    $mailMessage = $notification->build();
    echo "✅ Método build() ejecutado correctamente\n";
    
    // 5. Verificar archivos adjuntos
    echo "\n5. Verificando archivos adjuntos en el email...\n";
    
    // Usar reflexión para acceder a los attachments
    $reflection = new ReflectionClass($mailMessage);
    $attachmentsProperty = $reflection->getProperty('attachments');
    $attachmentsProperty->setAccessible(true);
    $attachments = $attachmentsProperty->getValue($mailMessage);
    
    echo "   - Número de archivos adjuntos: " . count($attachments) . "\n";
    
    foreach ($attachments as $index => $attachment) {
        echo "   - Adjunto " . ($index + 1) . ":\n";
        echo "     * Archivo: " . ($attachment['file'] ?? 'N/A') . "\n";
        echo "     * Nombre: " . ($attachment['options']['as'] ?? 'N/A') . "\n";
        echo "     * Tipo MIME: " . ($attachment['options']['mime'] ?? 'N/A') . "\n";
    }
    
    // 6. Simular envío (sin enviar realmente)
    echo "\n6. Simulando envío de email...\n";
    
    // Configurar para usar log driver temporalmente
    config(['mail.default' => 'log']);
    
    try {
        // Enviar a una dirección de prueba
        Mail::to('test@example.com')->send($notification);
        echo "✅ Email enviado correctamente (revisar logs)\n";
    } catch (Exception $e) {
        echo "❌ Error al enviar email: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== VERIFICACIÓN COMPLETADA ===\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la verificación: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
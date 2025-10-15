<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\VentanillaUnica\VentanillaRadicaReci;
use App\Mail\RadicadoNotification;

// Cargar configuración de Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // Buscar un radicado con archivos
    $radicado = VentanillaRadicaReci::with('archivos')->first();
    
    if (!$radicado) {
        echo "No se encontraron radicados en la base de datos.\n";
        exit(1);
    }
    
    echo "Radicado encontrado: " . $radicado->num_radicado . "\n";
    echo "Archivo digital: " . ($radicado->archivo_digital ?? 'No tiene') . "\n";
    echo "Archivos adicionales: " . $radicado->archivos->count() . "\n";
    
    // Crear instancia de la notificación
    $notification = new RadicadoNotification($radicado, 'asignacion');
    
    // Obtener adjuntos
    $attachments = $notification->attachments();
    
    echo "Total de adjuntos configurados: " . count($attachments) . "\n";
    
    foreach ($attachments as $index => $attachment) {
        echo "Adjunto " . ($index + 1) . ": " . $attachment->as . "\n";
    }
    
    echo "✅ Prueba completada exitosamente. Los adjuntos están configurados correctamente.\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la prueba: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}
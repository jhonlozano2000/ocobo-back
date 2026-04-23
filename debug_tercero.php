<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$r = \App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados::first();
echo "tercero_id value: " . ($r->tercero_id ?? 'NULL') . "\n";

// Check if tercero_enviado_id column exists
$hasCol = \Illuminate\Support\Facades\Schema::hasColumn('ventanilla_radica_enviados', 'tercero_enviado_id');
echo "has tercero_enviado_id column: " . ($hasCol ? 'YES' : 'NO') . "\n";

$hasCol2 = \Illuminate\Support\Facades\Schema::hasColumn('ventanilla_radica_enviados', 'tercero_id');
echo "has tercero_id column: " . ($hasCol2 ? 'YES' : 'NO') . "\n";

// Try loading with the tercero relation
$r2 = \App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados::with('tercero')->find($r->id);
echo "tercero relation loaded: " . ($r2->tercero ? json_encode($r2->tercero->only(['id', 'nom_razo_soci'])) : 'NULL') . "\n";
echo "tercero_id raw: " . $r2->getRawOriginal('tercero_id') . "\n";

// Check attributes  
echo "\nAll attributes:\n";
foreach (['tercero_id', 'tercero_enviado_id'] as $key) {
    echo "  $key: " . ($r2->$key ?? 'NULL') . "\n";
}
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CALIDAD_ORGANIGRAMA ===\n";
$deps = App\Models\Calidad\CalidadOrganigrama::all();
foreach($deps as $d) {
    echo "ID: " . $d->id . " | COD: '" . ($d->cod_organico ?: 'VACIO') . "' | NOM: " . substr($d->nom_organico, 0, 50) . "\n";
}
echo "\nTotal: " . $deps->count() . " dependencias\n";
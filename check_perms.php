<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::find(1);
$perms = $user->getAllPermissions()->pluck('name')->filter(function ($p) {
    return str_contains($p, 'Subir') || str_contains($p, 'subir') || str_contains($p, 'upload');
})->values();

echo "Upload permissions:\n";
echo $perms->toJson()."\n\n";

// Test middleware
$allPqrs = $user->getAllPermissions()->pluck('name')->filter(function ($p) {
    return str_contains($p, 'PQRSF');
})->values();
echo "All PQRSF permissions:\n";
echo $allPqrs->toJson()."\n";

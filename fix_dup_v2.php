<?php
$file = __DIR__.'/app/Services/Firma/TsaService.php';
$c = file_get_contents($file);

// Encontrar TODAS las ocurrencias de verificarFirmaToken
$positions = [];
$offset = 0;
while (($pos = strpos($c, 'private function verificarFirmaToken', $offset)) !== false) {
    $positions[] = $pos;
    $offset = $pos + 10;
}
echo "Ocurrencias: ".count($positions)."\n";
foreach ($positions as $p) {
    echo "  at $p\n";
}

if (count($positions) > 1) {
    // Eliminar desde la segunda hasta antes del siguiente método después de ella
    // Buscar el final del segundo: encontrar "private function" o "}" que lo cierre
    $secondStart = $positions[1];
    // Retroceder al /** anterior
    $docStart = strrpos(substr($c, 0, $secondStart), '/**');
    if ($docStart === false) { $docStart = $secondStart; }
    
    // Buscar donde termina (siguiente private function o fin de clase)
    $nextFn = strpos($c, 'private function', $secondStart + 10);
    if ($nextFn === false) { $nextFn = strpos($c, '}', strrpos($c, '}')); } // última }
    
    echo "Eliminando desde doc=$docStart hasta next_fn=$nextFn\n";
    $c = substr_replace($c, '', $docStart, $nextFn - $docStart);
    file_put_contents($file, $c);
    echo "Duplicado eliminado\n";
}

echo "Lint:\n";
passthru("php -l $file");

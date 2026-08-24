<?php
/**
 * Limpieza final de TsaService: elimina duplicados y código muerto.
 */
$file = __DIR__.'/app/Services/Firma/TsaService.php';
$c = file_get_contents($file);

// Eliminar encodeLength duplicado (segunda ocurrencia, fuera de la clase)
$first = strpos($c, 'private function encodeLength');
$second = strpos($c, 'private function encodeLength', $first + 10);
if ($second !== false) {
    // Buscar el final: siguiente "private function" o cierre de archivo
    $nextFn = strpos($c, "\n", $second + 100);
    if ($nextFn === false) { $nextFn = strlen($c); }
    // Encontrar el cierre } del método (buscar \n    }\n)
    $closePos = strpos($c, "\n}", strpos($c, "\n", $second));
    if ($closePos !== false) {
        $endClean = $closePos + 2; // incluir }\n
        $c = substr_replace($c, '', $second - 4, $endClean - ($second - 4));
        echo "encodeLength duplicado eliminado\n";
    }
}

// Eliminar extractDigestAlgorithm (no se usa en el nuevo código)
$edStart = strpos($c, 'private function extractDigestAlgorithm');
if ($edStart !== false) {
    $edEnd = strpos($c, "\n    }", $edStart);
    if ($edEnd !== false) {
        $c = substr_replace($c, '', $edStart - 8, $edEnd + 6 - ($edStart - 8));
        echo "extractDigestAlgorithm eliminado\n";
    }
}

file_put_contents($file, $c);

// Verificar
exec("php -l ".escapeshellarg($file)." 2>&1", $out, $code);
echo implode("\n", $out)."\n";

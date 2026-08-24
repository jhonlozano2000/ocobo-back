<?php
$f = __DIR__.'/app/Services/Firma/TsaService.php';
$c = file_get_contents($f);

// Buscar el bloque de extracción del token y reemplazarlo con return simple
$start_marker = '        // TimeStampResp ::= SEQUENCE';
$end_marker = 'return base64_encode(substr($respDer,';

$start = strpos($c, $start_marker);
if ($start === false) { die("No encontré inicio\n"); }
$end_pos = strpos($c, $end_marker, $start);
if ($end_pos === false) { die("No encontré fin\n"); }

// Encontrar el final de la línea que contiene el return
$line_end = strpos($c, "\n", strpos($c, ');', $end_pos));
if ($line_end === false) { $line_end = strlen($c); }

$new_code = "        // Retornar el TimeStampResp completo;\n"
    ."        // parseTimeStampToken detecta y salta el wrapper PKIStatusInfo.";

$c = substr_replace($c, $new_code, $start, $line_end - $start);
file_put_contents($f, $c);
echo "OK\n";

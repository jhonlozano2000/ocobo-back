<?php
$c = file_get_contents('app/Services/Firma/TsaService.php');
$first = strpos($c, '    private function verificarFirmaToken');
$second = strpos($c, '    private function verificarFirmaToken', $first + 10);
$next = strpos($c, '    private function extractDigestAlgorithm');
if ($second !== false && $next !== false && $next > $second) {
    $c = substr_replace($c, '', $second, $next - $second);
    file_put_contents('app/Services/Firma/TsaService.php', $c);
    echo "Eliminado duplicado\n";
} else {
    echo "No encontrado: second=$second next=$next\n";
}

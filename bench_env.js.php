<?php
use App\Models\VentanillaUnica\Enviados\VentanillaRadicaEnviados;
use Illuminate\Support\Facades\DB;

echo 'Total enviados: ' . VentanillaRadicaEnviados::count() . PHP_EOL;

DB::enableQueryLog();

$start = microtime(true);
$radicados = VentanillaRadicaEnviados::with([
    'clasificacionDocumental',
    'tercero',
    'medioEnvio',
    'tipoRespuesta',
    'usuarioCreaRadicado',
    'responsables.userCargo.user',
    'responsables.userCargo.cargo',
])->orderBy('created_at', 'desc')->paginate(10);
$elapsedQuery = microtime(true) - $start;

$start = microtime(true);
$radicados->getCollection()->transform(function ($radicado) {
    $radicado->getClasificacionDocumentalInfo();
    $radicado->getResponsablesInfo();
    $radicado->getEstadoTrabajoInfo();
    $radicado->getDiasParaVencerAttribute();
    $radicado->isVencida();
    return $radicado;
});
$elapsedTransform = microtime(true) - $start;

echo 'Query base (paginate 10): ' . round($elapsedQuery * 1000, 1) . 'ms' . PHP_EOL;
echo 'Transform (10 filas): ' . round($elapsedTransform * 1000, 1) . 'ms' . PHP_EOL;
echo 'Total: ' . round(($elapsedQuery + $elapsedTransform) * 1000, 1) . 'ms' . PHP_EOL;

$log = DB::getQueryLog();
echo 'Num queries: ' . count($log) . PHP_EOL;
$slow = collect($log)->sortByDesc('time')->take(5);
foreach ($slow as $q) {
    echo round($q['time'], 1) . 'ms :: ' . substr(str_replace(["\n", "\r"], ' ', $q['query']), 0, 200) . PHP_EOL;
}

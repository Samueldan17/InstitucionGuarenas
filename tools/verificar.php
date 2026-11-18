<?php
// Verificación mínima de sintaxis PHP para SIGEB-G.
// Uso: php tools/verificar.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se ejecuta por línea de comandos.');
}

$root = dirname(__DIR__);
$excluir = ['vendor', '.git', 'node_modules', 'cache'];
$archivos = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $archivo) {
    if (strtolower($archivo->getExtension()) !== 'php') continue;

    $ruta = $archivo->getPathname();
    $relativa = str_replace('\\', '/', substr($ruta, strlen($root) + 1));
    $primeraCarpeta = explode('/', $relativa)[0];
    if (in_array($primeraCarpeta, $excluir, true)) continue;

    $archivos[] = $ruta;
}

sort($archivos);

$fallos = 0;
foreach ($archivos as $archivo) {
    $salida = [];
    $codigo = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($archivo) . ' 2>&1', $salida, $codigo);
    if ($codigo !== 0) {
        $fallos++;
        echo "FALLO: $archivo\n" . implode("\n", $salida) . "\n";
    }
}

echo count($archivos) . " archivos verificados; " . ($fallos ? "$fallos con errores" : "sin errores") . ".\n";
exit($fallos ? 1 : 0);

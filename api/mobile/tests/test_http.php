<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Subprocessos chamam somente rotas que terminam antes de abrir conexão com MySQL.
$entry = realpath(__DIR__ . '/../index.php');
$cases = [
    ['GET', 'health', 200, 'data'],
    ['POST', 'health', 405, 'error'],
    ['GET', 'inexistente', 404, 'error'],
    ['GET', 'login', 405, 'error'],
    ['POST', 'login', 415, 'error'],
];
foreach ($cases as [$method, $resource, $expected, $field]) {
    $code = '$_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . ';'
        . '$_GET["resource"]=' . var_export($resource, true) . ';'
        . 'register_shutdown_function(function(){fwrite(STDERR, (string)http_response_code());});'
        . 'require ' . var_export($entry, true) . ';';
    $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Não foi possível abrir PHP.'); }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $status = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    $json = json_decode($out, true, 32, JSON_THROW_ON_ERROR);
    if ($exit !== 0 || (int) $status !== $expected || !isset($json[$field])) {
        throw new RuntimeException("Falhou contrato: $method $resource; HTTP $status; $out");
    }
}
fwrite(STDOUT, count($cases) . " contratos HTTP passaram (nenhum banco utilizado).\n");

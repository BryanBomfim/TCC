<?php
declare(strict_types=1);
$raiz = dirname(__DIR__);
$arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
$falhas = 0;
$total = 0;
foreach ($arquivos as $arquivo) {
    if ($arquivo->getExtension() !== 'php' || str_contains($arquivo->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    $processo = proc_open([PHP_BINARY, '-l', $arquivo->getPathname()], [0=>STDIN, 1=>STDOUT, 2=>STDERR], $pipes);
    if (!is_resource($processo) || proc_close($processo) !== 0) { $falhas++; }
    $total++;
}
echo "Arquivos PHP: {$total}; falhas: {$falhas}" . PHP_EOL;
exit($falhas === 0 ? 0 : 1);

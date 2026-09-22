<?php
/**
 * Conexão PDO configurável por variáveis de ambiente.
 */

require_once __DIR__ . '/environment.php';

define('DB_HOST', (string) env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', (string) env('DB_NAME', 'smartcontrol'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASSWORD', ''));
define('DB_CHARSET', (string) env('DB_CHARSET', 'utf8mb4'));

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
} catch (PDOException $e) {
    error_log('Falha de conexão com o banco SmartControl: ' . $e->getMessage());

    if (PHP_SAPI === 'cli') {
        throw $e;
    }

    http_response_code(500);
    $debug = (bool) env('APP_DEBUG', false);
    $detalhe = $debug ? '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>' : '';

    exit(
        '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>SmartControl - indisponível</title></head><body><main><h1>Não foi possível iniciar o SmartControl</h1>'
        . '<p>Verifique a configuração do banco de dados e tente novamente.</p>' . $detalhe . '</main></body></html>'
    );
}

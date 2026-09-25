<?php
/**
 * config/database.php
 * -----------------------------------------------------------------
 * Configuração e abertura da conexão com o banco de dados MySQL,
 * utilizando PDO. Ajuste as constantes abaixo conforme o seu
 * ambiente (XAMPP/WAMP/phpMyAdmin).
 * -----------------------------------------------------------------
 */

$dbHost = (string) smartcontrolEnv('DB_HOST', '127.0.0.1');
$dbPort = smartcontrolEnvInt('DB_PORT', 3306);
$dbName = (string) smartcontrolEnv('DB_NAME', 'smartcontrol');
$dbUser = (string) smartcontrolEnv('DB_USER', 'root');
$dbPassword = (string) smartcontrolEnv('DB_PASSWORD', '');
$dbCharset = 'utf8mb4';

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPassword);
define('DB_CHARSET', $dbCharset);

try {
    $dsn = 'mysql:host=' . DB_HOST
        . ';port=' . DB_PORT
        . ';dbname=' . DB_NAME
        . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $pdo->exec("SET time_zone = '-03:00'");
} catch (PDOException $e) {
    error_log('Falha na conexão com o banco de dados: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    $mensagem = 'Não foi possível conectar ao banco de dados. Tente novamente mais tarde.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $mensagem . PHP_EOL);
        exit(1);
    }
    if (defined('SMARTCONTROL_API') && SMARTCONTROL_API) {
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode(['success' => false, 'data' => null, 'message' => $mensagem]));
    }
    exit($mensagem);
}

<?php
/**
 * Configuração global do SmartControl.
 */

require_once __DIR__ . '/environment.php';

define('APP_ENV', (string) env('APP_ENV', 'local'));
define('APP_DEBUG', (bool) env('APP_DEBUG', APP_ENV !== 'production'));
define('BASE_URL', rtrim((string) env('APP_BASE_URL', '/smartcontrol'), '/'));
define('APP_URL', rtrim((string) env('APP_URL', 'http://localhost/smartcontrol'), '/'));
define('SESSION_TIMEOUT', max(300, (int) env('SESSION_TIMEOUT', 1800)));

date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
);

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL !== '' ? BASE_URL : '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (isset($_SESSION['usuario_id'])) {
    $agora = time();
    $ultimaAtividade = (int) ($_SESSION['ultima_atividade'] ?? $agora);

    if (($agora - $ultimaAtividade) > SESSION_TIMEOUT) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        $_SESSION['mensagem'] = [
            'tipo' => 'erro',
            'texto' => 'Sua sessão expirou por inatividade. Entre novamente.',
        ];
    } else {
        $_SESSION['ultima_atividade'] = $agora;
    }
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e): void {
        error_log('Exceção não tratada no SmartControl: ' . $e->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
        }
        require dirname(__DIR__) . '/errors/500.php';
    });
}

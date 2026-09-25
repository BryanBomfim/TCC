<?php
/**
 * Inicialização global da aplicação.
 */

require_once __DIR__ . '/environment.php';

smartcontrolLoadEnv(__DIR__ . '/../.env');

$autoloadComposer = __DIR__ . '/../vendor/autoload.php';

if (is_file($autoloadComposer)) {
    require_once $autoloadComposer;
}

$baseUrl = rtrim((string) smartcontrolEnv('APP_BASE_URL', '/smartcontrol'), '/');
$appUrl = rtrim((string) smartcontrolEnv('APP_URL', 'http://localhost/smartcontrol'), '/');
$appEnv = strtolower((string) smartcontrolEnv('APP_ENV', 'production'));
$appDebug = smartcontrolEnvBool('APP_DEBUG', false) && $appEnv !== 'production';

define('BASE_URL', $baseUrl);
define('APP_URL', $appUrl);
define('APP_ENV', $appEnv);
define('APP_DEBUG', $appDebug);

date_default_timezone_set('America/Sao_Paulo');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('zend.exception_ignore_args', '1');

$pastaLogs = __DIR__ . '/../storage/logs';

if (is_dir($pastaLogs) || @mkdir($pastaLogs, 0775, true)) {
    ini_set('error_log', $pastaLogs . '/app.log');
}

$httpsAtivo = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), array_filter(array_map('trim', explode(',', (string) smartcontrolEnv('APP_TRUSTED_PROXIES', '')))), true)
        && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'))
);

if (PHP_SAPI !== 'cli' && smartcontrolEnvBool('APP_FORCE_HTTPS', false) && !$httpsAtivo) {
    http_response_code(403);
    exit('Acesse o SmartControl pela URL HTTPS configurada.');
}

$requisicaoApi = defined('SMARTCONTROL_API') && SMARTCONTROL_API;

if (!$requisicaoApi && session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_name('SMARTCONTROLSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL !== '' ? BASE_URL : '/',
        'secure' => $httpsAtivo || smartcontrolEnvBool('APP_FORCE_HTTPS', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (!headers_sent()) {
    header_remove('X-Powered-By');
}

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e): void {
        error_log('Exceção não tratada: ' . get_class($e) . ' em ' . $e->getFile() . ':' . $e->getLine());
        if (defined('SMARTCONTROL_API') && SMARTCONTROL_API) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Não foi possível processar a solicitação.']);
            exit;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        require __DIR__ . '/../errors/500.php';
        exit;
    });
}

$tempoSessao = max(300, smartcontrolEnvInt('APP_SESSION_TIMEOUT', 1800));

if (!$requisicaoApi && isset($_SESSION['usuario_id'])) {
    $ultimaAtividade = (int) ($_SESSION['ultima_atividade'] ?? time());

    if ((time() - $ultimaAtividade) > $tempoSessao) {
        $_SESSION = [];
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['mensagem'] = [
            'tipo' => 'erro',
            'texto' => 'Sua sessão expirou por inatividade. Entre novamente.',
        ];

        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    $_SESSION['ultima_atividade'] = time();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!$requisicaoApi) {
    revalidarSessao();
}

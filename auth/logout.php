<?php
/** Encerra a sessão do usuário e registra o evento de auditoria. */
require_once __DIR__ . '/../config/config.php';

if (estaLogado()) {
    registrarAuditoria('LOGOUT', 'Autenticação', (int) $_SESSION['usuario_id'], 'Logout realizado.');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;

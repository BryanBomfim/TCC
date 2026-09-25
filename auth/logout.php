<?php
/**
 * auth/logout.php
 * Encerra a sessão do usuário e redireciona para o login.
 */
require_once __DIR__ . '/../config/config.php';

if (!estaLogado()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || !validarTokenCSRF($_POST['csrf_token'] ?? null)
) {
    definirMensagem('erro', 'Não foi possível encerrar a sessão. Tente novamente.');
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

registrarAuditoria(
    'LOGOUT',
    'Autenticação',
    (int) $_SESSION['usuario_id'],
    'Logout realizado com sucesso.'
);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros['path'],
        $parametros['domain'],
        $parametros['secure'],
        $parametros['httponly']
    );
}

session_destroy();

header('Location: ' . BASE_URL . '/auth/login.php');
exit;

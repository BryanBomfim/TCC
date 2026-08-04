<?php
/**
 * index.php
 * Ponto de entrada do sistema. Redireciona para o dashboard se o
 * usuário já estiver logado, ou para a tela de login caso contrário.
 */
require_once __DIR__ . '/config/config.php';

if (estaLogado()) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;

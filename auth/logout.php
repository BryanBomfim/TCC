<?php
/**
 * auth/logout.php
 * Encerra a sessão do usuário e redireciona para o login.
 */
require_once __DIR__ . '/../config/config.php';

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . '/auth/login.php');
exit;

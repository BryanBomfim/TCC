<?php
/**
 * config/config.php
 * -----------------------------------------------------------------
 * Configurações gerais do sistema: sessão, caminho base e inclusão
 * da conexão com o banco de dados. Este arquivo deve ser incluído
 * no topo de toda página do sistema (exceto a própria conexão).
 * -----------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {

    // Não aceita IDs de sessão inválidos/inventados
    ini_set('session.use_strict_mode', '1');

    // Impede acesso ao cookie da sessão via JavaScript
    ini_set('session.cookie_httponly', '1');

    // Ajuda a reduzir envio indevido do cookie entre sites
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}

// Caminho base do sistema (ajuste se instalar em subpasta diferente)
define('BASE_URL', '/smartcontrol');

// Endereço completo do sistema.
define('APP_URL', 'http://localhost/smartcontrol');

// Fuso horário padrão
date_default_timezone_set('America/Sao_Paulo');

// Exibir erros durante o desenvolvimento (desative em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

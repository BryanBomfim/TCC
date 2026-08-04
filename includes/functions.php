<?php
/**
 * includes/functions.php
 * -----------------------------------------------------------------
 * Funções auxiliares reutilizadas em todo o sistema: autenticação,
 * controle de acesso, sanitização, formatação e mensagens de sessão.
 * -----------------------------------------------------------------
 */

/** Verifica se existe um usuário autenticado na sessão. */
function estaLogado(): bool
{
    return isset($_SESSION['usuario_id']);
}

/** Redireciona o usuário para o login caso não esteja autenticado. */
function exigirLogin(): void
{
    if (!estaLogado()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/** Verifica se o usuário logado é administrador. */
function ehAdministrador(): bool
{
    return estaLogado() && $_SESSION['usuario_perfil'] === 'administrador';
}

/** Bloqueia o acesso de quem não é administrador, exibindo erro 403. */
function exigirAdministrador(): void
{
    exigirLogin();
    if (!ehAdministrador()) {
        http_response_code(403);
        require_once __DIR__ . '/erro_403.php';
        exit;
    }
}

/** Sanitiza uma string vinda de formulário (remove tags e espaços extras). */
function limpar(?string $valor): string
{
    return htmlspecialchars(trim($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Formata um valor decimal como moeda brasileira. */
function formatarMoeda($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/** Formata uma data do formato do banco (Y-m-d H:i:s) para dd/mm/aaaa hh:mm. */
function formatarDataHora(?string $data): string
{
    if (empty($data)) {
        return '-';
    }
    $timestamp = strtotime($data);
    return date('d/m/Y H:i', $timestamp);
}

/** Formata apenas a data (sem horário). */
function formatarData(?string $data): string
{
    if (empty($data)) {
        return '-';
    }
    return date('d/m/Y', strtotime($data));
}

/** Define uma mensagem flash a ser exibida na próxima página carregada. */
function definirMensagem(string $tipo, string $texto): void
{
    $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
}

/** Recupera e limpa a mensagem flash armazenada na sessão. */
function obterMensagem(): ?array
{
    if (isset($_SESSION['mensagem'])) {
        $mensagem = $_SESSION['mensagem'];
        unset($_SESSION['mensagem']);
        return $mensagem;
    }
    return null;
}

/** Retorna o nome do usuário atualmente logado. */
function nomeUsuarioLogado(): string
{
    return $_SESSION['usuario_nome'] ?? '';
}

/** Gera e valida token CSRF simples para proteger formulários. */
function gerarTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarTokenCSRF(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

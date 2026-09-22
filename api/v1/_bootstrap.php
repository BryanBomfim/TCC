<?php
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

function apiResposta(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $status >= 200 && $status < 300,
        'data' => $data,
        'message' => $message,
        'meta' => (object) $meta,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiErro(string $message, int $status = 400, array $details = []): never
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'data' => null,
        'message' => $message,
        'errors' => (object) $details,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiCabecalhoAutorizacao(): string
{
    $cabecalho = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($cabecalho === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $cabecalho = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    return trim($cabecalho);
}

function apiAutenticar(): array
{
    global $pdo;

    if (estaLogado()) {
        return [
            'id' => (int) $_SESSION['usuario_id'],
            'nome' => (string) ($_SESSION['usuario_nome'] ?? ''),
            'perfil' => (string) ($_SESSION['usuario_perfil'] ?? 'funcionario'),
            'metodo' => 'sessao',
        ];
    }

    $cabecalho = apiCabecalhoAutorizacao();
    if (!preg_match('/^Bearer\s+(.+)$/i', $cabecalho, $m)) {
        apiErro('Autenticação necessária.', 401);
    }

    $token = trim($m[1]);
    if (!preg_match('/^sc_[a-f0-9]{64}$/i', $token)) {
        apiErro('Token de API inválido.', 401);
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT t.id AS token_id, u.id, u.nome, u.perfil
        FROM api_tokens t
        INNER JOIN usuarios u ON u.id = t.usuario_id
        WHERE t.token_hash = :token_hash
          AND t.revogado_em IS NULL
          AND (t.expira_em IS NULL OR t.expira_em > NOW())
          AND u.ativo = 1
        LIMIT 1
    ");
    $stmt->execute([':token_hash' => $tokenHash]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        apiErro('Token de API inválido, expirado ou revogado.', 401);
    }

    $stmtUso = $pdo->prepare("UPDATE api_tokens SET ultimo_uso_em = NOW() WHERE id = :id");
    $stmtUso->execute([':id' => (int) $usuario['token_id']]);

    return [
        'id' => (int) $usuario['id'],
        'nome' => (string) $usuario['nome'],
        'perfil' => (string) $usuario['perfil'],
        'metodo' => 'token',
    ];
}

function apiExigirAdministrador(array $usuario): void
{
    if (($usuario['perfil'] ?? '') !== 'administrador') {
        apiErro('Esta operação exige perfil de administrador.', 403);
    }
}

function apiPaginacao(int $padrao = 20, int $maximo = 100): array
{
    $pagina = max(1, (int) ($_GET['page'] ?? 1));
    $limite = (int) ($_GET['limit'] ?? $padrao);
    $limite = max(1, min($maximo, $limite));
    return [$pagina, $limite, ($pagina - 1) * $limite];
}

$apiUsuario = apiAutenticar();

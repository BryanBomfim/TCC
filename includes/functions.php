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

/**
 * Registra uma ação importante no histórico de auditoria.
 *
 * Exemplos de ações:
 * CRIAR, EDITAR, INATIVAR, ENTRADA, SAIDA, ANEXAR, EXCLUIR
 *
 * Importante:
 * Nunca envie senhas, tokens ou outras informações sensíveis
 * em $dadosAnteriores ou $dadosNovos.
 */
function registrarAuditoria(
    string $acao,
    string $modulo,
    ?int $registroId,
    string $descricao,
    ?array $dadosAnteriores = null,
    ?array $dadosNovos = null
): void {

    global $pdo;

    /*
     * Auditoria só é registrada quando existe
     * um usuário autenticado.
     */
    if (!isset($_SESSION['usuario_id'])) {
        return;
    }

    $usuarioId = (int) $_SESSION['usuario_id'];

    $usuarioNome = $_SESSION['usuario_nome'] ?? 'Usuário';

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    /*
     * Converte os dados de antes/depois para JSON.
     *
     * Exemplo:
     * {
     *   "preco_venda": "89.90",
     *   "estoque_minimo": 5
     * }
     */
    $jsonAnterior = null;
    $jsonNovo = null;

    if ($dadosAnteriores !== null) {

        $jsonAnterior = json_encode(
            $dadosAnteriores,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
    }

    if ($dadosNovos !== null) {

        $jsonNovo = json_encode(
            $dadosNovos,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
    }

    try {

        $stmt = $pdo->prepare("
            INSERT INTO logs_auditoria
            (
                usuario_id,
                usuario_nome,
                acao,
                modulo,
                registro_id,
                descricao,
                dados_anteriores,
                dados_novos,
                ip
            )
            VALUES
            (
                :usuario_id,
                :usuario_nome,
                :acao,
                :modulo,
                :registro_id,
                :descricao,
                :dados_anteriores,
                :dados_novos,
                :ip
            )
        ");

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':usuario_nome' => $usuarioNome,
            ':acao' => strtoupper($acao),
            ':modulo' => $modulo,
            ':registro_id' => $registroId,
            ':descricao' => $descricao,
            ':dados_anteriores' => $jsonAnterior,
            ':dados_novos' => $jsonNovo,
            ':ip' => $ip
        ]);

    } catch (Throwable $e) {

        /*
         * Uma falha no histórico não deve derrubar
         * o funcionamento principal do SmartControl.
         */
        error_log(
            'Erro ao registrar auditoria: '
            . $e->getMessage()
        );
    }
}

/**
 * Cria uma notificação para um usuário do SmartControl.
 *
 * Tipos utilizados:
 * - info
 * - sucesso
 * - alerta
 * - critico
 */
function criarNotificacao(
    int $usuarioId,
    string $tipo,
    string $titulo,
    string $mensagem,
    ?string $entidade = null,
    ?int $entidadeId = null,
    ?string $link = null
): ?int {

    global $pdo;

    /*
     * Evita criar notificação para usuário inválido.
     */
    if ($usuarioId <= 0) {
        return null;
    }

    /*
     * Mantemos apenas tipos conhecidos.
     */
    $tiposPermitidos = [
        'info',
        'sucesso',
        'alerta',
        'critico'
    ];

    if (!in_array($tipo, $tiposPermitidos, true)) {
        $tipo = 'info';
    }

    /*
     * Evita textos maiores que as colunas do banco.
     */
    $titulo = mb_substr(
        trim($titulo),
        0,
        150
    );

    $mensagem = mb_substr(
        trim($mensagem),
        0,
        500
    );

    if ($titulo === '' || $mensagem === '') {
        return null;
    }

    if ($link !== null) {

        $link = mb_substr(
            trim($link),
            0,
            255
        );

        if ($link === '') {
            $link = null;
        }
    }

    try {

        $stmt = $pdo->prepare("
            INSERT INTO notificacoes
            (
                usuario_id,
                tipo,
                titulo,
                mensagem,
                entidade,
                entidade_id,
                link
            )
            VALUES
            (
                :usuario_id,
                :tipo,
                :titulo,
                :mensagem,
                :entidade,
                :entidade_id,
                :link
            )
        ");

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':tipo' => $tipo,
            ':titulo' => $titulo,
            ':mensagem' => $mensagem,
            ':entidade' => $entidade,
            ':entidade_id' => $entidadeId,
            ':link' => $link
        ]);

        return (int) $pdo->lastInsertId();

    } catch (Throwable $e) {

        /*
         * Uma falha nas notificações não deve
         * derrubar o restante do sistema.
         */
        error_log(
            'Erro ao criar notificação: '
            . $e->getMessage()
        );

        return null;
    }
}

/**
 * Retorna o inventário atualmente aberto.
 *
 * Retorna null quando não existe inventário em andamento.
 */
function obterInventarioAberto(): ?array
{
    global $pdo;

    try {

        $stmt = $pdo->query("
            SELECT
                id,
                titulo,
                criado_em

            FROM inventarios

            WHERE status = 'aberto'

            ORDER BY criado_em ASC, id ASC

            LIMIT 1
        ");

        $inventario = $stmt->fetch();

        return $inventario ?: null;

    } catch (Throwable $e) {

        error_log(
            'Erro ao verificar inventário aberto: '
            . $e->getMessage()
        );

        return null;
    }
}
?>

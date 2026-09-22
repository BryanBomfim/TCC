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
    return estaLogado() && ($_SESSION['usuario_perfil'] ?? '') === 'administrador';
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
     * Eventos de segurança podem acontecer antes da autenticação.
     * Nesses casos o banco aceita usuário nulo e preserva IP/descrição.
     */
    $usuarioId = isset($_SESSION['usuario_id'])
        ? (int) $_SESSION['usuario_id']
        : null;

    $usuarioNome = $_SESSION['usuario_nome'] ?? null;

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

/**
 * Renderiza breadcrumbs simples a partir da rota atual.
 * Mantém a navegação consistente sem duplicar marcação em cada módulo.
 */
function renderizarBreadcrumbs(): void
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $partes = array_values(array_filter(explode('/', trim($script, '/'))));
    $arquivo = $partes ? array_pop($partes) : 'index.php';
    $pasta = $partes ? end($partes) : '';

    $secoes = [
        'dashboard' => ['Dashboard', '/dashboard/index.php'],
        'products' => ['Produtos', '/products/list.php'],
        'categories' => ['Categorias', '/categories/list.php'],
        'suppliers' => ['Fornecedores', '/suppliers/list.php'],
        'stock' => ['Movimentações', '/stock/history.php'],
        'inventory' => ['Inventário', '/inventory/index.php'],
        'reports' => ['Relatórios', '/reports/index.php'],
        'users' => ['Usuários', '/users/list.php'],
        'audit' => ['Auditoria', '/audit/index.php'],
        'settings' => ['Configurações', '/settings/index.php'],
        'account' => ['Minha Conta', '/account/index.php'],
        'notifications' => ['Notificações', '/notifications/index.php'],
    ];

    $paginas = [
        'create.php' => 'Novo',
        'edit.php' => 'Editar',
        'view.php' => 'Detalhes',
        'entry.php' => 'Entrada',
        'exit.php' => 'Saída',
        'adjust.php' => 'Ajuste',
        'history.php' => 'Histórico',
        'api.php' => 'API REST',
        'products.php' => 'Produtos',
        'movements.php' => 'Movimentações',
        'categories.php' => 'Categorias',
        'suppliers.php' => 'Fornecedores',
        'users.php' => 'Usuários',
        'low_stock.php' => 'Estoque baixo',
        'out_of_stock.php' => 'Sem estoque',
        'inventory.php' => 'Inventários',
    ];

    if (!isset($secoes[$pasta])) {
        return;
    }

    [$secao, $rotaSecao] = $secoes[$pasta];
    $arquivoInicial = in_array($arquivo, ['index.php', 'list.php'], true);

    echo '<nav class="breadcrumbs" aria-label="Breadcrumb">';
    if ($pasta !== 'dashboard') {
        echo '<a href="' . limpar(BASE_URL . '/dashboard/index.php') . '">Dashboard</a>';
        echo '<span aria-hidden="true">/</span>';
    }

    if ($arquivoInicial) {
        echo '<span aria-current="page">' . limpar($secao) . '</span>';
    } else {
        echo '<a href="' . limpar(BASE_URL . $rotaSecao) . '">' . limpar($secao) . '</a>';
        echo '<span aria-hidden="true">/</span>';
        echo '<span aria-current="page">' . limpar($paginas[$arquivo] ?? 'Página') . '</span>';
    }
    echo '</nav>';
}

?>

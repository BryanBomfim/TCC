<?php
/**
 * includes/functions.php
 * -----------------------------------------------------------------
 * Funções auxiliares reutilizadas em todo o sistema: autenticação,
 * controle de acesso, sanitização, formatação e mensagens de sessão.
 * -----------------------------------------------------------------
 */

/** A assinatura muda quando senha, e-mail ou perfil mudam. Nunca sai da sessão. */
function assinaturaAutenticacao(array $usuario): string
{
    return hash('sha256', $usuario['senha'] . '|' . $usuario['email'] . '|' . $usuario['perfil'] . '|' . ($usuario['auth_version'] ?? 0));
}

/** Revalida a conta em cada requisição, inclusive após bloqueio administrativo. */
function revalidarSessao(): void
{
    global $pdo;
    if (!isset($_SESSION['usuario_id'])) {
        return;
    }
    $stmt = $pdo->prepare('SELECT id, nome, email, senha, perfil, ativo, email_verificado_em, auth_version FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => (int) $_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();
    if (!$usuario || !(int) $usuario['ativo'] || !$usuario['email_verificado_em']
        || !hash_equals(assinaturaAutenticacao($usuario), (string) ($_SESSION['auth_assinatura'] ?? ''))) {
        $_SESSION = [];
        session_regenerate_id(true);
        definirMensagem('erro', 'Sua conta foi atualizada ou o acesso foi encerrado. Entre novamente.');
        return;
    }
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_perfil'] = $usuario['perfil'];
}

/** Revoga credenciais auxiliares durante alterações sensíveis, na transação do chamador. */
function revogarCredenciaisUsuario(int $usuarioId): void
{
    global $pdo;
    foreach (['recuperacoes_senha', 'verificacoes_email'] as $tabela) {
        $stmt = $pdo->prepare("UPDATE {$tabela} SET utilizado_em = NOW() WHERE usuario_id = :id AND utilizado_em IS NULL");
        $stmt->execute([':id' => $usuarioId]);
    }
    $stmt = $pdo->prepare('UPDATE api_tokens SET revogado_em = NOW() WHERE usuario_id = :id AND revogado_em IS NULL');
    $stmt->execute([':id' => $usuarioId]);
}

/** Valida quantidades sem truncar frações, texto ou overflow do INT SQL. */
function quantidadeInteira(mixed $valor, int $minimo = 0): ?int
{
    if (!is_string($valor) && !is_int($valor)) {
        return null;
    }
    $texto = trim((string) $valor);
    if (!preg_match('/^[0-9]+$/D', $texto) || strlen($texto) > 10) {
        return null;
    }
    $numero = (int) $texto;
    return $numero >= $minimo && $numero <= 2147483647 ? $numero : null;
}

/** Verifica se existe um usuário autenticado na sessão. */
function estaLogado(): bool
{
    return isset($_SESSION['usuario_id']);
}

/** Redireciona o usuário para o login caso não esteja autenticado. */
function exigirLogin(): void
{
    if (estaLogado() && !empty($_SESSION['troca_senha_obrigatoria'])) {
        $rota = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if (!str_ends_with($rota, '/account/index.php') && !str_ends_with($rota, '/auth/logout.php')) {
            definirMensagem('erro', 'Defina uma senha exclusiva em Minha Conta para começar.');
            header('Location: ' . BASE_URL . '/account/index.php');
            exit;
        }
    }
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

/** Retorna um rótulo legível para a origem de uma movimentação. */
function formatarOrigemMovimentacao(?string $origem): string
{
    $rotulos = [
        'cadastro' => 'Cadastro do produto',
        'entrada' => 'Entrada manual',
        'saida' => 'Saída manual',
        'inventario' => 'Inventário',
        'ajuste' => 'Ajuste administrativo',
        'migracao' => 'Saldo inicial migrado',
        'manual' => 'Operação manual',
    ];

    return $rotulos[$origem ?? ''] ?? 'Não informada';
}

/** Monta a versão resumida do endereço mantida para compatibilidade com relatórios antigos. */
function montarEnderecoFornecedor(array $dados): ?string
{
    $partes = array_filter([
        trim((string) ($dados['logradouro'] ?? '')),
        trim((string) ($dados['numero'] ?? '')),
        trim((string) ($dados['complemento'] ?? '')),
        trim((string) ($dados['bairro'] ?? '')),
        trim((string) ($dados['cidade'] ?? ''))
            . (trim((string) ($dados['uf'] ?? '')) !== '' ? ' - ' . strtoupper(trim((string) $dados['uf'])) : ''),
        trim((string) ($dados['cep'] ?? '')),
    ], static fn (string $parte): bool => trim($parte, ' -') !== '');

    if ($partes === []) {
        return null;
    }

    return mb_substr(implode(', ', $partes), 0, 255);
}

/** @return array<string, string> */
function extrairDadosFornecedor(array $origem): array
{
    $campos = [
        'nome', 'razao_social', 'cnpj_cpf', 'telefone', 'email',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
    ];
    $dados = [];
    foreach ($campos as $campo) {
        $dados[$campo] = trim((string) ($origem[$campo] ?? ''));
    }
    $dados['cnpj_cpf'] = preg_replace('/\D/', '', $dados['cnpj_cpf']) ?? '';
    $dados['cep'] = preg_replace('/\D/', '', $dados['cep']) ?? '';
    $dados['uf'] = strtoupper($dados['uf']);
    $dados['endereco'] = montarEnderecoFornecedor($dados) ?? '';

    return $dados;
}

function validarDadosFornecedor(array $dados): ?string
{
    $limites = [
        'nome' => 150, 'razao_social' => 180, 'cnpj_cpf' => 14, 'telefone' => 20,
        'email' => 150, 'cep' => 8, 'logradouro' => 180, 'numero' => 20,
        'complemento' => 100, 'bairro' => 100, 'cidade' => 100, 'uf' => 2,
    ];

    if (($dados['nome'] ?? '') === '') {
        return 'O nome do fornecedor é obrigatório.';
    }
    foreach ($limites as $campo => $limite) {
        if (mb_strlen((string) ($dados[$campo] ?? '')) > $limite) {
            return 'Um dos campos ultrapassa o tamanho permitido.';
        }
    }
    if (($dados['email'] ?? '') !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Informe um e-mail válido.';
    }
    if (($dados['cnpj_cpf'] ?? '') !== '' && !in_array(strlen($dados['cnpj_cpf']), [11, 14], true)) {
        return 'Informe um CPF com 11 dígitos ou CNPJ com 14 dígitos.';
    }
    if (($dados['cep'] ?? '') !== '' && strlen($dados['cep']) !== 8) {
        return 'Informe um CEP com 8 dígitos.';
    }
    if (($dados['uf'] ?? '') !== '' && !preg_match('/^[A-Z]{2}$/', $dados['uf'])) {
        return 'Informe uma UF válida com duas letras.';
    }

    return null;
}

/** Valida senhas novas antes de gerar o hash. */
function validarNovaSenha(string $senha): ?string
{
    if ($senha === 'SmartControl@2026!') {
        return 'Escolha uma senha exclusiva, diferente da senha de demonstração.';
    }

    if (mb_strlen($senha) < 10) {
        return 'A senha deve possuir no mínimo 10 caracteres.';
    }

    if (strlen($senha) > 72) {
        return 'A senha deve possuir no máximo 72 bytes.';
    }

    return null;
}

/**
 * Verifica e registra limites para ações públicas sensíveis.
 * O identificador (por exemplo, e-mail) é persistido apenas como hash.
 */
function permitirRequisicaoLimitada(
    string $acao,
    string $identificador,
    int $limite,
    int $janelaSegundos
): bool {
    global $pdo;

    $acao = mb_substr(trim($acao), 0, 50);
    $hash = hash('sha256', strtolower(trim($identificador)));
    $ip = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'), 0, 45);
    $inicioJanela = date('Y-m-d H:i:s', time() - max(60, $janelaSegundos));

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM limites_requisicao
        WHERE acao = :acao
          AND identificador_hash = :hash
          AND ip = :ip
          AND criado_em >= :inicio
    ");
    $stmt->execute([
        ':acao' => $acao,
        ':hash' => $hash,
        ':ip' => $ip,
        ':inicio' => $inicioJanela,
    ]);

    if ((int) $stmt->fetchColumn() >= $limite) {
        return false;
    }

    $stmtInserir = $pdo->prepare("
        INSERT INTO limites_requisicao
            (acao, identificador_hash, ip)
        VALUES
            (:acao, :hash, :ip)
    ");
    $stmtInserir->execute([
        ':acao' => $acao,
        ':hash' => $hash,
        ':ip' => $ip,
    ]);

    if (random_int(1, 100) === 1) {
        $pdo->exec("
            DELETE FROM limites_requisicao
            WHERE criado_em < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
    }

    return true;
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
        if ($pdo->inTransaction()) {
            throw $e;
        }
        error_log(
            'Erro ao registrar auditoria: '
            . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine()
        );
    }
}

/** Registra eventos de segurança que podem ocorrer sem usuário autenticado. */
function registrarEventoSeguranca(string $acao, string $descricao): void
{
    global $pdo;

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
                ip
            )
            VALUES
            (
                NULL,
                NULL,
                :acao,
                'Autenticação',
                NULL,
                :descricao,
                :ip
            )
        ");
        $stmt->execute([
            ':acao' => mb_substr(strtoupper($acao), 0, 50),
            ':descricao' => mb_substr($descricao, 0, 500),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('Erro ao registrar evento de segurança: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
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
            . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine()
        );

        return null;
    }
}

/** Envia um alerta interno a todos os administradores ativos, com deduplicação. */
function notificarAdministradores(
    string $tipo,
    string $titulo,
    string $mensagem,
    ?string $entidade = null,
    ?int $entidadeId = null,
    ?string $link = null,
    int $deduplicarMinutos = 15
): void {
    global $pdo;

    try {
        $inicioDeduplicacao = date(
            'Y-m-d H:i:s',
            time() - (max(1, $deduplicarMinutos) * 60)
        );

        $administradores = $pdo->query("
            SELECT id FROM usuarios
            WHERE perfil = 'administrador' AND ativo = 1
        ")->fetchAll(PDO::FETCH_COLUMN);

        $stmtDuplicada = $pdo->prepare("
            SELECT 1 FROM notificacoes
            WHERE usuario_id = :usuario_id
              AND titulo = :titulo
              AND mensagem = :mensagem
              AND criado_em >= :inicio
            LIMIT 1
        ");

        foreach ($administradores as $administradorId) {
            $stmtDuplicada->bindValue(':usuario_id', (int) $administradorId, PDO::PARAM_INT);
            $stmtDuplicada->bindValue(':titulo', mb_substr($titulo, 0, 150));
            $stmtDuplicada->bindValue(':mensagem', mb_substr($mensagem, 0, 500));
            $stmtDuplicada->bindValue(':inicio', $inicioDeduplicacao);
            $stmtDuplicada->execute();

            if (!$stmtDuplicada->fetchColumn()) {
                criarNotificacao((int) $administradorId, $tipo, $titulo, $mensagem, $entidade, $entidadeId, $link);
            }
        }
    } catch (Throwable $e) {
        error_log('Erro ao notificar administradores: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
    }
}

function obterConfiguracao(string $chave, string $padrao = ''): string
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave = :chave LIMIT 1');
        $stmt->execute([':chave' => $chave]);
        $valor = $stmt->fetchColumn();
        return $valor !== false ? (string) $valor : $padrao;
    } catch (Throwable $e) {
        error_log('Erro ao ler configuração: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
        return $padrao;
    }
}

/** @return array{pagina:int,por_pagina:int,total_paginas:int,offset:int} */
function calcularPaginacao(int $totalRegistros, int $porPagina = 25): array
{
    $porPagina = min(100, max(1, $porPagina));
    $totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
    $pagina = min($totalPaginas, max(1, (int) ($_GET['pagina'] ?? 1)));

    return [
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
        'total_paginas' => $totalPaginas,
        'offset' => ($pagina - 1) * $porPagina,
    ];
}

function exibirPaginacao(array $paginacao, string $rotulo = 'Paginação'): void
{
    $pagina = (int) ($paginacao['pagina'] ?? 1);
    $total = (int) ($paginacao['total_paginas'] ?? 1);
    if ($total <= 1) {
        return;
    }

    echo '<nav class="paginacao" aria-label="' . limpar($rotulo) . '">';
    for ($i = max(1, $pagina - 2); $i <= min($total, $pagina + 2); $i++) {
        $url = '?' . http_build_query(array_merge($_GET, ['pagina' => $i]));
        $classe = $i === $pagina ? ' class="ativo" aria-current="page"' : '';
        echo '<a' . $classe . ' href="' . limpar($url) . '">' . $i . '</a>';
    }
    echo '</nav>';
}

function notificarMovimentacaoIncomum(array $resultado, string $tipo): void
{
    $limite = max(1, (int) obterConfiguracao('limite_movimentacao_incomum', '100'));
    if ((int) ($resultado['quantidade'] ?? 0) < $limite) {
        return;
    }

    $produtoId = (int) ($resultado['produto_id'] ?? 0);
    notificarAdministradores(
        'alerta',
        'Movimentação incomum',
        ucfirst($tipo) . ' de ' . (int) $resultado['quantidade'] . ' unidades no produto "'
            . (string) ($resultado['produto_nome'] ?? 'Produto') . '".',
        'produto',
        $produtoId,
        BASE_URL . '/products/view.php?id=' . $produtoId,
        5
    );
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
            . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine()
        );

        return null;
    }
}
?>

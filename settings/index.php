<?php
/** Configurações gerais do SmartControl. */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $nomeSistema = trim($_POST['nome_sistema'] ?? '');
        $nomeEmpresa = trim($_POST['nome_empresa'] ?? '');
        $estoqueMinimoPadrao = trim($_POST['estoque_minimo_padrao'] ?? '');
        $limiteMovimentacaoIncomum = trim($_POST['limite_movimentacao_incomum'] ?? '');

        if ($nomeSistema === '' || $nomeEmpresa === '') {
            $erro = 'Preencha os campos obrigatórios.';
        } elseif (mb_strlen($nomeSistema) > 100 || mb_strlen($nomeEmpresa) > 150) {
            $erro = 'O nome do sistema ou da empresa ultrapassa o tamanho permitido.';
        } elseif (
            filter_var($estoqueMinimoPadrao, FILTER_VALIDATE_INT) === false
            || (int) $estoqueMinimoPadrao < 0
            || filter_var($limiteMovimentacaoIncomum, FILTER_VALIDATE_INT) === false
            || (int) $limiteMovimentacaoIncomum < 1
        ) {
            $erro = 'Informe valores inteiros válidos para os limites de estoque.';
        } else {
            $valores = [
                'nome_sistema' => $nomeSistema,
                'nome_empresa' => $nomeEmpresa,
                'estoque_minimo_padrao' => (int) $estoqueMinimoPadrao,
                'limite_movimentacao_incomum' => (int) $limiteMovimentacaoIncomum,
            ];
            $anteriores = [];
            foreach ($pdo->query('SELECT chave, valor FROM configuracoes')->fetchAll() as $linhaAnterior) {
                $anteriores[$linhaAnterior['chave']] = $linhaAnterior['valor'];
            }

            $stmtSalvar = $pdo->prepare("
                INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)
                ON DUPLICATE KEY UPDATE valor = :valor_atualizado
            ");
            foreach ($valores as $chave => $valor) {
                $stmtSalvar->execute([
                    ':chave' => $chave,
                    ':valor' => $valor,
                    ':valor_atualizado' => $valor,
                ]);
            }

            registrarAuditoria(
                'ALTERAR',
                'Configurações',
                null,
                'Configurações gerais do sistema atualizadas.',
                array_intersect_key($anteriores, $valores),
                $valores
            );
            definirMensagem('sucesso', 'Configurações atualizadas com sucesso!');
            header('Location: ' . BASE_URL . '/settings/index.php');
            exit;
        }
    }
}

$configuracoesAtuais = [];
foreach ($pdo->query('SELECT chave, valor FROM configuracoes')->fetchAll() as $linha) {
    $configuracoesAtuais[$linha['chave']] = $linha['valor'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Configurações do Sistema</h1>

    <div class="painel">
        <h2>Integrações</h2>
        <p>Gerencie credenciais revogáveis para a API REST de leitura.</p>
        <a class="botao botao-secundario" href="<?php echo BASE_URL; ?>/settings/api_tokens.php">Gerenciar tokens da API</a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div><?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome_sistema">Nome do Sistema *</label>
        <input type="text" name="nome_sistema" id="nome_sistema" required maxlength="100"
            value="<?php echo limpar($configuracoesAtuais['nome_sistema'] ?? 'SmartControl'); ?>">

        <label for="nome_empresa">Nome da Empresa *</label>
        <input type="text" name="nome_empresa" id="nome_empresa" required maxlength="150"
            value="<?php echo limpar($configuracoesAtuais['nome_empresa'] ?? ''); ?>">

        <label for="estoque_minimo_padrao">Estoque mínimo padrão para novos produtos</label>
        <input type="number" min="0" name="estoque_minimo_padrao" id="estoque_minimo_padrao" required
            value="<?php echo limpar($configuracoesAtuais['estoque_minimo_padrao'] ?? '5'); ?>">

        <label for="limite_movimentacao_incomum">Alertar movimentações a partir de (unidades)</label>
        <input type="number" min="1" name="limite_movimentacao_incomum" id="limite_movimentacao_incomum" required
            value="<?php echo limpar($configuracoesAtuais['limite_movimentacao_incomum'] ?? '100'); ?>">

        <div class="acoes-formulario"><button type="submit" class="botao">Salvar Configurações</button></div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

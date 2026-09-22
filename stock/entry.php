<?php
/**
 * Registra entrada de estoque com rastreabilidade completa.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$produtoSelecionado = (int) ($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);
$fornecedorSelecionado = (int) ($_POST['fornecedor_id'] ?? 0);
$quantidadeInformada = trim((string) ($_POST['quantidade'] ?? ''));
$valorUnitarioInformado = trim((string) ($_POST['valor_unitario'] ?? ''));
$referenciaInformada = trim((string) ($_POST['referencia'] ?? ''));
$observacaoInformada = trim((string) ($_POST['observacao'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $fornecedorId = (int) ($_POST['fornecedor_id'] ?? 0);
        $quantidade = (int) ($_POST['quantidade'] ?? 0);
        $valorUnitario = str_replace(',', '.', trim((string) ($_POST['valor_unitario'] ?? '')));
        $referencia = trim((string) ($_POST['referencia'] ?? ''));
        $observacao = trim((string) ($_POST['observacao'] ?? ''));

        $produtoSelecionado = $produtoId;
        $fornecedorSelecionado = $fornecedorId;

        if ($produtoId <= 0) {
            $erro = 'Selecione um produto.';
        } elseif ($quantidade <= 0) {
            $erro = 'Informe uma quantidade válida (maior que zero).';
        } elseif ($valorUnitario !== '' && (!is_numeric($valorUnitario) || (float) $valorUnitario < 0)) {
            $erro = 'Informe um valor unitário válido.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmtProduto = $pdo->prepare("
                    SELECT id, nome, quantidade_estoque, unidade, fornecedor_id
                    FROM produtos
                    WHERE id = :id AND ativo = 1
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmtProduto->execute([':id' => $produtoId]);
                $produtoAtual = $stmtProduto->fetch();

                if (!$produtoAtual) {
                    throw new RuntimeException('Produto não encontrado ou inativo.');
                }

                if ($fornecedorId > 0) {
                    $stmtFornecedor = $pdo->prepare("SELECT id FROM fornecedores WHERE id = :id AND ativo = 1 LIMIT 1");
                    $stmtFornecedor->execute([':id' => $fornecedorId]);
                    if (!$stmtFornecedor->fetchColumn()) {
                        throw new RuntimeException('Fornecedor inválido ou inativo.');
                    }
                } else {
                    $fornecedorId = (int) ($produtoAtual['fornecedor_id'] ?? 0);
                }

                $estoqueAntes = (int) $produtoAtual['quantidade_estoque'];
                $estoqueDepois = $estoqueAntes + $quantidade;

                $stmtInsert = $pdo->prepare("
                    INSERT INTO movimentacoes
                    (
                        produto_id,
                        usuario_id,
                        fornecedor_id,
                        tipo,
                        quantidade,
                        saldo_anterior,
                        saldo_posterior,
                        origem,
                        motivo,
                        valor_unitario,
                        referencia,
                        observacao
                    )
                    VALUES
                    (
                        :produto_id,
                        :usuario_id,
                        :fornecedor_id,
                        'entrada',
                        :quantidade,
                        :saldo_anterior,
                        :saldo_posterior,
                        'manual',
                        'Entrada de estoque',
                        :valor_unitario,
                        :referencia,
                        :observacao
                    )
                ");
                $stmtInsert->execute([
                    ':produto_id' => $produtoId,
                    ':usuario_id' => (int) $_SESSION['usuario_id'],
                    ':fornecedor_id' => $fornecedorId > 0 ? $fornecedorId : null,
                    ':quantidade' => $quantidade,
                    ':saldo_anterior' => $estoqueAntes,
                    ':saldo_posterior' => $estoqueDepois,
                    ':valor_unitario' => $valorUnitario !== '' ? (float) $valorUnitario : null,
                    ':referencia' => $referencia !== '' ? mb_substr($referencia, 0, 100) : null,
                    ':observacao' => $observacao !== '' ? mb_substr($observacao, 0, 500) : null,
                ]);
                $movimentacaoId = (int) $pdo->lastInsertId();

                $stmtUpdate = $pdo->prepare("UPDATE produtos SET quantidade_estoque = :estoque WHERE id = :id");
                $stmtUpdate->execute([
                    ':estoque' => $estoqueDepois,
                    ':id' => $produtoId,
                ]);

                registrarAuditoria(
                    'ENTRADA',
                    'Estoque',
                    $movimentacaoId,
                    'Entrada de ' . $quantidade . ' ' . $produtoAtual['unidade'] . ' no produto "' . $produtoAtual['nome'] . '".',
                    [
                        'produto_id' => $produtoId,
                        'saldo_anterior' => $estoqueAntes,
                    ],
                    [
                        'produto_id' => $produtoId,
                        'quantidade' => $quantidade,
                        'saldo_posterior' => $estoqueDepois,
                        'fornecedor_id' => $fornecedorId > 0 ? $fornecedorId : null,
                        'valor_unitario' => $valorUnitario !== '' ? (float) $valorUnitario : null,
                        'referencia' => $referencia ?: null,
                    ]
                );

                $pdo->commit();
                definirMensagem('sucesso', 'Entrada de estoque registrada com sucesso!');
                header('Location: ' . BASE_URL . '/products/view.php?id=' . $produtoId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Erro ao registrar entrada de estoque: ' . $e->getMessage());
                $erro = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Ocorreu um erro ao registrar a entrada. Tente novamente.';
            }
        }
    }
}

$produtos = $pdo->query("
    SELECT id, nome, quantidade_estoque, unidade, fornecedor_id
    FROM produtos
    WHERE ativo = 1
    ORDER BY nome ASC
")->fetchAll();

$fornecedores = $pdo->query("
    SELECT id, nome
    FROM fornecedores
    WHERE ativo = 1
    ORDER BY nome ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <h1>Registrar Entrada de Estoque</h1>
    <p class="descricao-pagina">Registre a origem da entrada para manter a rastreabilidade do estoque.</p>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="produto_id">Produto *</label>
        <select name="produto_id" id="produto_id" required>
            <option value="">Selecione um produto...</option>
            <?php foreach ($produtos as $produto): ?>
                <option value="<?php echo (int) $produto['id']; ?>"
                    data-fornecedor-id="<?php echo (int) ($produto['fornecedor_id'] ?? 0); ?>"
                    <?php echo $produtoSelecionado === (int) $produto['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($produto['nome']); ?>
                    (estoque atual: <?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="quantidade">Quantidade *</label>
        <input type="number" name="quantidade" id="quantidade" min="1" required value="<?php echo limpar($quantidadeInformada); ?>">

        <label for="fornecedor_id">Fornecedor</label>
        <select name="fornecedor_id" id="fornecedor_id">
            <option value="0">Usar fornecedor padrão do produto</option>
            <?php foreach ($fornecedores as $fornecedor): ?>
                <option value="<?php echo (int) $fornecedor['id']; ?>" <?php echo $fornecedorSelecionado === (int) $fornecedor['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($fornecedor['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="valor_unitario">Valor de compra por unidade</label>
        <input type="number" name="valor_unitario" id="valor_unitario" min="0" step="0.01" inputmode="decimal"
            value="<?php echo limpar($valorUnitarioInformado); ?>" placeholder="0,00">

        <label for="referencia">Referência / Nota fiscal</label>
        <input type="text" name="referencia" id="referencia" maxlength="100"
            value="<?php echo limpar($referenciaInformada); ?>" placeholder="Ex: NF 000123">

        <label for="observacao">Observação</label>
        <textarea name="observacao" id="observacao" rows="3" maxlength="500"
            placeholder="Informações adicionais sobre a entrada"><?php echo limpar($observacaoInformada); ?></textarea>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao-sucesso">Registrar Entrada</button>
            <a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

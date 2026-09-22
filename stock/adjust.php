<?php
/**
 * Ajuste administrativo de estoque.
 * Exige justificativa e registra saldo anterior/posterior.
 */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$erro = '';
$produtoSelecionado = (int) ($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);
$novaQuantidadeInformada = trim((string) ($_POST['nova_quantidade'] ?? ''));
$justificativaInformada = trim((string) ($_POST['justificativa'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $novaQuantidade = filter_var($_POST['nova_quantidade'] ?? null, FILTER_VALIDATE_INT);
        $justificativa = trim((string) ($_POST['justificativa'] ?? ''));
        $produtoSelecionado = $produtoId;

        if ($produtoId <= 0) {
            $erro = 'Selecione um produto.';
        } elseif ($novaQuantidade === false || $novaQuantidade < 0) {
            $erro = 'Informe uma nova quantidade válida, igual ou maior que zero.';
        } elseif (mb_strlen($justificativa) < 5) {
            $erro = 'Informe uma justificativa com pelo menos 5 caracteres.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmtProduto = $pdo->prepare("
                    SELECT id, nome, quantidade_estoque, estoque_minimo, unidade
                    FROM produtos
                    WHERE id = :id
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmtProduto->execute([':id' => $produtoId]);
                $produto = $stmtProduto->fetch();

                if (!$produto) {
                    throw new RuntimeException('Produto não encontrado.');
                }

                $estoqueAntes = (int) $produto['quantidade_estoque'];
                $estoqueDepois = (int) $novaQuantidade;
                $diferenca = $estoqueDepois - $estoqueAntes;

                if ($diferenca === 0) {
                    throw new RuntimeException('A nova quantidade é igual ao estoque atual. Nenhum ajuste foi necessário.');
                }

                $tipo = $diferenca > 0 ? 'entrada' : 'saida';
                $quantidadeMovimento = abs($diferenca);

                $stmtMov = $pdo->prepare("
                    INSERT INTO movimentacoes
                    (
                        produto_id,
                        usuario_id,
                        tipo,
                        quantidade,
                        saldo_anterior,
                        saldo_posterior,
                        origem,
                        motivo,
                        observacao
                    )
                    VALUES
                    (
                        :produto_id,
                        :usuario_id,
                        :tipo,
                        :quantidade,
                        :saldo_anterior,
                        :saldo_posterior,
                        'ajuste',
                        'Ajuste administrativo',
                        :observacao
                    )
                ");
                $stmtMov->execute([
                    ':produto_id' => $produtoId,
                    ':usuario_id' => (int) $_SESSION['usuario_id'],
                    ':tipo' => $tipo,
                    ':quantidade' => $quantidadeMovimento,
                    ':saldo_anterior' => $estoqueAntes,
                    ':saldo_posterior' => $estoqueDepois,
                    ':observacao' => mb_substr($justificativa, 0, 500),
                ]);
                $movimentacaoId = (int) $pdo->lastInsertId();

                $stmtUpdate = $pdo->prepare("UPDATE produtos SET quantidade_estoque = :quantidade WHERE id = :id");
                $stmtUpdate->execute([
                    ':quantidade' => $estoqueDepois,
                    ':id' => $produtoId,
                ]);

                registrarAuditoria(
                    'AJUSTE',
                    'Estoque',
                    $movimentacaoId,
                    'Ajuste administrativo do produto "' . $produto['nome'] . '".',
                    [
                        'produto_id' => $produtoId,
                        'quantidade_estoque' => $estoqueAntes,
                    ],
                    [
                        'produto_id' => $produtoId,
                        'quantidade_estoque' => $estoqueDepois,
                        'diferenca' => $diferenca,
                        'justificativa' => $justificativa,
                    ]
                );

                $pdo->commit();
                definirMensagem('sucesso', 'Ajuste de estoque registrado com sucesso!');
                header('Location: ' . BASE_URL . '/products/view.php?id=' . $produtoId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Erro ao ajustar estoque: ' . $e->getMessage());
                $erro = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Ocorreu um erro ao ajustar o estoque.';
            }
        }
    }
}

$produtos = $pdo->query("
    SELECT id, nome, quantidade_estoque, unidade
    FROM produtos
    ORDER BY nome ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <h1>Ajuste Administrativo de Estoque</h1>
    <p class="descricao-pagina">Use somente para corrigir divergências justificadas. Toda alteração fica registrada na auditoria.</p>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="produto_id">Produto *</label>
        <select name="produto_id" id="produto_id" required>
            <option value="">Selecione um produto...</option>
            <?php foreach ($produtos as $produto): ?>
                <option value="<?php echo (int) $produto['id']; ?>" <?php echo $produtoSelecionado === (int) $produto['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($produto['nome']); ?>
                    (atual: <?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="nova_quantidade">Nova quantidade em estoque *</label>
        <input type="number" name="nova_quantidade" id="nova_quantidade" min="0" required
            value="<?php echo limpar($novaQuantidadeInformada); ?>">

        <label for="justificativa">Justificativa *</label>
        <textarea name="justificativa" id="justificativa" rows="4" minlength="5" maxlength="500" required
            placeholder="Explique por que o saldo precisa ser corrigido"><?php echo limpar($justificativaInformada); ?></textarea>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao-alerta">Aplicar Ajuste</button>
            <a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

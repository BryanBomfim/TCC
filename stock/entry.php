<?php
/**
 * stock/entry.php
 * Registra uma entrada de estoque para um produto, atualizando
 * a quantidade em estoque e gravando o histórico da movimentação.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$produtoSelecionado = (int) ($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $quantidade = (int) ($_POST['quantidade'] ?? 0);
        $observacao = trim($_POST['observacao'] ?? '');

        if ($produtoId <= 0) {
            $erro = 'Selecione um produto.';
        } elseif ($quantidade <= 0) {
            $erro = 'Informe uma quantidade válida (maior que zero).';
        } else {
            try {

                $pdo->beginTransaction();

                /*
                 * Busca o produto e bloqueia temporariamente o registro
                 * durante a movimentação.
                 */
                $stmtProduto = $pdo->prepare("
        SELECT
            id,
            nome,
            quantidade_estoque,
            unidade
        FROM produtos
        WHERE id = :id
          AND ativo = 1
        LIMIT 1
        FOR UPDATE
    ");

                $stmtProduto->execute([
                    ':id' => $produtoId
                ]);

                $produtoAtual = $stmtProduto->fetch();

                if (!$produtoAtual) {

                    $pdo->rollBack();

                    $erro = 'Produto não encontrado ou inativo.';

                } else {

                    /*
                     * Quantidade existente antes da entrada.
                     */
                    $estoqueAntes =
                        (int) $produtoAtual['quantidade_estoque'];

                    /*
                     * Quantidade que haverá depois da entrada.
                     */
                    $estoqueDepois =
                        $estoqueAntes + $quantidade;

                    /*
                     * Registra a movimentação.
                     */
                    $stmtInsert = $pdo->prepare("
            INSERT INTO movimentacoes
            (
                produto_id,
                usuario_id,
                tipo,
                quantidade,
                observacao
            )
            VALUES
            (
                :produto_id,
                :usuario_id,
                'entrada',
                :quantidade,
                :observacao
            )
        ");

                    $stmtInsert->execute([
                        ':produto_id' => $produtoId,
                        ':usuario_id' => $_SESSION['usuario_id'],
                        ':quantidade' => $quantidade,
                        ':observacao' => $observacao ?: null
                    ]);

                    $movimentacaoId =
                        (int) $pdo->lastInsertId();

                    /*
                     * Atualiza o estoque.
                     */
                    $stmtUpdate = $pdo->prepare("
            UPDATE produtos

            SET quantidade_estoque = :estoque

            WHERE id = :id
        ");

                    $stmtUpdate->execute([
                        ':estoque' => $estoqueDepois,
                        ':id' => $produtoId
                    ]);

                    /*
                     * Registra a ação na auditoria.
                     */
                    registrarAuditoria(
                        'ENTRADA',
                        'Estoque',
                        $movimentacaoId,
                        'Entrada de '
                        . $quantidade
                        . ' '
                        . $produtoAtual['unidade']
                        . ' no produto "'
                        . $produtoAtual['nome']
                        . '".',
                        [
                            'produto_id' => $produtoId,
                            'produto' => $produtoAtual['nome'],
                            'estoque' => $estoqueAntes
                        ],
                        [
                            'produto_id' => $produtoId,
                            'produto' => $produtoAtual['nome'],
                            'quantidade_entrada' => $quantidade,
                            'estoque' => $estoqueDepois,
                            'observacao' => $observacao ?: null
                        ]
                    );

                    /*
                     * Confirma estoque + movimentação + auditoria.
                     */
                    $pdo->commit();

                    definirMensagem(
                        'sucesso',
                        'Entrada de estoque registrada com sucesso!'
                    );

                    header(
                        'Location: '
                        . BASE_URL
                        . '/products/view.php?id='
                        . $produtoId
                    );

                    exit;
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $erro =
                    'Ocorreu um erro ao registrar a entrada. '
                    . 'Tente novamente.';
            }
        }
        $produtoSelecionado = $produtoId;
    }
}

$produtos = $pdo->query("SELECT id, nome, quantidade_estoque, unidade FROM produtos WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Registrar Entrada de Estoque</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="produto_id">Produto *</label>
        <select name="produto_id" id="produto_id" required>
            <option value="">Selecione um produto...</option>
            <?php foreach ($produtos as $produto): ?>
                <option value="<?php echo $produto['id']; ?>" <?php echo $produtoSelecionado == $produto['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($produto['nome']); ?> (estoque atual:
                    <?php echo (int) $produto['quantidade_estoque']; ?>     <?php echo limpar($produto['unidade']); ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="quantidade">Quantidade *</label>
        <input type="number" name="quantidade" id="quantidade" min="1" required>

        <label for="observacao">Observação</label>
        <textarea name="observacao" id="observacao" rows="3"
            placeholder="Ex: Compra do fornecedor X, nota fiscal nº..."></textarea>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao-sucesso">Registrar Entrada</button>
            <a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Registro de entrada de estoque.
 */

use SmartControl\Services\StockService;

require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$produtoSelecionado = (int) ($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);
$fornecedorSelecionado = (int) ($_POST['fornecedor_id'] ?? 0);
$valorUnitarioInformado = trim($_POST['valor_unitario'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $produtoSelecionado = (int) ($_POST['produto_id'] ?? 0);
        $quantidade = quantidadeInteira($_POST['quantidade'] ?? '', 1) ?? 0;
        $fornecedorSelecionado = (int) ($_POST['fornecedor_id'] ?? 0);
        $valorTexto = str_replace(',', '.', trim($_POST['valor_unitario'] ?? ''));
        $observacao = trim($_POST['observacao'] ?? '');

        if ($valorTexto !== '' && !is_numeric($valorTexto)) {
            $erro = 'Informe um valor de compra válido.';
        } else {
            try {
                $pdo->beginTransaction();
                $servico = new StockService($pdo);
                $resultado = $servico->registrarEntrada(
                    (int) $_SESSION['usuario_id'],
                    $produtoSelecionado,
                    $quantidade,
                    $fornecedorSelecionado > 0 ? $fornecedorSelecionado : null,
                    $valorTexto !== '' ? (float) $valorTexto : null,
                    $observacao
                );

                registrarAuditoria(
                    'ENTRADA',
                    'Estoque',
                    (int) $resultado['movimentacao_id'],
                    'Entrada de ' . $resultado['quantidade'] . ' ' . $resultado['unidade']
                        . ' no produto "' . $resultado['produto_nome'] . '".',
                    [
                        'produto_id' => $resultado['produto_id'],
                        'estoque' => $resultado['saldo_anterior'],
                    ],
                    [
                        'produto_id' => $resultado['produto_id'],
                        'quantidade_entrada' => $resultado['quantidade'],
                        'estoque' => $resultado['saldo_posterior'],
                        'fornecedor_id' => $fornecedorSelecionado ?: null,
                        'valor_unitario' => $valorTexto !== '' ? (float) $valorTexto : null,
                        'observacao' => $observacao ?: null,
                    ]
                );

                notificarMovimentacaoIncomum($resultado, 'entrada');
                $pdo->commit();
                definirMensagem('sucesso', 'Entrada de estoque registrada com sucesso!');
                header('Location: ' . BASE_URL . '/products/view.php?id=' . $resultado['produto_id']);
                exit;
            } catch (DomainException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $erro = $e->getMessage();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('Erro ao registrar entrada de estoque: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
                $erro = 'Ocorreu um erro ao registrar a entrada. Tente novamente.';
            }
        }
    }
}

$produtos = $pdo->query("
    SELECT id, sku, codigo_barras, nome, quantidade_estoque, unidade
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
    <h1>Registrar Entrada de Estoque</h1>
    <p class="descricao-pagina">A entrada atualiza o saldo e cria um registro rastreável no histórico.</p>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="produto_id">Produto *</label>
        <select name="produto_id" id="produto_id" required data-product-select>
            <option value="">Selecione um produto...</option>
            <?php foreach ($produtos as $produto): ?>
                <option value="<?php echo (int) $produto['id']; ?>"
                    data-code="<?php echo limpar($produto['codigo_barras'] ?: $produto['sku']); ?>"
                    data-sku="<?php echo limpar($produto['sku']); ?>"
                    <?php echo $produtoSelecionado === (int) $produto['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($produto['nome']); ?>
                    (<?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="codigo_produto">Localizar por SKU ou código de barras</label>
        <input type="search" id="codigo_produto" inputmode="numeric" autocomplete="off"
            data-product-code placeholder="Leia ou digite o código e pressione Enter">
        <small id="codigo-feedback" data-product-code-feedback aria-live="polite"></small>

        <label for="quantidade">Quantidade *</label>
        <input type="number" name="quantidade" id="quantidade" min="1" required
            value="<?php echo limpar($_POST['quantidade'] ?? ''); ?>">

        <label for="fornecedor_id">Fornecedor</label>
        <select name="fornecedor_id" id="fornecedor_id">
            <option value="">Não informado</option>
            <?php foreach ($fornecedores as $fornecedor): ?>
                <option value="<?php echo (int) $fornecedor['id']; ?>"
                    <?php echo $fornecedorSelecionado === (int) $fornecedor['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($fornecedor['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="valor_unitario">Valor unitário de compra</label>
        <input type="number" name="valor_unitario" id="valor_unitario" min="0" step="0.01"
            value="<?php echo limpar($valorUnitarioInformado); ?>" placeholder="0,00">

        <label for="observacao">Observação</label>
        <textarea name="observacao" id="observacao" rows="3" maxlength="255"
            placeholder="Nota fiscal, lote ou detalhe da compra."><?php echo limpar($_POST['observacao'] ?? ''); ?></textarea>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao-sucesso">Registrar Entrada</button>
            <a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

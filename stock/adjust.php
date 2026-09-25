<?php
/**
 * Ajuste administrativo de saldo com justificativa obrigatória.
 */

use SmartControl\Services\StockService;

require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$erro = '';
$produtoSelecionado = (int) ($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $produtoSelecionado = (int) ($_POST['produto_id'] ?? 0);
        $novoSaldoTexto = trim($_POST['novo_saldo'] ?? '');
        $justificativa = trim($_POST['justificativa'] ?? '');

        if ($novoSaldoTexto === '' || filter_var($novoSaldoTexto, FILTER_VALIDATE_INT) === false) {
            $erro = 'Informe um novo saldo inteiro válido.';
        } else {
            try {
                $pdo->beginTransaction();
                $servico = new StockService($pdo);
                $resultado = $servico->ajustarSaldo(
                    (int) $_SESSION['usuario_id'],
                    $produtoSelecionado,
                    (int) $novoSaldoTexto,
                    $justificativa
                );

                registrarAuditoria(
                    'AJUSTE',
                    'Estoque',
                    (int) $resultado['movimentacao_id'],
                    'Ajuste administrativo do produto "' . $resultado['produto_nome'] . '".',
                    [
                        'produto_id' => $resultado['produto_id'],
                        'estoque' => $resultado['saldo_anterior'],
                    ],
                    [
                        'produto_id' => $resultado['produto_id'],
                        'estoque' => $resultado['saldo_posterior'],
                        'diferenca' => $resultado['saldo_posterior'] - $resultado['saldo_anterior'],
                        'justificativa' => $justificativa,
                    ]
                );

                $pdo->commit();
                definirMensagem('sucesso', 'Saldo ajustado com sucesso e registrado no histórico.');
                header('Location: ' . BASE_URL . '/products/view.php?id=' . $resultado['produto_id']);
                exit;
            } catch (DomainException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $erro = $e->getMessage();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('Erro ao ajustar estoque: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
                $erro = 'Ocorreu um erro ao ajustar o saldo. Tente novamente.';
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

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Ajuste Administrativo de Estoque</h1>
    <p class="descricao-pagina">
        Informe o saldo físico correto. O sistema registra o saldo anterior, a diferença e o responsável.
    </p>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <div class="alerta alerta-aviso" role="note">
        Use esta operação apenas para correções justificadas. Entradas e saídas comuns possuem telas próprias.
    </div>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="produto_id">Produto *</label>
        <select name="produto_id" id="produto_id" required data-product-select>
            <option value="">Selecione um produto...</option>
            <?php foreach ($produtos as $produto): ?>
                <option value="<?php echo (int) $produto['id']; ?>"
                    data-code="<?php echo limpar($produto['codigo_barras'] ?: $produto['sku']); ?>"
                    data-sku="<?php echo limpar($produto['sku']); ?>"
                    data-stock="<?php echo (int) $produto['quantidade_estoque']; ?>"
                    <?php echo $produtoSelecionado === (int) $produto['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($produto['nome']); ?>
                    (saldo: <?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="codigo_produto">Localizar por SKU ou código de barras</label>
        <input type="search" id="codigo_produto" autocomplete="off" data-product-code
            placeholder="Leia ou digite o código e pressione Enter">
        <small data-product-code-feedback aria-live="polite"></small>

        <label for="novo_saldo">Novo saldo físico *</label>
        <input type="number" name="novo_saldo" id="novo_saldo" min="0" required
            value="<?php echo limpar($_POST['novo_saldo'] ?? ''); ?>">

        <label for="justificativa">Justificativa *</label>
        <textarea name="justificativa" id="justificativa" rows="4" minlength="10" maxlength="255"
            required placeholder="Descreva por que o saldo precisa ser corrigido."><?php echo limpar($_POST['justificativa'] ?? ''); ?></textarea>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao-alerta"
                data-confirm="Confirma o ajuste administrativo deste produto?">Confirmar Ajuste</button>
            <a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

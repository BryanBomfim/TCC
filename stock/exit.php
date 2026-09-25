<?php
/**
 * Registro de saída de estoque com bloqueio concorrente e alerta de saldo.
 */

use SmartControl\Services\StockService;

require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$produtoSelecionado = (int) ($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);
$motivosPermitidos = ['Venda', 'Consumo interno', 'Perda ou avaria', 'Transferência', 'Outro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $produtoSelecionado = (int) ($_POST['produto_id'] ?? 0);
        $quantidade = quantidadeInteira($_POST['quantidade'] ?? '', 1) ?? 0;
        $motivo = trim($_POST['motivo'] ?? '');
        $observacao = trim($_POST['observacao'] ?? '');

        if (!in_array($motivo, $motivosPermitidos, true)) {
            $erro = 'Selecione um motivo válido para a saída.';
        } else {
            try {
                $pdo->beginTransaction();
                $servico = new StockService($pdo);
                $resultado = $servico->registrarSaida(
                    (int) $_SESSION['usuario_id'],
                    $produtoSelecionado,
                    $quantidade,
                    $motivo,
                    $observacao
                );

                registrarAuditoria(
                    'SAIDA',
                    'Estoque',
                    (int) $resultado['movimentacao_id'],
                    'Saída de ' . $resultado['quantidade'] . ' ' . $resultado['unidade']
                        . ' do produto "' . $resultado['produto_nome'] . '".',
                    [
                        'produto_id' => $resultado['produto_id'],
                        'estoque' => $resultado['saldo_anterior'],
                    ],
                    [
                        'produto_id' => $resultado['produto_id'],
                        'quantidade_saida' => $resultado['quantidade'],
                        'estoque' => $resultado['saldo_posterior'],
                        'motivo' => $motivo,
                        'observacao' => $observacao ?: null,
                    ]
                );

                notificarMovimentacaoIncomum($resultado, 'saída');

                $tipoNotificacao = null;
                $tituloNotificacao = '';
                $mensagemNotificacao = '';

                if ($resultado['saldo_posterior'] === 0 && $resultado['saldo_anterior'] > 0) {
                    $tipoNotificacao = 'critico';
                    $tituloNotificacao = 'Produto sem estoque';
                    $mensagemNotificacao = 'O produto "' . $resultado['produto_nome'] . '" ficou sem estoque.';
                } elseif (
                    $resultado['saldo_posterior'] <= $resultado['estoque_minimo']
                    && $resultado['saldo_posterior'] > 0
                    && $resultado['saldo_anterior'] > $resultado['estoque_minimo']
                ) {
                    $tipoNotificacao = 'alerta';
                    $tituloNotificacao = 'Estoque baixo';
                    $mensagemNotificacao = 'O produto "' . $resultado['produto_nome']
                        . '" atingiu o estoque mínimo. Saldo atual: '
                        . $resultado['saldo_posterior'] . ' ' . $resultado['unidade'] . '.';
                }

                if ($tipoNotificacao !== null) {
                    $administradores = $pdo->query("
                        SELECT id FROM usuarios
                        WHERE perfil = 'administrador' AND ativo = 1
                    ")->fetchAll();

                    foreach ($administradores as $administrador) {
                        criarNotificacao(
                            (int) $administrador['id'],
                            $tipoNotificacao,
                            $tituloNotificacao,
                            $mensagemNotificacao,
                            'produto',
                            (int) $resultado['produto_id'],
                            BASE_URL . '/products/view.php?id=' . $resultado['produto_id']
                        );
                    }
                }

                $pdo->commit();
                definirMensagem('sucesso', 'Saída de estoque registrada com sucesso!');
                header('Location: ' . BASE_URL . '/products/view.php?id=' . $resultado['produto_id']);
                exit;
            } catch (DomainException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $erro = $e->getMessage();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('Erro ao registrar saída de estoque: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
                $erro = 'Ocorreu um erro ao registrar a saída. Tente novamente.';
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
    <h1>Registrar Saída de Estoque</h1>
    <p class="descricao-pagina">O saldo é validado no servidor para impedir estoque negativo.</p>

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
        <small data-product-code-feedback aria-live="polite"></small>

        <label for="quantidade">Quantidade *</label>
        <input type="number" name="quantidade" id="quantidade" min="1" required
            value="<?php echo limpar($_POST['quantidade'] ?? ''); ?>">

        <label for="motivo">Motivo *</label>
        <select name="motivo" id="motivo" required>
            <option value="">Selecione...</option>
            <?php foreach ($motivosPermitidos as $opcaoMotivo): ?>
                <option value="<?php echo limpar($opcaoMotivo); ?>"
                    <?php echo ($_POST['motivo'] ?? '') === $opcaoMotivo ? 'selected' : ''; ?>>
                    <?php echo limpar($opcaoMotivo); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="observacao">Observação</label>
        <textarea name="observacao" id="observacao" rows="3" maxlength="255"
            placeholder="Pedido, setor solicitante ou detalhe relevante."><?php echo limpar($_POST['observacao'] ?? ''); ?></textarea>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao-alerta">Registrar Saída</button>
            <a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

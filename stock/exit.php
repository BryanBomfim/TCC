<?php
/**
 * Registra saída de estoque sem permitir saldo negativo.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$produtoSelecionado = (int) ($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);
$quantidadeInformada = trim((string) ($_POST['quantidade'] ?? ''));
$motivoInformado = trim((string) ($_POST['motivo'] ?? ''));
$referenciaInformada = trim((string) ($_POST['referencia'] ?? ''));
$observacaoInformada = trim((string) ($_POST['observacao'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $quantidade = (int) ($_POST['quantidade'] ?? 0);
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $referencia = trim((string) ($_POST['referencia'] ?? ''));
        $observacao = trim((string) ($_POST['observacao'] ?? ''));
        $produtoSelecionado = $produtoId;

        if ($produtoId <= 0) {
            $erro = 'Selecione um produto.';
        } elseif ($quantidade <= 0) {
            $erro = 'Informe uma quantidade válida (maior que zero).';
        } elseif ($motivo === '') {
            $erro = 'Informe o motivo da saída.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmtProduto = $pdo->prepare("
                    SELECT id, nome, quantidade_estoque, estoque_minimo, unidade
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

                $estoqueAntes = (int) $produtoAtual['quantidade_estoque'];
                $estoqueMinimo = (int) $produtoAtual['estoque_minimo'];

                if ($quantidade > $estoqueAntes) {
                    throw new RuntimeException(
                        'Quantidade insuficiente em estoque. Estoque atual: '
                        . $estoqueAntes . ' ' . $produtoAtual['unidade'] . '.'
                    );
                }

                $estoqueDepois = $estoqueAntes - $quantidade;

                $stmtInsert = $pdo->prepare("
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
                        referencia,
                        observacao
                    )
                    VALUES
                    (
                        :produto_id,
                        :usuario_id,
                        'saida',
                        :quantidade,
                        :saldo_anterior,
                        :saldo_posterior,
                        'manual',
                        :motivo,
                        :referencia,
                        :observacao
                    )
                ");
                $stmtInsert->execute([
                    ':produto_id' => $produtoId,
                    ':usuario_id' => (int) $_SESSION['usuario_id'],
                    ':quantidade' => $quantidade,
                    ':saldo_anterior' => $estoqueAntes,
                    ':saldo_posterior' => $estoqueDepois,
                    ':motivo' => mb_substr($motivo, 0, 100),
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
                    'SAIDA',
                    'Estoque',
                    $movimentacaoId,
                    'Saída de ' . $quantidade . ' ' . $produtoAtual['unidade'] . ' do produto "' . $produtoAtual['nome'] . '".',
                    [
                        'produto_id' => $produtoId,
                        'saldo_anterior' => $estoqueAntes,
                    ],
                    [
                        'produto_id' => $produtoId,
                        'quantidade' => $quantidade,
                        'saldo_posterior' => $estoqueDepois,
                        'motivo' => $motivo,
                        'referencia' => $referencia ?: null,
                    ]
                );

                $tipoNotificacao = null;
                $tituloNotificacao = null;
                $mensagemNotificacao = null;

                if ($estoqueDepois === 0 && $estoqueAntes > 0) {
                    $tipoNotificacao = 'critico';
                    $tituloNotificacao = 'Produto sem estoque';
                    $mensagemNotificacao = 'O produto "' . $produtoAtual['nome'] . '" ficou sem estoque.';
                } elseif (
                    $estoqueDepois <= $estoqueMinimo
                    && $estoqueDepois > 0
                    && $estoqueAntes > $estoqueMinimo
                ) {
                    $tipoNotificacao = 'alerta';
                    $tituloNotificacao = 'Estoque baixo';
                    $mensagemNotificacao = 'O produto "' . $produtoAtual['nome'] . '" atingiu o estoque mínimo. Estoque atual: '
                        . $estoqueDepois . ' ' . $produtoAtual['unidade'] . '.';
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
                            $produtoId,
                            BASE_URL . '/products/view.php?id=' . $produtoId
                        );
                    }
                }

                $pdo->commit();
                definirMensagem('sucesso', 'Saída de estoque registrada com sucesso!');
                header('Location: ' . BASE_URL . '/products/view.php?id=' . $produtoId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Erro ao registrar saída de estoque: ' . $e->getMessage());
                $erro = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Ocorreu um erro ao registrar a saída. Tente novamente.';
            }
        }
    }
}

$produtos = $pdo->query("
    SELECT id, nome, quantidade_estoque, unidade
    FROM produtos
    WHERE ativo = 1
    ORDER BY nome ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <h1>Registrar Saída de Estoque</h1>
    <p class="descricao-pagina">O motivo da saída é obrigatório para manter o histórico rastreável.</p>

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
                    (estoque atual: <?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="quantidade">Quantidade *</label>
        <input type="number" name="quantidade" id="quantidade" min="1" required value="<?php echo limpar($quantidadeInformada); ?>">

        <label for="motivo">Motivo *</label>
        <input type="text" name="motivo" id="motivo" required maxlength="100"
            value="<?php echo limpar($motivoInformado); ?>" placeholder="Ex: Venda, consumo interno, avaria">

        <label for="referencia">Referência / Pedido</label>
        <input type="text" name="referencia" id="referencia" maxlength="100"
            value="<?php echo limpar($referenciaInformada); ?>" placeholder="Ex: Pedido 1542">

        <label for="observacao">Observação</label>
        <textarea name="observacao" id="observacao" rows="3" maxlength="500"
            placeholder="Informações adicionais sobre a saída"><?php echo limpar($observacaoInformada); ?></textarea>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao-alerta">Registrar Saída</button>
            <a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

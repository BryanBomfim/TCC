<?php
/**
 * products/view.php
 * Exibe informações detalhadas de um produto, incluindo o
 * histórico de movimentações relacionadas a ele.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT p.*, c.nome AS categoria_nome, f.nome AS fornecedor_nome
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    WHERE p.id = :id
");
$stmt->execute([':id' => $id]);
$produto = $stmt->fetch();

if (!$produto) {
    definirMensagem('erro', 'Produto não encontrado.');
    header('Location: ' . BASE_URL . '/products/list.php');
    exit;
}

$stmtMov = $pdo->prepare("
    SELECT m.*, u.nome AS usuario_nome
    FROM movimentacoes m
    INNER JOIN usuarios u ON u.id = m.usuario_id
    WHERE m.produto_id = :produto_id
    ORDER BY m.data_movimentacao DESC
");
$stmtMov->execute([':produto_id' => $id]);
$movimentacoes = $stmtMov->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo">
    <div class="topo-pagina">
        <h1><?php echo limpar($produto['nome']); ?></h1>
        <div>
            <a href="<?php echo BASE_URL; ?>/products/edit.php?id=<?php echo $produto['id']; ?>" class="botao">Editar</a>
            <a href="<?php echo BASE_URL; ?>/stock/entry.php?produto_id=<?php echo $produto['id']; ?>" class="botao botao-sucesso">+ Entrada</a>
            <a href="<?php echo BASE_URL; ?>/stock/exit.php?produto_id=<?php echo $produto['id']; ?>" class="botao botao-alerta">- Saída</a>
        </div>
    </div>

    <div class="painel">
        <div class="grade-detalhes">
            <div><strong>Categoria:</strong> <?php echo limpar($produto['categoria_nome'] ?? '-'); ?></div>
            <div><strong>Fornecedor:</strong> <?php echo limpar($produto['fornecedor_nome'] ?? '-'); ?></div>
            <div><strong>Preço de Custo:</strong> <?php echo formatarMoeda($produto['preco_custo']); ?></div>
            <div><strong>Preço de Venda:</strong> <?php echo formatarMoeda($produto['preco_venda']); ?></div>
            <div><strong>Estoque Atual:</strong> <?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?></div>
            <div><strong>Estoque Mínimo:</strong> <?php echo (int) $produto['estoque_minimo']; ?></div>
            <div><strong>Status:</strong>
                <span class="etiqueta <?php echo $produto['ativo'] ? 'etiqueta-ativo' : 'etiqueta-inativo'; ?>">
                    <?php echo $produto['ativo'] ? 'Ativo' : 'Inativo'; ?>
                </span>
            </div>
            <div><strong>Cadastrado em:</strong> <?php echo formatarDataHora($produto['data_cadastro']); ?></div>
        </div>
        <?php if (!empty($produto['descricao'])): ?>
            <p class="descricao-produto"><strong>Descrição:</strong> <?php echo nl2br(limpar($produto['descricao'])); ?></p>
        <?php endif; ?>
    </div>

    <div class="painel">
        <h2>Histórico de Movimentações</h2>
        <?php if (empty($movimentacoes)): ?>
            <p class="texto-vazio">Nenhuma movimentação registrada para este produto.</p>
        <?php else: ?>
            <table class="tabela">
                <thead>
                    <tr><th>Tipo</th><th>Quantidade</th><th>Usuário</th><th>Data</th><th>Observação</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($movimentacoes as $mov): ?>
                    <tr>
                        <td>
                            <span class="etiqueta etiqueta-<?php echo $mov['tipo']; ?>">
                                <?php echo $mov['tipo'] === 'entrada' ? 'Entrada' : 'Saída'; ?>
                            </span>
                        </td>
                        <td><?php echo (int) $mov['quantidade']; ?></td>
                        <td><?php echo limpar($mov['usuario_nome']); ?></td>
                        <td><?php echo formatarDataHora($mov['data_movimentacao']); ?></td>
                        <td><?php echo limpar($mov['observacao'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * reports/low_stock.php
 * Relatório de produtos com estoque igual ou abaixo do mínimo definido.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$stmt = $pdo->query("
    SELECT p.*, c.nome AS categoria_nome, f.nome AS fornecedor_nome
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    WHERE p.ativo = 1 AND p.quantidade_estoque <= p.estoque_minimo
    ORDER BY p.quantidade_estoque ASC
");
$produtos = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <div class="topo-pagina">
        <h1>Relatório de Estoque Baixo</h1>
        <button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir</button>
    </div>

    <p class="resumo-relatorio">
        Produtos com estoque baixo: <strong><?php echo count($produtos); ?></strong>
    </p>

    <?php if (empty($produtos)): ?>
        <p class="texto-vazio">Nenhum produto com estoque baixo no momento. Parabéns pela gestão!</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr><th scope="col">Produto</th><th scope="col">Categoria</th><th scope="col">Fornecedor</th><th scope="col">Estoque Atual</th><th scope="col">Estoque Mínimo</th></tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $produto): ?>
                <tr class="linha-alerta">
                    <td><?php echo limpar($produto['nome']); ?></td>
                    <td><?php echo limpar($produto['categoria_nome'] ?? '-'); ?></td>
                    <td><?php echo limpar($produto['fornecedor_nome'] ?? '-'); ?></td>
                    <td><?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?></td>
                    <td><?php echo (int) $produto['estoque_minimo']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$stmt = $pdo->query("
    SELECT p.id, p.sku, p.nome, p.estoque_minimo, p.unidade,
           c.nome AS categoria_nome, f.nome AS fornecedor_nome
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    WHERE p.ativo = 1 AND p.quantidade_estoque = 0
    ORDER BY p.nome ASC
");
$produtos = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <div class="topo-pagina">
        <div><h1>Relatório de Produtos sem Estoque</h1><p class="descricao-pagina">Itens ativos com saldo igual a zero.</p></div>
        <div class="acoes-formulario">
            <a class="botao botao-secundario" href="<?php echo BASE_URL; ?>/reports/export_csv.php?report=out_of_stock">Exportar CSV</a>
            <button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir / PDF</button>
        </div>
    </div>
    <p class="resumo-relatorio">Produtos sem estoque: <strong><?php echo count($produtos); ?></strong></p>
    <?php if (!$produtos): ?><p class="texto-vazio">Nenhum produto ativo está sem estoque.</p><?php else: ?>
        <div class="tabela-responsiva"><table class="tabela"><thead><tr><th>SKU</th><th>Produto</th><th>Categoria</th><th>Fornecedor</th><th>Estoque mínimo</th></tr></thead><tbody>
        <?php foreach ($produtos as $produto): ?><tr>
            <td><?php echo limpar($produto['sku'] ?? '-'); ?></td><td><?php echo limpar($produto['nome']); ?></td><td><?php echo limpar($produto['categoria_nome'] ?? '-'); ?></td><td><?php echo limpar($produto['fornecedor_nome'] ?? '-'); ?></td><td><?php echo (int) $produto['estoque_minimo']; ?> <?php echo limpar($produto['unidade']); ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

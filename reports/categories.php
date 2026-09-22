<?php
/**
 * reports/categories.php
 * Relatório de categorias com a quantidade de produtos vinculados a cada uma.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$stmt = $pdo->query("
    SELECT c.*, COUNT(p.id) AS total_produtos
    FROM categorias c
    LEFT JOIN produtos p ON p.categoria_id = c.id
    GROUP BY c.id
    ORDER BY c.nome ASC
");
$categorias = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <div class="topo-pagina">
        <h1>Relatório de Categorias</h1>
        <button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir</button>
    </div>

    <?php if (empty($categorias)): ?>
        <p class="texto-vazio">Nenhuma categoria cadastrada.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr><th scope="col">Categoria</th><th scope="col">Descrição</th><th scope="col">Status</th><th scope="col">Produtos Vinculados</th></tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <td><?php echo limpar($categoria['nome']); ?></td>
                    <td><?php echo limpar($categoria['descricao'] ?? '-'); ?></td>
                    <td><?php echo $categoria['ativo'] ? 'Ativa' : 'Inativa'; ?></td>
                    <td><?php echo (int) $categoria['total_produtos']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

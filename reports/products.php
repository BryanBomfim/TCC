<?php
/**
 * reports/products.php
 * Relatório de produtos com filtros por categoria, fornecedor e status.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$filtroCategoria = (int) ($_GET['categoria'] ?? 0);
$filtroFornecedor = (int) ($_GET['fornecedor'] ?? 0);
$filtroStatus = $_GET['status'] ?? '';

$sql = "
    SELECT p.*, c.nome AS categoria_nome, f.nome AS fornecedor_nome
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    WHERE 1 = 1
";
$parametros = [];

if ($filtroCategoria > 0) {
    $sql .= " AND p.categoria_id = :categoria";
    $parametros[':categoria'] = $filtroCategoria;
}
if ($filtroFornecedor > 0) {
    $sql .= " AND p.fornecedor_id = :fornecedor";
    $parametros[':fornecedor'] = $filtroFornecedor;
}
if ($filtroStatus === 'ativo') {
    $sql .= " AND p.ativo = 1";
} elseif ($filtroStatus === 'inativo') {
    $sql .= " AND p.ativo = 0";
}
$sql .= " ORDER BY p.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$produtos = $stmt->fetchAll();

$valorTotalEstoque = 0;
foreach ($produtos as $p) {
    $valorTotalEstoque += $p['preco_venda'] * $p['quantidade_estoque'];
}

$categorias = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome ASC")->fetchAll();
$fornecedores = $pdo->query("SELECT id, nome FROM fornecedores ORDER BY nome ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Relatório de Produtos</h1>

    <form method="GET" action="" class="form-filtros">
        <select name="categoria" aria-label="Filtrar por categoria">
            <option value="0">Todas as categorias</option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?php echo $categoria['id']; ?>" <?php echo $filtroCategoria == $categoria['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($categoria['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="fornecedor" aria-label="Filtrar por fornecedor">
            <option value="0">Todos os fornecedores</option>
            <?php foreach ($fornecedores as $fornecedor): ?>
                <option value="<?php echo $fornecedor['id']; ?>" <?php echo $filtroFornecedor == $fornecedor['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($fornecedor['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status" aria-label="Filtrar por status">
            <option value="">Todos os status</option>
            <option value="ativo" <?php echo $filtroStatus === 'ativo' ? 'selected' : ''; ?>>Ativos</option>
            <option value="inativo" <?php echo $filtroStatus === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
        </select>
        <button type="submit" class="botao">Filtrar</button>
        <a href="<?php echo BASE_URL; ?>/reports/products.php" class="botao botao-secundario">Limpar</a>
        <button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir</button>
    </form>

    <p class="resumo-relatorio">
        Total de produtos: <strong><?php echo count($produtos); ?></strong> |
        Valor total em estoque (preço de venda): <strong><?php echo formatarMoeda($valorTotalEstoque); ?></strong>
    </p>

    <?php if (empty($produtos)): ?>
        <p class="texto-vazio">Nenhum produto encontrado para os filtros selecionados.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Produto</th><th scope="col">Categoria</th><th scope="col">Fornecedor</th><th scope="col">Estoque</th>
                    <th scope="col">Preço Custo</th><th scope="col">Preço Venda</th><th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $produto): ?>
                <tr>
                    <td><?php echo limpar($produto['nome']); ?></td>
                    <td><?php echo limpar($produto['categoria_nome'] ?? '-'); ?></td>
                    <td><?php echo limpar($produto['fornecedor_nome'] ?? '-'); ?></td>
                    <td><?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?></td>
                    <td><?php echo formatarMoeda($produto['preco_custo']); ?></td>
                    <td><?php echo formatarMoeda($produto['preco_venda']); ?></td>
                    <td><?php echo $produto['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

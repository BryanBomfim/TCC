<?php
/**
 * products/list.php
 * Lista, pesquisa e filtra produtos por categoria/fornecedor/status.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$busca = trim($_GET['busca'] ?? '');
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

if ($busca !== '') {
    $sql .= " AND p.nome LIKE :busca";
    $parametros[':busca'] = '%' . $busca . '%';
}
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
if (isset($_GET['baixo'])) {
    $sql .= " AND p.quantidade_estoque <= p.estoque_minimo";
}

$sql .= " ORDER BY p.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$produtos = $stmt->fetchAll();

$categorias = $pdo->query("SELECT id, nome FROM categorias WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();
$fornecedores = $pdo->query("SELECT id, nome FROM fornecedores WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo">
    <div class="topo-pagina">
        <h1>Produtos</h1>
        <a href="<?php echo BASE_URL; ?>/products/create.php" class="botao">+ Novo Produto</a>
    </div>

    <form method="GET" action="" class="form-filtros">
        <input type="text" name="busca" placeholder="Buscar produto pelo nome..." value="<?php echo limpar($busca); ?>">

        <select name="categoria">
            <option value="0">Todas as categorias</option>
            <?php foreach ($categorias as $categoria): ?>
                <option value="<?php echo $categoria['id']; ?>" <?php echo $filtroCategoria == $categoria['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($categoria['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="fornecedor">
            <option value="0">Todos os fornecedores</option>
            <?php foreach ($fornecedores as $fornecedor): ?>
                <option value="<?php echo $fornecedor['id']; ?>" <?php echo $filtroFornecedor == $fornecedor['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($fornecedor['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <option value="">Todos os status</option>
            <option value="ativo" <?php echo $filtroStatus === 'ativo' ? 'selected' : ''; ?>>Ativos</option>
            <option value="inativo" <?php echo $filtroStatus === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
        </select>

        <button type="submit" class="botao">Filtrar</button>
        <a href="<?php echo BASE_URL; ?>/products/list.php" class="botao botao-secundario">Limpar</a>
    </form>

    <?php if (empty($produtos)): ?>
        <p class="texto-vazio">Nenhum produto encontrado.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Fornecedor</th>
                    <th>Estoque</th>
                    <th>Preço Venda</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $produto): ?>
                <tr class="<?php echo $produto['quantidade_estoque'] <= $produto['estoque_minimo'] ? 'linha-alerta' : ''; ?>">
                    <td><?php echo limpar($produto['nome']); ?></td>
                    <td><?php echo limpar($produto['categoria_nome'] ?? '-'); ?></td>
                    <td><?php echo limpar($produto['fornecedor_nome'] ?? '-'); ?></td>
                    <td><?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?></td>
                    <td><?php echo formatarMoeda($produto['preco_venda']); ?></td>
                    <td>
                        <span class="etiqueta <?php echo $produto['ativo'] ? 'etiqueta-ativo' : 'etiqueta-inativo'; ?>">
                            <?php echo $produto['ativo'] ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </td>
                    <td class="acoes">
                        <a href="<?php echo BASE_URL; ?>/products/view.php?id=<?php echo $produto['id']; ?>">Ver</a>
                        <a href="<?php echo BASE_URL; ?>/products/edit.php?id=<?php echo $produto['id']; ?>">Editar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

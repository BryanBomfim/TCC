<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiErro('Método não permitido.', 405);
}

[$pagina, $limite, $offset] = apiPaginacao();
$busca = trim((string) ($_GET['search'] ?? ''));
$categoria = max(0, (int) ($_GET['category_id'] ?? 0));
$fornecedor = max(0, (int) ($_GET['supplier_id'] ?? 0));

$where = ['1 = 1'];
$params = [];

if ($busca !== '') {
    $where[] = '(p.nome LIKE :busca OR p.sku LIKE :busca OR p.codigo_barras LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
if ($categoria > 0) {
    $where[] = 'p.categoria_id = :categoria';
    $params[':categoria'] = $categoria;
}
if ($fornecedor > 0) {
    $where[] = 'p.fornecedor_id = :fornecedor';
    $params[':fornecedor'] = $fornecedor;
}
$whereSql = implode(' AND ', $where);

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM produtos p WHERE {$whereSql}");
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        p.id, p.sku, p.codigo_barras, p.nome, p.descricao,
        p.preco_custo, p.preco_venda, p.quantidade_estoque,
        p.estoque_minimo, p.unidade, p.ativo, p.data_cadastro,
        c.id AS categoria_id, c.nome AS categoria,
        f.id AS fornecedor_id, f.nome AS fornecedor
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id
    LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
    WHERE {$whereSql}
    ORDER BY p.nome ASC
    LIMIT {$limite} OFFSET {$offset}
");
$stmt->execute($params);

apiResposta($stmt->fetchAll(), 'Produtos encontrados.', 200, [
    'page' => $pagina,
    'limit' => $limite,
    'total' => $total,
    'pages' => max(1, (int) ceil($total / $limite)),
]);

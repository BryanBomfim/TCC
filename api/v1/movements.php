<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiErro('Método não permitido.', 405);
}

[$pagina, $limite, $offset] = apiPaginacao();
$tipo = trim((string) ($_GET['type'] ?? ''));
$produto = max(0, (int) ($_GET['product_id'] ?? 0));
$origem = trim((string) ($_GET['origin'] ?? ''));

$where = ['1 = 1'];
$params = [];

if ($tipo !== '') {
    if (!in_array($tipo, ['entrada', 'saida'], true)) {
        apiErro('Tipo de movimentação inválido.', 422);
    }
    $where[] = 'm.tipo = :tipo';
    $params[':tipo'] = $tipo;
}
if ($produto > 0) {
    $where[] = 'm.produto_id = :produto';
    $params[':produto'] = $produto;
}
if ($origem !== '') {
    $origensPermitidas = ['manual', 'ajuste', 'inventario', 'cadastro'];
    if (!in_array($origem, $origensPermitidas, true)) {
        apiErro('Origem de movimentação inválida.', 422);
    }
    $where[] = 'm.origem = :origem';
    $params[':origem'] = $origem;
}
$whereSql = implode(' AND ', $where);

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM movimentacoes m WHERE {$whereSql}");
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        m.id, m.tipo, m.quantidade, m.saldo_anterior, m.saldo_posterior,
        m.origem, m.motivo, m.valor_unitario, m.referencia, m.observacao,
        m.data_movimentacao,
        p.id AS produto_id, p.sku, p.nome AS produto,
        u.id AS usuario_id, u.nome AS usuario,
        f.id AS fornecedor_id, f.nome AS fornecedor
    FROM movimentacoes m
    INNER JOIN produtos p ON p.id = m.produto_id
    INNER JOIN usuarios u ON u.id = m.usuario_id
    LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
    WHERE {$whereSql}
    ORDER BY m.data_movimentacao DESC, m.id DESC
    LIMIT {$limite} OFFSET {$offset}
");
$stmt->execute($params);

apiResposta($stmt->fetchAll(), 'Movimentações encontradas.', 200, [
    'page' => $pagina,
    'limit' => $limite,
    'total' => $total,
    'pages' => max(1, (int) ceil($total / $limite)),
]);

<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiErro('Método não permitido.', 405);
}

[$pagina, $limite, $offset] = apiPaginacao(20, 100);
$status = trim((string) ($_GET['status'] ?? ''));
$where = '';
$params = [];

if ($status !== '') {
    if (!in_array($status, ['aberto', 'finalizado', 'cancelado'], true)) {
        apiErro('Status de inventário inválido.', 422);
    }
    $where = 'WHERE i.status = :status';
    $params[':status'] = $status;
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM inventarios i {$where}");
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        i.id, i.titulo, i.status, i.observacao,
        i.criado_por, i.criado_por_nome, i.finalizado_por, i.finalizado_por_nome,
        i.criado_em, i.finalizado_em,
        COUNT(ii.id) AS total_itens,
        COALESCE(SUM(CASE WHEN ii.quantidade_contada IS NOT NULL THEN 1 ELSE 0 END), 0) AS itens_contados,
        COALESCE(SUM(CASE WHEN ii.diferenca IS NOT NULL AND ii.diferenca <> 0 THEN 1 ELSE 0 END), 0) AS divergencias
    FROM inventarios i
    LEFT JOIN inventario_itens ii ON ii.inventario_id = i.id
    {$where}
    GROUP BY i.id
    ORDER BY i.criado_em DESC, i.id DESC
    LIMIT {$limite} OFFSET {$offset}
");
$stmt->execute($params);

apiResposta($stmt->fetchAll(), 'Inventários encontrados.', 200, [
    'page' => $pagina,
    'limit' => $limite,
    'total' => $total,
    'pages' => max(1, (int) ceil($total / $limite)),
]);

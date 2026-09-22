<?php
/** Exportação CSV dos relatórios gerenciais do SmartControl. */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$report = trim((string) ($_GET['report'] ?? ''));
$rows = [];
$headers = [];
$filename = 'smartcontrol-relatorio.csv';

/** Evita interpretação de fórmulas ao abrir o CSV em planilhas. */
function csvSeguro(mixed $valor): mixed
{
    if (!is_string($valor)) {
        return $valor;
    }

    $valor = trim($valor);
    if ($valor !== '' && preg_match('/^[=+\-@]/u', $valor)) {
        return "'" . $valor;
    }
    return $valor;
}

if ($report === 'products') {
    $categoria = max(0, (int) ($_GET['categoria'] ?? 0));
    $fornecedor = max(0, (int) ($_GET['fornecedor'] ?? 0));
    $status = trim((string) ($_GET['status'] ?? ''));

    $where = ['1 = 1'];
    $params = [];
    if ($categoria > 0) {
        $where[] = 'p.categoria_id = :categoria';
        $params[':categoria'] = $categoria;
    }
    if ($fornecedor > 0) {
        $where[] = 'p.fornecedor_id = :fornecedor';
        $params[':fornecedor'] = $fornecedor;
    }
    if ($status === 'ativo') {
        $where[] = 'p.ativo = 1';
    } elseif ($status === 'inativo') {
        $where[] = 'p.ativo = 0';
    }

    $headers = ['SKU', 'Código de barras', 'Produto', 'Categoria', 'Fornecedor', 'Estoque', 'Unidade', 'Preço custo', 'Preço venda', 'Status'];
    $stmt = $pdo->prepare("\n        SELECT p.sku, p.codigo_barras, p.nome, c.nome categoria, f.nome fornecedor,\n               p.quantidade_estoque, p.unidade, p.preco_custo, p.preco_venda, p.ativo\n        FROM produtos p\n        LEFT JOIN categorias c ON c.id = p.categoria_id\n        LEFT JOIN fornecedores f ON f.id = p.fornecedor_id\n        WHERE " . implode(' AND ', $where) . "\n        ORDER BY p.nome\n    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [$r['sku'], $r['codigo_barras'], $r['nome'], $r['categoria'], $r['fornecedor'], $r['quantidade_estoque'], $r['unidade'], $r['preco_custo'], $r['preco_venda'], $r['ativo'] ? 'Ativo' : 'Inativo'];
    }
    $filename = 'smartcontrol-produtos-' . date('Y-m-d') . '.csv';
} elseif ($report === 'movements') {
    $tipo = trim((string) ($_GET['tipo'] ?? ''));
    $produto = max(0, (int) ($_GET['produto_id'] ?? 0));
    $categoria = max(0, (int) ($_GET['categoria_id'] ?? 0));
    $fornecedor = max(0, (int) ($_GET['fornecedor_id'] ?? 0));
    $usuario = max(0, (int) ($_GET['usuario_id'] ?? 0));
    $dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
    $dataFim = trim((string) ($_GET['data_fim'] ?? ''));

    $where = ['1 = 1'];
    $params = [];
    if (in_array($tipo, ['entrada', 'saida'], true)) {
        $where[] = 'm.tipo = :tipo';
        $params[':tipo'] = $tipo;
    }
    if ($produto > 0) {
        $where[] = 'm.produto_id = :produto';
        $params[':produto'] = $produto;
    }
    if ($categoria > 0) {
        $where[] = 'p.categoria_id = :categoria';
        $params[':categoria'] = $categoria;
    }
    if ($fornecedor > 0) {
        $where[] = 'm.fornecedor_id = :fornecedor';
        $params[':fornecedor'] = $fornecedor;
    }
    if ($usuario > 0 && ehAdministrador()) {
        $where[] = 'm.usuario_id = :usuario';
        $params[':usuario'] = $usuario;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
        $where[] = 'm.data_movimentacao >= :inicio';
        $params[':inicio'] = $dataInicio . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
        $where[] = 'm.data_movimentacao <= :fim';
        $params[':fim'] = $dataFim . ' 23:59:59';
    }

    $headers = ['Data', 'Produto', 'SKU', 'Tipo', 'Quantidade', 'Saldo anterior', 'Saldo posterior', 'Origem', 'Motivo', 'Referência', 'Usuário', 'Fornecedor', 'Valor unitário', 'Observação'];
    $stmt = $pdo->prepare("\n        SELECT m.*, p.nome produto, p.sku, u.nome usuario, f.nome fornecedor\n        FROM movimentacoes m\n        INNER JOIN produtos p ON p.id = m.produto_id\n        INNER JOIN usuarios u ON u.id = m.usuario_id\n        LEFT JOIN fornecedores f ON f.id = m.fornecedor_id\n        WHERE " . implode(' AND ', $where) . "\n        ORDER BY m.data_movimentacao DESC, m.id DESC\n    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [$r['data_movimentacao'], $r['produto'], $r['sku'], $r['tipo'], $r['quantidade'], $r['saldo_anterior'], $r['saldo_posterior'], $r['origem'], $r['motivo'], $r['referencia'], $r['usuario'], $r['fornecedor'], $r['valor_unitario'], $r['observacao']];
    }
    $filename = 'smartcontrol-movimentacoes-' . date('Y-m-d') . '.csv';
} elseif ($report === 'low_stock' || $report === 'out_of_stock') {
    $headers = ['SKU', 'Produto', 'Categoria', 'Fornecedor', 'Estoque atual', 'Estoque mínimo', 'Unidade'];
    $condicao = $report === 'out_of_stock'
        ? 'p.quantidade_estoque = 0'
        : 'p.quantidade_estoque > 0 AND p.quantidade_estoque <= p.estoque_minimo';
    $stmt = $pdo->query("\n        SELECT p.sku, p.nome, c.nome categoria, f.nome fornecedor, p.quantidade_estoque, p.estoque_minimo, p.unidade\n        FROM produtos p\n        LEFT JOIN categorias c ON c.id = p.categoria_id\n        LEFT JOIN fornecedores f ON f.id = p.fornecedor_id\n        WHERE p.ativo = 1 AND {$condicao}\n        ORDER BY p.nome\n    ");
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = array_values($r);
    }
    $filename = 'smartcontrol-' . ($report === 'out_of_stock' ? 'sem-estoque' : 'estoque-baixo') . '-' . date('Y-m-d') . '.csv';
} elseif ($report === 'inventory') {
    $status = trim((string) ($_GET['status'] ?? ''));
    $where = '';
    $params = [];
    if (in_array($status, ['aberto', 'finalizado', 'cancelado'], true)) {
        $where = 'WHERE i.status = :status';
        $params[':status'] = $status;
    }

    $headers = ['ID', 'Inventário', 'Status', 'Itens', 'Contados', 'Divergências', 'Ajustes +', 'Ajustes -', 'Criado por', 'Criado em', 'Finalizado por', 'Finalizado em'];
    $stmt = $pdo->prepare("\n        SELECT i.id, i.titulo, i.status, i.criado_por_nome, i.criado_em, i.finalizado_por_nome, i.finalizado_em,\n               COUNT(ii.id) total_itens,\n               SUM(CASE WHEN ii.quantidade_contada IS NOT NULL THEN 1 ELSE 0 END) itens_contados,\n               SUM(CASE WHEN ii.diferenca IS NOT NULL AND ii.diferenca <> 0 THEN 1 ELSE 0 END) divergencias,\n               COALESCE(SUM(CASE WHEN ii.diferenca > 0 THEN ii.diferenca ELSE 0 END), 0) unidades_entrada,\n               COALESCE(SUM(CASE WHEN ii.diferenca < 0 THEN ABS(ii.diferenca) ELSE 0 END), 0) unidades_saida\n        FROM inventarios i\n        LEFT JOIN inventario_itens ii ON ii.inventario_id = i.id\n        {$where}\n        GROUP BY i.id\n        ORDER BY i.criado_em DESC, i.id DESC\n    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [$r['id'], $r['titulo'], $r['status'], $r['total_itens'], $r['itens_contados'], $r['divergencias'], $r['unidades_entrada'], $r['unidades_saida'], $r['criado_por_nome'], $r['criado_em'], $r['finalizado_por_nome'], $r['finalizado_em']];
    }
    $filename = 'smartcontrol-inventarios-' . date('Y-m-d') . '.csv';
} else {
    http_response_code(404);
    exit('Relatório não disponível para exportação.');
}

registrarAuditoria('EXPORTAR_CSV', 'Relatórios', null, 'Relatório exportado em CSV: ' . $report . '.');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'wb');
fputcsv($out, $headers, ';');
foreach ($rows as $row) {
    fputcsv($out, array_map('csvSeguro', $row), ';');
}
fclose($out);
exit;

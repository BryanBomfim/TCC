<?php
/**
 * Exportação CSV dos relatórios. O ponto de entrada usa uma lista fechada de
 * relatórios e prepared statements para que nenhum trecho de SQL venha da URL.
 */

require_once __DIR__ . '/../config/config.php';
exigirLogin();

$relatorio = $_GET['relatorio'] ?? '';
$permitidos = ['products', 'movements', 'low_stock', 'out_of_stock', 'suppliers', 'inventories', 'divergences'];

if (!in_array($relatorio, $permitidos, true)) {
    http_response_code(400);
    exit('Relatório inválido.');
}

/** Evita que planilhas interpretem conteúdo textual como fórmula. */
function valorCsv(mixed $valor): string
{
    $texto = (string) ($valor ?? '');
    if ($texto !== '' && preg_match('/^[\x00-\x20]*[=+@-]|^[\t\r\n]/', $texto)) {
        return "'" . $texto;
    }
    return $texto;
}

/** @param list<string> $cabecalho @param iterable<array<int, mixed>> $linhas */
function enviarCsv(string $nome, array $cabecalho, iterable $linhas): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nome . '-' . date('Y-m-d-His') . '.csv"');
    header('Cache-Control: no-store, max-age=0');

    $saida = fopen('php://output', 'wb');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, $cabecalho, ';', '"', '\\');

    foreach ($linhas as $linha) {
        fputcsv($saida, array_map('valorCsv', $linha), ';', '"', '\\');
    }

    fclose($saida);
    exit;
}

if ($relatorio === 'products') {
    $categoria = (int) ($_GET['categoria'] ?? 0);
    $fornecedor = (int) ($_GET['fornecedor'] ?? 0);
    $status = $_GET['status'] ?? '';
    $sql = "
        SELECT p.sku, p.codigo_barras, p.nome, c.nome AS categoria,
               f.nome AS fornecedor, p.quantidade_estoque, p.estoque_minimo,
               p.unidade, p.preco_custo, p.preco_venda, p.ativo
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
        WHERE 1 = 1
    ";
    $params = [];
    if ($categoria > 0) {
        $sql .= ' AND p.categoria_id = :categoria';
        $params[':categoria'] = $categoria;
    }
    if ($fornecedor > 0) {
        $sql .= ' AND p.fornecedor_id = :fornecedor';
        $params[':fornecedor'] = $fornecedor;
    }
    if ($status === 'ativo' || $status === 'inativo') {
        $sql .= ' AND p.ativo = :ativo';
        $params[':ativo'] = $status === 'ativo' ? 1 : 0;
    }
    $sql .= ' ORDER BY p.nome';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $linhas = [];
    foreach ($stmt as $row) {
        $linhas[] = [
            $row['sku'], $row['codigo_barras'], $row['nome'], $row['categoria'], $row['fornecedor'],
            $row['quantidade_estoque'], $row['estoque_minimo'], $row['unidade'],
            $row['preco_custo'], $row['preco_venda'], $row['ativo'] ? 'Ativo' : 'Inativo',
        ];
    }
    enviarCsv('produtos', ['SKU', 'Código', 'Produto', 'Categoria', 'Fornecedor', 'Estoque', 'Mínimo', 'Unidade', 'Custo', 'Venda', 'Status'], $linhas);
}

if ($relatorio === 'movements') {
    $sql = "
        SELECT m.data_movimentacao, p.nome AS produto, c.nome AS categoria,
               m.tipo, m.quantidade, m.saldo_anterior, m.saldo_posterior,
               m.origem, m.motivo, f.nome AS fornecedor, u.nome AS usuario, m.observacao
        FROM movimentacoes m
        INNER JOIN produtos p ON p.id = m.produto_id
        LEFT JOIN categorias c ON c.id = p.categoria_id
        LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
        INNER JOIN usuarios u ON u.id = m.usuario_id
        WHERE 1 = 1
    ";
    $params = [];
    $filtrosInteiros = [
        'produto_id' => 'm.produto_id',
        'categoria_id' => 'p.categoria_id',
        'fornecedor_id' => 'm.fornecedor_id',
        'usuario_id' => 'm.usuario_id',
    ];
    foreach ($filtrosInteiros as $campo => $coluna) {
        $valor = (int) ($_GET[$campo] ?? 0);
        if ($valor > 0) {
            $sql .= " AND {$coluna} = :{$campo}";
            $params[":{$campo}"] = $valor;
        }
    }
    $tipo = $_GET['tipo'] ?? '';
    if (in_array($tipo, ['entrada', 'saida'], true)) {
        $sql .= ' AND m.tipo = :tipo';
        $params[':tipo'] = $tipo;
    }
    $origem = $_GET['origem'] ?? '';
    if (in_array($origem, ['cadastro', 'entrada', 'saida', 'inventario', 'ajuste', 'migracao', 'manual'], true)) {
        $sql .= ' AND m.origem = :origem';
        $params[':origem'] = $origem;
    }
    foreach (['data_inicio' => '>=', 'data_fim' => '<='] as $campo => $operador) {
        $data = $_GET[$campo] ?? '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $sql .= " AND DATE(m.data_movimentacao) {$operador} :{$campo}";
            $params[":{$campo}"] = $data;
        }
    }
    $sql .= ' ORDER BY m.data_movimentacao DESC, m.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $linhas = [];
    foreach ($stmt as $row) {
        $linhas[] = [
            $row['data_movimentacao'], $row['produto'], $row['categoria'], $row['tipo'], $row['quantidade'],
            $row['saldo_anterior'], $row['saldo_posterior'], formatarOrigemMovimentacao($row['origem']),
            $row['motivo'], $row['fornecedor'], $row['usuario'], $row['observacao'],
        ];
    }
    enviarCsv('movimentacoes', ['Data', 'Produto', 'Categoria', 'Tipo', 'Quantidade', 'Saldo anterior', 'Saldo posterior', 'Origem', 'Motivo', 'Fornecedor', 'Responsável', 'Observação'], $linhas);
}

if ($relatorio === 'low_stock' || $relatorio === 'out_of_stock') {
    $condicao = $relatorio === 'out_of_stock'
        ? 'p.quantidade_estoque = 0'
        : 'p.quantidade_estoque <= p.estoque_minimo';
    $stmt = $pdo->query("
        SELECT p.sku, p.nome, c.nome AS categoria, f.nome AS fornecedor,
               p.quantidade_estoque, p.estoque_minimo, p.unidade
        FROM produtos p
        LEFT JOIN categorias c ON c.id = p.categoria_id
        LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
        WHERE p.ativo = 1 AND {$condicao}
        ORDER BY p.quantidade_estoque, p.nome
    ");
    $linhas = [];
    foreach ($stmt as $row) {
        $linhas[] = [$row['sku'], $row['nome'], $row['categoria'], $row['fornecedor'], $row['quantidade_estoque'], $row['estoque_minimo'], $row['unidade']];
    }
    enviarCsv($relatorio, ['SKU', 'Produto', 'Categoria', 'Fornecedor', 'Estoque', 'Mínimo', 'Unidade'], $linhas);
}

if ($relatorio === 'suppliers') {
    $stmt = $pdo->query("
        SELECT f.nome, f.cnpj_cpf, f.telefone, f.email, f.endereco, f.ativo,
               COUNT(p.id) AS produtos
        FROM fornecedores f
        LEFT JOIN produtos p ON p.fornecedor_id = f.id
        GROUP BY f.id
        ORDER BY f.nome
    ");
    $linhas = [];
    foreach ($stmt as $row) {
        $linhas[] = [$row['nome'], $row['cnpj_cpf'], $row['telefone'], $row['email'], $row['endereco'], $row['produtos'], $row['ativo'] ? 'Ativo' : 'Inativo'];
    }
    enviarCsv('fornecedores', ['Fornecedor', 'CNPJ/CPF', 'Telefone', 'E-mail', 'Endereço', 'Produtos', 'Status'], $linhas);
}

if ($relatorio === 'inventories') {
    $status = $_GET['status'] ?? '';
    $sql = "
        SELECT i.id, i.titulo, i.status, i.criado_por_nome, i.criado_em,
               i.finalizado_por_nome, i.finalizado_em,
               COUNT(ii.id) AS itens,
               COALESCE(SUM(ii.quantidade_contada IS NOT NULL), 0) AS contados,
               COALESCE(SUM(ii.diferenca <> 0), 0) AS divergencias
        FROM inventarios i
        LEFT JOIN inventario_itens ii ON ii.inventario_id = i.id
        WHERE 1 = 1
    ";
    $params = [];
    if (in_array($status, ['aberto', 'finalizado', 'cancelado'], true)) {
        $sql .= ' AND i.status = :status';
        $params[':status'] = $status;
    }
    $sql .= ' GROUP BY i.id ORDER BY i.criado_em DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $linhas = [];
    foreach ($stmt as $row) {
        $linhas[] = [$row['id'], $row['titulo'], $row['status'], $row['itens'], $row['contados'], $row['divergencias'], $row['criado_por_nome'], $row['criado_em'], $row['finalizado_por_nome'], $row['finalizado_em']];
    }
    enviarCsv('inventarios', ['ID', 'Título', 'Status', 'Itens', 'Contados', 'Divergências', 'Criado por', 'Iniciado em', 'Finalizado por', 'Finalizado em'], $linhas);
}

$inventarioId = (int) ($_GET['inventario_id'] ?? 0);
$sql = "
    SELECT i.id AS inventario_id, i.titulo, i.status, p.sku, p.nome AS produto,
           ii.estoque_sistema, ii.quantidade_contada, ii.diferenca,
           ii.contado_por_nome, ii.contado_em
    FROM inventario_itens ii
    INNER JOIN inventarios i ON i.id = ii.inventario_id
    INNER JOIN produtos p ON p.id = ii.produto_id
    WHERE ii.diferenca IS NOT NULL AND ii.diferenca <> 0
";
$params = [];
if ($inventarioId > 0) {
    $sql .= ' AND i.id = :inventario_id';
    $params[':inventario_id'] = $inventarioId;
}
$sql .= ' ORDER BY i.criado_em DESC, p.nome';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$linhas = [];
foreach ($stmt as $row) {
    $linhas[] = [$row['inventario_id'], $row['titulo'], $row['status'], $row['sku'], $row['produto'], $row['estoque_sistema'], $row['quantidade_contada'], $row['diferenca'], $row['contado_por_nome'], $row['contado_em']];
}
enviarCsv('divergencias-inventario', ['Inventário', 'Título', 'Status', 'SKU', 'Produto', 'Saldo do sistema', 'Contagem física', 'Diferença', 'Contado por', 'Contado em'], $linhas);

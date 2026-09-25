<?php
/** Relatório paginado de movimentações com filtros gerenciais. */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$filtros = [
    'tipo' => $_GET['tipo'] ?? '',
    'origem' => $_GET['origem'] ?? '',
    'produto_id' => (int) ($_GET['produto_id'] ?? 0),
    'categoria_id' => (int) ($_GET['categoria_id'] ?? 0),
    'fornecedor_id' => (int) ($_GET['fornecedor_id'] ?? 0),
    'usuario_id' => (int) ($_GET['usuario_id'] ?? 0),
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
];

$where = ['1 = 1'];
$params = [];
if (in_array($filtros['tipo'], ['entrada', 'saida'], true)) {
    $where[] = 'm.tipo = :tipo';
    $params[':tipo'] = $filtros['tipo'];
}
$origensPermitidas = ['cadastro', 'entrada', 'saida', 'inventario', 'ajuste', 'migracao', 'manual'];
if (in_array($filtros['origem'], $origensPermitidas, true)) {
    $where[] = 'm.origem = :origem';
    $params[':origem'] = $filtros['origem'];
}
foreach ([
    'produto_id' => 'm.produto_id',
    'categoria_id' => 'p.categoria_id',
    'fornecedor_id' => 'm.fornecedor_id',
    'usuario_id' => 'm.usuario_id',
] as $campo => $coluna) {
    if ($filtros[$campo] > 0) {
        $where[] = $coluna . ' = :' . $campo;
        $params[':' . $campo] = $filtros[$campo];
    }
}
foreach (['data_inicio' => '>=', 'data_fim' => '<='] as $campo => $operador) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros[$campo])) {
        $where[] = 'DATE(m.data_movimentacao) ' . $operador . ' :' . $campo;
        $params[':' . $campo] = $filtros[$campo];
    } else {
        $filtros[$campo] = '';
    }
}
$clausulaWhere = implode(' AND ', $where);

$stmtResumo = $pdo->prepare("
    SELECT COUNT(*) AS registros,
           COALESCE(SUM(CASE WHEN m.tipo = 'entrada' THEN m.quantidade ELSE 0 END), 0) AS entradas,
           COALESCE(SUM(CASE WHEN m.tipo = 'saida' THEN m.quantidade ELSE 0 END), 0) AS saidas
    FROM movimentacoes m
    INNER JOIN produtos p ON p.id = m.produto_id
    WHERE {$clausulaWhere}
");
$stmtResumo->execute($params);
$resumo = $stmtResumo->fetch();

$porPagina = 50;
$totalRegistros = (int) $resumo['registros'];
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$pagina = min($totalPaginas, max(1, (int) ($_GET['pagina'] ?? 1)));
$offset = ($pagina - 1) * $porPagina;

$stmt = $pdo->prepare("
    SELECT m.*, p.nome AS produto_nome, c.nome AS categoria_nome,
           u.nome AS usuario_nome, f.nome AS fornecedor_nome
    FROM movimentacoes m
    INNER JOIN produtos p ON p.id = m.produto_id
    LEFT JOIN categorias c ON c.id = p.categoria_id
    INNER JOIN usuarios u ON u.id = m.usuario_id
    LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
    WHERE {$clausulaWhere}
    ORDER BY m.data_movimentacao DESC, m.id DESC
    LIMIT {$porPagina} OFFSET {$offset}
");
$stmt->execute($params);
$movimentacoes = $stmt->fetchAll();

$produtos = $pdo->query('SELECT id, nome FROM produtos ORDER BY nome')->fetchAll();
$categorias = $pdo->query('SELECT id, nome FROM categorias ORDER BY nome')->fetchAll();
$fornecedores = $pdo->query('SELECT id, nome FROM fornecedores ORDER BY nome')->fetchAll();
$usuarios = $pdo->query('SELECT id, nome FROM usuarios ORDER BY nome')->fetchAll();
$queryCsv = http_build_query(['relatorio' => 'movements'] + $filtros);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <div class="topo-pagina">
        <div><h1>Relatório de Movimentações</h1><p class="descricao-pagina">Rastreabilidade completa de entradas, saídas, ajustes e inventários.</p></div>
        <div class="acoes-relatorio">
            <a class="botao" href="<?php echo BASE_URL; ?>/reports/export_csv.php?<?php echo limpar($queryCsv); ?>">Exportar CSV</a>
        <button type="button" class="botao botao-secundario" data-print>Imprimir / PDF</button>
        </div>
    </div>

    <form method="GET" class="form-filtros form-filtros-grade">
        <label>Tipo
            <select name="tipo"><option value="">Todos</option><option value="entrada" <?php echo $filtros['tipo'] === 'entrada' ? 'selected' : ''; ?>>Entrada</option><option value="saida" <?php echo $filtros['tipo'] === 'saida' ? 'selected' : ''; ?>>Saída</option></select>
        </label>
        <label>Origem
            <select name="origem"><option value="">Todas</option><?php foreach ($origensPermitidas as $origem): ?><option value="<?php echo $origem; ?>" <?php echo $filtros['origem'] === $origem ? 'selected' : ''; ?>><?php echo limpar(formatarOrigemMovimentacao($origem)); ?></option><?php endforeach; ?></select>
        </label>
        <label>Produto
            <select name="produto_id"><option value="0">Todos</option><?php foreach ($produtos as $item): ?><option value="<?php echo (int) $item['id']; ?>" <?php echo $filtros['produto_id'] === (int) $item['id'] ? 'selected' : ''; ?>><?php echo limpar($item['nome']); ?></option><?php endforeach; ?></select>
        </label>
        <label>Categoria
            <select name="categoria_id"><option value="0">Todas</option><?php foreach ($categorias as $item): ?><option value="<?php echo (int) $item['id']; ?>" <?php echo $filtros['categoria_id'] === (int) $item['id'] ? 'selected' : ''; ?>><?php echo limpar($item['nome']); ?></option><?php endforeach; ?></select>
        </label>
        <label>Fornecedor
            <select name="fornecedor_id"><option value="0">Todos</option><?php foreach ($fornecedores as $item): ?><option value="<?php echo (int) $item['id']; ?>" <?php echo $filtros['fornecedor_id'] === (int) $item['id'] ? 'selected' : ''; ?>><?php echo limpar($item['nome']); ?></option><?php endforeach; ?></select>
        </label>
        <label>Responsável
            <select name="usuario_id"><option value="0">Todos</option><?php foreach ($usuarios as $item): ?><option value="<?php echo (int) $item['id']; ?>" <?php echo $filtros['usuario_id'] === (int) $item['id'] ? 'selected' : ''; ?>><?php echo limpar($item['nome']); ?></option><?php endforeach; ?></select>
        </label>
        <label>Data inicial<input type="date" name="data_inicio" value="<?php echo limpar($filtros['data_inicio']); ?>"></label>
        <label>Data final<input type="date" name="data_fim" value="<?php echo limpar($filtros['data_fim']); ?>"></label>
        <div class="acoes-filtro"><button type="submit" class="botao">Filtrar</button><a href="<?php echo BASE_URL; ?>/reports/movements.php" class="botao botao-secundario">Limpar</a></div>
    </form>

    <p class="resumo-relatorio">
        Entradas: <strong><?php echo (int) $resumo['entradas']; ?></strong> |
        Saídas: <strong><?php echo (int) $resumo['saidas']; ?></strong> |
        Registros: <strong><?php echo $totalRegistros; ?></strong>
    </p>

    <?php if (!$movimentacoes): ?>
        <p class="texto-vazio">Nenhuma movimentação encontrada para os filtros selecionados.</p>
    <?php else: ?>
        <div class="tabela-responsiva"><table class="tabela">
            <thead><tr><th>Produto</th><th>Tipo</th><th>Quantidade</th><th>Saldo</th><th>Origem</th><th>Responsável</th><th>Data</th><th>Detalhes</th></tr></thead>
            <tbody><?php foreach ($movimentacoes as $mov): ?><tr>
                <td><?php echo limpar($mov['produto_nome']); ?><br><small><?php echo limpar($mov['categoria_nome'] ?: 'Sem categoria'); ?></small></td>
                <td><span class="etiqueta etiqueta-<?php echo $mov['tipo']; ?>"><?php echo $mov['tipo'] === 'entrada' ? 'Entrada' : 'Saída'; ?></span></td>
                <td><?php echo (int) $mov['quantidade']; ?></td>
                <td><?php echo (int) $mov['saldo_anterior']; ?> &rarr; <?php echo (int) $mov['saldo_posterior']; ?></td>
                <td><?php echo limpar(formatarOrigemMovimentacao($mov['origem'])); ?></td>
                <td><?php echo limpar($mov['usuario_nome']); ?></td>
                <td><?php echo formatarDataHora($mov['data_movimentacao']); ?></td>
                <td><?php echo limpar($mov['motivo'] ?: '-'); ?><?php if ($mov['fornecedor_nome']): ?><br><small>Fornecedor: <?php echo limpar($mov['fornecedor_nome']); ?></small><?php endif; ?><?php if ($mov['observacao']): ?><br><small><?php echo limpar($mov['observacao']); ?></small><?php endif; ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>

        <?php if ($totalPaginas > 1): ?>
            <nav class="paginacao" aria-label="Paginação do relatório">
                <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
                    <a class="<?php echo $i === $pagina ? 'ativo' : ''; ?>" href="?<?php echo limpar(http_build_query(array_merge($_GET, ['pagina' => $i]))); ?>" <?php echo $i === $pagina ? 'aria-current="page"' : ''; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

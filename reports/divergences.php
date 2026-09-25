<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$inventarioId = (int) ($_GET['inventario_id'] ?? 0);
$sql = "
    SELECT i.id AS inventario_id, i.titulo, i.status, p.sku, p.nome AS produto_nome,
           p.unidade, ii.estoque_sistema, ii.quantidade_contada, ii.diferenca,
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
$divergencias = $stmt->fetchAll();
$inventarios = $pdo->query('SELECT id, titulo FROM inventarios ORDER BY criado_em DESC')->fetchAll();
$queryCsv = http_build_query(['relatorio' => 'divergences', 'inventario_id' => $inventarioId]);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <div class="topo-pagina">
        <div><h1>Divergências de Inventário</h1><p class="descricao-pagina">Comparação entre o saldo registrado e a contagem física.</p></div>
        <div class="acoes-relatorio">
            <a class="botao" href="<?php echo BASE_URL; ?>/reports/export_csv.php?<?php echo limpar($queryCsv); ?>">Exportar CSV</a>
        <button type="button" class="botao botao-secundario" data-print>Imprimir / PDF</button>
        </div>
    </div>
    <form method="GET" class="form-filtros">
        <label for="inventario_id">Inventário</label>
        <select name="inventario_id" id="inventario_id">
            <option value="0">Todos</option>
            <?php foreach ($inventarios as $inventario): ?>
                <option value="<?php echo (int) $inventario['id']; ?>" <?php echo $inventarioId === (int) $inventario['id'] ? 'selected' : ''; ?>><?php echo limpar($inventario['titulo']); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao-secundario" href="<?php echo BASE_URL; ?>/reports/divergences.php">Limpar</a>
    </form>
    <p class="resumo-relatorio">Divergências encontradas: <strong><?php echo count($divergencias); ?></strong></p>
    <?php if (!$divergencias): ?>
        <p class="texto-vazio">Nenhuma divergência corresponde ao filtro.</p>
    <?php else: ?>
        <div class="tabela-responsiva"><table class="tabela">
            <thead><tr><th>Inventário</th><th>Produto</th><th>Saldo</th><th>Contagem</th><th>Diferença</th><th>Responsável</th><th>Data</th></tr></thead>
            <tbody>
            <?php foreach ($divergencias as $item): ?>
                <tr class="<?php echo (int) $item['diferenca'] < 0 ? 'linha-alerta' : ''; ?>">
                    <td><?php echo limpar($item['titulo']); ?></td>
                    <td><?php echo limpar($item['produto_nome']); ?> <small><?php echo limpar($item['sku'] ?: ''); ?></small></td>
                    <td><?php echo (int) $item['estoque_sistema']; ?> <?php echo limpar($item['unidade']); ?></td>
                    <td><?php echo (int) $item['quantidade_contada']; ?> <?php echo limpar($item['unidade']); ?></td>
                    <td><strong><?php echo (int) $item['diferenca'] > 0 ? '+' : ''; ?><?php echo (int) $item['diferenca']; ?></strong></td>
                    <td><?php echo limpar($item['contado_por_nome'] ?: '-'); ?></td>
                    <td><?php echo formatarDataHora($item['contado_em']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

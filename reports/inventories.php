<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$status = $_GET['status'] ?? '';
$sql = "
    SELECT i.id, i.titulo, i.status, i.criado_por_nome, i.criado_em,
           i.finalizado_por_nome, i.finalizado_em,
           COUNT(ii.id) AS total_itens,
           COALESCE(SUM(ii.quantidade_contada IS NOT NULL), 0) AS total_contados,
           COALESCE(SUM(ii.diferenca <> 0), 0) AS total_divergencias
    FROM inventarios i
    LEFT JOIN inventario_itens ii ON ii.inventario_id = i.id
    WHERE 1 = 1
";
$params = [];
if (in_array($status, ['aberto', 'finalizado', 'cancelado'], true)) {
    $sql .= ' AND i.status = :status';
    $params[':status'] = $status;
}
$sql .= ' GROUP BY i.id ORDER BY i.criado_em DESC, i.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventarios = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
$queryCsv = http_build_query(['relatorio' => 'inventories', 'status' => $status]);
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <div class="topo-pagina">
        <div><h1>Relatório de Inventários</h1><p class="descricao-pagina">Acompanhamento das contagens e divergências preservadas.</p></div>
        <div class="acoes-relatorio">
            <a class="botao" href="<?php echo BASE_URL; ?>/reports/export_csv.php?<?php echo limpar($queryCsv); ?>">Exportar CSV</a>
        <button type="button" class="botao botao-secundario" data-print>Imprimir / PDF</button>
        </div>
    </div>

    <form method="GET" class="form-filtros">
        <label for="status">Status</label>
        <select name="status" id="status">
            <option value="">Todos</option>
            <?php foreach (['aberto' => 'Aberto', 'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado'] as $valor => $rotulo): ?>
                <option value="<?php echo $valor; ?>" <?php echo $status === $valor ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
            <?php endforeach; ?>
        </select>
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao-secundario" href="<?php echo BASE_URL; ?>/reports/inventories.php">Limpar</a>
    </form>

    <?php if (!$inventarios): ?>
        <p class="texto-vazio">Nenhum inventário corresponde ao filtro.</p>
    <?php else: ?>
        <div class="tabela-responsiva"><table class="tabela">
            <thead><tr><th>Título</th><th>Status</th><th>Progresso</th><th>Divergências</th><th>Iniciado por</th><th>Início</th><th>Conclusão</th></tr></thead>
            <tbody>
            <?php foreach ($inventarios as $inventario): ?>
                <tr>
                    <td><a href="<?php echo BASE_URL; ?>/inventory/view.php?id=<?php echo (int) $inventario['id']; ?>"><?php echo limpar($inventario['titulo']); ?></a></td>
                    <td><?php echo limpar(ucfirst($inventario['status'])); ?></td>
                    <td><?php echo (int) $inventario['total_contados']; ?> / <?php echo (int) $inventario['total_itens']; ?></td>
                    <td><?php echo (int) $inventario['total_divergencias']; ?></td>
                    <td><?php echo limpar($inventario['criado_por_nome']); ?></td>
                    <td><?php echo formatarDataHora($inventario['criado_em']); ?></td>
                    <td><?php echo formatarDataHora($inventario['finalizado_em']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

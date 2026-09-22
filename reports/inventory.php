<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$status = trim((string) ($_GET['status'] ?? ''));
$where = '';
$params = [];
if (in_array($status, ['aberto', 'finalizado', 'cancelado'], true)) {
    $where = 'WHERE i.status = :status';
    $params[':status'] = $status;
}

$stmt = $pdo->prepare("
    SELECT i.id, i.titulo, i.status, i.criado_por_nome, i.finalizado_por_nome,
           i.criado_em, i.finalizado_em,
           COUNT(ii.id) AS total_itens,
           SUM(CASE WHEN ii.quantidade_contada IS NOT NULL THEN 1 ELSE 0 END) AS itens_contados,
           SUM(CASE WHEN ii.diferenca IS NOT NULL AND ii.diferenca <> 0 THEN 1 ELSE 0 END) AS divergencias,
           COALESCE(SUM(CASE WHEN ii.diferenca > 0 THEN ii.diferenca ELSE 0 END), 0) AS unidades_entrada,
           COALESCE(SUM(CASE WHEN ii.diferenca < 0 THEN ABS(ii.diferenca) ELSE 0 END), 0) AS unidades_saida
    FROM inventarios i
    LEFT JOIN inventario_itens ii ON ii.inventario_id = i.id
    {$where}
    GROUP BY i.id
    ORDER BY i.criado_em DESC, i.id DESC
");
$stmt->execute($params);
$inventarios = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <?php $exportQuery = http_build_query(array_filter(['report' => 'inventory', 'status' => $status], static fn ($v) => $v !== '')); ?>
    <div class="topo-pagina"><div><h1>Relatório de Inventários</h1><p class="descricao-pagina">Acompanhe contagens físicas, divergências e ajustes realizados.</p></div><div class="acoes-formulario"><a class="botao botao-secundario" href="<?php echo BASE_URL; ?>/reports/export_csv.php?<?php echo limpar($exportQuery); ?>">Exportar CSV</a><button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir / PDF</button></div></div>
    <form method="GET" class="form-filtros">
        <select name="status" aria-label="Filtrar por status"><option value="">Todos os status</option><option value="aberto" <?php echo $status === 'aberto' ? 'selected' : ''; ?>>Aberto</option><option value="finalizado" <?php echo $status === 'finalizado' ? 'selected' : ''; ?>>Finalizado</option><option value="cancelado" <?php echo $status === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option></select>
        <button class="botao" type="submit">Filtrar</button><a class="botao botao-secundario" href="<?php echo BASE_URL; ?>/reports/inventory.php">Limpar</a>
    </form>
    <?php if (!$inventarios): ?><p class="texto-vazio">Nenhum inventário encontrado.</p><?php else: ?>
    <div class="tabela-responsiva"><table class="tabela"><thead><tr><th>Inventário</th><th>Status</th><th>Itens</th><th>Contados</th><th>Divergências</th><th>Ajustes +</th><th>Ajustes -</th><th>Criado em</th><th>Finalizado em</th></tr></thead><tbody>
    <?php foreach ($inventarios as $i): ?><tr>
        <td><a href="<?php echo BASE_URL; ?>/inventory/view.php?id=<?php echo (int) $i['id']; ?>"><?php echo limpar($i['titulo']); ?></a></td><td><?php echo limpar(ucfirst($i['status'])); ?></td><td><?php echo (int) $i['total_itens']; ?></td><td><?php echo (int) $i['itens_contados']; ?></td><td><?php echo (int) $i['divergencias']; ?></td><td><?php echo (int) $i['unidades_entrada']; ?></td><td><?php echo (int) $i['unidades_saida']; ?></td><td><?php echo formatarDataHora($i['criado_em']); ?></td><td><?php echo formatarDataHora($i['finalizado_em']); ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

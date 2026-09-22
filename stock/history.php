<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$filtroTipo = trim((string) ($_GET['tipo'] ?? ''));
$filtroProduto = (int) ($_GET['produto_id'] ?? 0);
$filtroOrigem = trim((string) ($_GET['origem'] ?? ''));
$dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim = trim((string) ($_GET['data_fim'] ?? ''));
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 30;

$where = ' WHERE 1=1 ';
$params = [];
if (in_array($filtroTipo, ['entrada','saida'], true)) { $where .= ' AND m.tipo = :tipo'; $params[':tipo'] = $filtroTipo; }
if ($filtroProduto > 0) { $where .= ' AND m.produto_id = :produto_id'; $params[':produto_id'] = $filtroProduto; }
if (in_array($filtroOrigem, ['manual','ajuste','inventario'], true)) { $where .= ' AND m.origem = :origem'; $params[':origem'] = $filtroOrigem; }
if ($dataInicio !== '') { $where .= ' AND m.data_movimentacao >= :data_inicio'; $params[':data_inicio'] = $dataInicio . ' 00:00:00'; }
if ($dataFim !== '') { $where .= ' AND m.data_movimentacao <= :data_fim'; $params[':data_fim'] = $dataFim . ' 23:59:59'; }

$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM movimentacoes m' . $where);
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$sql = "SELECT m.*, p.nome AS produto_nome, p.unidade, u.nome AS usuario_nome, f.nome AS fornecedor_nome
        FROM movimentacoes m
        INNER JOIN produtos p ON p.id = m.produto_id
        INNER JOIN usuarios u ON u.id = m.usuario_id
        LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
        $where ORDER BY m.data_movimentacao DESC LIMIT $porPagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimentacoes = $stmt->fetchAll();
$produtos = $pdo->query('SELECT id, nome FROM produtos ORDER BY nome ASC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <div class="topo-pagina"><h1>Movimentações de Estoque</h1><div>
        <a href="<?php echo BASE_URL; ?>/stock/entry.php" class="botao botao-sucesso">+ Entrada</a>
        <a href="<?php echo BASE_URL; ?>/stock/exit.php" class="botao botao-alerta">- Saída</a>
        <?php if (ehAdministrador()): ?><a href="<?php echo BASE_URL; ?>/stock/adjust.php" class="botao botao-secundario">Ajustar Estoque</a><?php endif; ?>
    </div></div>

    <form method="GET" class="form-filtros">
        <select name="tipo" aria-label="Filtrar por tipo"><option value="">Todos os tipos</option><option value="entrada" <?php echo $filtroTipo==='entrada'?'selected':''; ?>>Entrada</option><option value="saida" <?php echo $filtroTipo==='saida'?'selected':''; ?>>Saída</option></select>
        <select name="origem" aria-label="Filtrar por origem"><option value="">Todas as origens</option><option value="manual" <?php echo $filtroOrigem==='manual'?'selected':''; ?>>Manual</option><option value="ajuste" <?php echo $filtroOrigem==='ajuste'?'selected':''; ?>>Ajuste</option><option value="inventario" <?php echo $filtroOrigem==='inventario'?'selected':''; ?>>Inventário</option></select>
        <select name="produto_id" aria-label="Selecionar produto"><option value="0">Todos os produtos</option><?php foreach($produtos as $produto): ?><option value="<?php echo (int)$produto['id']; ?>" <?php echo $filtroProduto===(int)$produto['id']?'selected':''; ?>><?php echo limpar($produto['nome']); ?></option><?php endforeach; ?></select>
        <label class="rotulo-inline">De: <input type="date" name="data_inicio" value="<?php echo limpar($dataInicio); ?>"></label>
        <label class="rotulo-inline">Até: <input type="date" name="data_fim" value="<?php echo limpar($dataFim); ?>"></label>
        <button type="submit" class="botao">Filtrar</button><a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">Limpar</a>
    </form>

    <p class="resumo-relatorio">Registros encontrados: <strong><?php echo $total; ?></strong></p>
    <?php if (!$movimentacoes): ?><p class="texto-vazio">Nenhuma movimentação encontrada.</p><?php else: ?>
    <div class="tabela-responsiva"><table class="tabela"><thead><tr><th>Produto</th><th>Tipo</th><th>Origem</th><th>Qtd.</th><th>Saldo anterior</th><th>Saldo posterior</th><th>Motivo</th><th>Responsável</th><th>Data</th></tr></thead><tbody>
    <?php foreach($movimentacoes as $mov): ?><tr>
        <td><?php echo limpar($mov['produto_nome']); ?></td><td><span class="etiqueta etiqueta-<?php echo limpar($mov['tipo']); ?>"><?php echo $mov['tipo']==='entrada'?'Entrada':'Saída'; ?></span></td>
        <td><?php echo limpar(ucfirst((string)($mov['origem'] ?? 'manual'))); ?></td><td><?php echo (int)$mov['quantidade']; ?> <?php echo limpar($mov['unidade']); ?></td>
        <td><?php echo $mov['saldo_anterior'] !== null ? (int)$mov['saldo_anterior'] : '-'; ?></td><td><?php echo $mov['saldo_posterior'] !== null ? (int)$mov['saldo_posterior'] : '-'; ?></td>
        <td><?php echo limpar($mov['motivo'] ?? ($mov['observacao'] ?? '-')); ?></td><td><?php echo limpar($mov['usuario_nome']); ?></td><td><?php echo formatarDataHora($mov['data_movimentacao']); ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>

    <?php if ($totalPaginas > 1): ?><nav class="paginacao" aria-label="Paginação"><span>Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?></span>
        <?php $query=$_GET; if($pagina>1){$query['pagina']=$pagina-1;} ?><a class="botao botao-secundario <?php echo $pagina<=1?'desabilitado':''; ?>" href="<?php echo $pagina>1 ? '?' . limpar(http_build_query($query)) : '#'; ?>">Anterior</a>
        <?php $query=$_GET; if($pagina<$totalPaginas){$query['pagina']=$pagina+1;} ?><a class="botao botao-secundario <?php echo $pagina>=$totalPaginas?'desabilitado':''; ?>" href="<?php echo $pagina<$totalPaginas ? '?' . limpar(http_build_query($query)) : '#'; ?>">Próxima</a>
    </nav><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

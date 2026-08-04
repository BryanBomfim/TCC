<?php
/**
 * reports/movements.php
 * Relatório de movimentações de estoque com filtros por tipo e período.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$filtroTipo = $_GET['tipo'] ?? '';
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';

$sql = "
    SELECT m.*, p.nome AS produto_nome, u.nome AS usuario_nome
    FROM movimentacoes m
    INNER JOIN produtos p ON p.id = m.produto_id
    INNER JOIN usuarios u ON u.id = m.usuario_id
    WHERE 1 = 1
";
$parametros = [];

if ($filtroTipo === 'entrada' || $filtroTipo === 'saida') {
    $sql .= " AND m.tipo = :tipo";
    $parametros[':tipo'] = $filtroTipo;
}
if ($dataInicio !== '') {
    $sql .= " AND DATE(m.data_movimentacao) >= :data_inicio";
    $parametros[':data_inicio'] = $dataInicio;
}
if ($dataFim !== '') {
    $sql .= " AND DATE(m.data_movimentacao) <= :data_fim";
    $parametros[':data_fim'] = $dataFim;
}
$sql .= " ORDER BY m.data_movimentacao DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$movimentacoes = $stmt->fetchAll();

$totalEntradas = 0;
$totalSaidas = 0;
foreach ($movimentacoes as $m) {
    if ($m['tipo'] === 'entrada') $totalEntradas += (int) $m['quantidade'];
    else $totalSaidas += (int) $m['quantidade'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo">
    <h1>Relatório de Movimentações</h1>

    <form method="GET" action="" class="form-filtros">
        <select name="tipo">
            <option value="">Todos os tipos</option>
            <option value="entrada" <?php echo $filtroTipo === 'entrada' ? 'selected' : ''; ?>>Entrada</option>
            <option value="saida" <?php echo $filtroTipo === 'saida' ? 'selected' : ''; ?>>Saída</option>
        </select>
        <label class="rotulo-inline">De: <input type="date" name="data_inicio" value="<?php echo limpar($dataInicio); ?>"></label>
        <label class="rotulo-inline">Até: <input type="date" name="data_fim" value="<?php echo limpar($dataFim); ?>"></label>
        <button type="submit" class="botao">Filtrar</button>
        <a href="<?php echo BASE_URL; ?>/reports/movements.php" class="botao botao-secundario">Limpar</a>
        <button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir</button>
    </form>

    <p class="resumo-relatorio">
        Total de entradas: <strong><?php echo $totalEntradas; ?></strong> |
        Total de saídas: <strong><?php echo $totalSaidas; ?></strong> |
        Registros: <strong><?php echo count($movimentacoes); ?></strong>
    </p>

    <?php if (empty($movimentacoes)): ?>
        <p class="texto-vazio">Nenhuma movimentação encontrada para os filtros selecionados.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr><th>Produto</th><th>Tipo</th><th>Quantidade</th><th>Usuário</th><th>Data</th><th>Observação</th></tr>
            </thead>
            <tbody>
                <?php foreach ($movimentacoes as $mov): ?>
                <tr>
                    <td><?php echo limpar($mov['produto_nome']); ?></td>
                    <td><?php echo $mov['tipo'] === 'entrada' ? 'Entrada' : 'Saída'; ?></td>
                    <td><?php echo (int) $mov['quantidade']; ?></td>
                    <td><?php echo limpar($mov['usuario_nome']); ?></td>
                    <td><?php echo formatarDataHora($mov['data_movimentacao']); ?></td>
                    <td><?php echo limpar($mov['observacao'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

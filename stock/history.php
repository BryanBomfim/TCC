<?php
/**
 * stock/history.php
 * Exibe o histórico completo de movimentações de estoque,
 * com filtros por tipo, produto e período.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$filtroTipo = $_GET['tipo'] ?? '';
$filtroProduto = (int) ($_GET['produto_id'] ?? 0);
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';

$sql = "
    SELECT
        m.*,
        p.nome AS produto_nome,
        u.nome AS usuario_nome,
        f.nome AS fornecedor_nome
    FROM movimentacoes m
    INNER JOIN produtos p ON p.id = m.produto_id
    INNER JOIN usuarios u ON u.id = m.usuario_id
    LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
    WHERE 1 = 1
";
$parametros = [];

if ($filtroTipo === 'entrada' || $filtroTipo === 'saida') {
    $sql .= " AND m.tipo = :tipo";
    $parametros[':tipo'] = $filtroTipo;
}
if ($filtroProduto > 0) {
    $sql .= " AND m.produto_id = :produto_id";
    $parametros[':produto_id'] = $filtroProduto;
}
if ($dataInicio !== '') {
    $sql .= " AND DATE(m.data_movimentacao) >= :data_inicio";
    $parametros[':data_inicio'] = $dataInicio;
}
if ($dataFim !== '') {
    $sql .= " AND DATE(m.data_movimentacao) <= :data_fim";
    $parametros[':data_fim'] = $dataFim;
}

$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM (' . $sql . ') AS movimentos_filtrados');
$stmtTotal->execute($parametros);
$totalMovimentacoes = (int) $stmtTotal->fetchColumn();
$paginacao = calcularPaginacao($totalMovimentacoes, 50);

$sql .= " ORDER BY m.data_movimentacao DESC, m.id DESC"
    . " LIMIT {$paginacao['por_pagina']} OFFSET {$paginacao['offset']}";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$movimentacoes = $stmt->fetchAll();

$produtos = $pdo->query("SELECT id, nome FROM produtos ORDER BY nome ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <div class="topo-pagina">
        <h1>Movimentações de Estoque</h1>
        <div>
            <a href="<?php echo BASE_URL; ?>/stock/entry.php" class="botao botao-sucesso">Registrar entrada</a>
            <?php if (ehAdministrador()): ?>
                <a href="<?php echo BASE_URL; ?>/stock/adjust.php" class="botao botao-secundario">Ajustar saldo</a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>/stock/exit.php" class="botao botao-alerta">- Saída</a>
        </div>
    </div>

    <form method="GET" action="" class="form-filtros">
        <select name="tipo" aria-label="Filtrar por tipo">
            <option value="">Todos os tipos</option>
            <option value="entrada" <?php echo $filtroTipo === 'entrada' ? 'selected' : ''; ?>>Entrada</option>
            <option value="saida" <?php echo $filtroTipo === 'saida' ? 'selected' : ''; ?>>Saída</option>
        </select>

        <select name="produto_id" aria-label="Selecionar produto">
            <option value="0">Todos os produtos</option>
            <?php foreach ($produtos as $produto): ?>
                <option value="<?php echo $produto['id']; ?>" <?php echo $filtroProduto == $produto['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($produto['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="rotulo-inline">De: <input type="date" name="data_inicio" value="<?php echo limpar($dataInicio); ?>" aria-label="Data inicial"></label>
        <label class="rotulo-inline">Até: <input type="date" name="data_fim" value="<?php echo limpar($dataFim); ?>" aria-label="Data final"></label>

        <button type="submit" class="botao">Filtrar</button>
        <a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">Limpar</a>
    </form>

    <?php if (empty($movimentacoes)): ?>
        <p class="texto-vazio">Nenhuma movimentação encontrada.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr><th scope="col">Produto</th><th scope="col">Tipo</th><th scope="col">Quantidade</th><th scope="col">Saldo</th><th scope="col">Origem</th><th scope="col">Responsável</th><th scope="col">Data</th><th scope="col">Detalhes</th></tr>
            </thead>
            <tbody>
                <?php foreach ($movimentacoes as $mov): ?>
                <tr>
                    <td><?php echo limpar($mov['produto_nome']); ?></td>
                    <td>
                        <span class="etiqueta etiqueta-<?php echo $mov['tipo']; ?>">
                            <?php echo $mov['tipo'] === 'entrada' ? 'Entrada' : 'Saída'; ?>
                        </span>
                    </td>
                    <td><?php echo (int) $mov['quantidade']; ?></td>
                    <td><?php echo (int) $mov['saldo_anterior']; ?> → <?php echo (int) $mov['saldo_posterior']; ?></td>
                    <td><?php echo limpar(formatarOrigemMovimentacao($mov['origem'])); ?></td>
                    <td><?php echo limpar($mov['usuario_nome']); ?></td>
                    <td><?php echo formatarDataHora($mov['data_movimentacao']); ?></td>
                    <td>
                        <?php echo limpar($mov['motivo'] ?? ''); ?>
                        <?php if (!empty($mov['fornecedor_nome'])): ?>
                            <br><small>Fornecedor: <?php echo limpar($mov['fornecedor_nome']); ?></small>
                        <?php endif; ?>
                        <?php if (!empty($mov['observacao'])): ?>
                            <br><small><?php echo limpar($mov['observacao']); ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php exibirPaginacao($paginacao, 'Paginação de movimentações'); ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

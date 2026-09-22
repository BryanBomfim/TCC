<?php
/** Relatório detalhado e filtrável das movimentações de estoque. */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$filtroTipo = trim((string) ($_GET['tipo'] ?? ''));
$filtroProduto = max(0, (int) ($_GET['produto_id'] ?? 0));
$filtroUsuario = max(0, (int) ($_GET['usuario_id'] ?? 0));
$filtroFornecedor = max(0, (int) ($_GET['fornecedor_id'] ?? 0));
$dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim = trim((string) ($_GET['data_fim'] ?? ''));

$where = ['1 = 1'];
$params = [];

if (in_array($filtroTipo, ['entrada', 'saida'], true)) {
    $where[] = 'm.tipo = :tipo';
    $params[':tipo'] = $filtroTipo;
}
if ($filtroProduto > 0) {
    $where[] = 'm.produto_id = :produto';
    $params[':produto'] = $filtroProduto;
}
if ($filtroUsuario > 0) {
    $where[] = 'm.usuario_id = :usuario';
    $params[':usuario'] = $filtroUsuario;
}
if ($filtroFornecedor > 0) {
    $where[] = 'm.fornecedor_id = :fornecedor';
    $params[':fornecedor'] = $filtroFornecedor;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
    $where[] = 'm.data_movimentacao >= :inicio';
    $params[':inicio'] = $dataInicio . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
    $where[] = 'm.data_movimentacao <= :fim';
    $params[':fim'] = $dataFim . ' 23:59:59';
}

$sql = "
    SELECT
        m.*,
        p.nome AS produto_nome,
        p.unidade,
        u.nome AS usuario_nome,
        f.nome AS fornecedor_nome
    FROM movimentacoes m
    INNER JOIN produtos p ON p.id = m.produto_id
    INNER JOIN usuarios u ON u.id = m.usuario_id
    LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY m.data_movimentacao DESC, m.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimentacoes = $stmt->fetchAll();

$totalEntradas = 0;
$totalSaidas = 0;
foreach ($movimentacoes as $movimentacao) {
    if ($movimentacao['tipo'] === 'entrada') {
        $totalEntradas += (int) $movimentacao['quantidade'];
    } else {
        $totalSaidas += (int) $movimentacao['quantidade'];
    }
}

$produtos = $pdo->query('SELECT id, nome FROM produtos ORDER BY nome')->fetchAll();
$usuarios = $pdo->query('SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome')->fetchAll();
$fornecedores = $pdo->query('SELECT id, nome FROM fornecedores WHERE ativo = 1 ORDER BY nome')->fetchAll();

$parametrosExportacao = http_build_query(array_filter([
    'report' => 'movements',
    'tipo' => $filtroTipo,
    'produto_id' => $filtroProduto ?: null,
    'fornecedor_id' => $filtroFornecedor ?: null,
    'usuario_id' => $filtroUsuario ?: null,
    'data_inicio' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio) ? $dataInicio : null,
    'data_fim' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim) ? $dataFim : null,
], static fn ($valor) => $valor !== null && $valor !== ''));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Relatório de Movimentações</h1>

    <form method="GET" action="" class="form-filtros">
        <select name="tipo" aria-label="Filtrar por tipo">
            <option value="">Todos os tipos</option>
            <option value="entrada" <?php echo $filtroTipo === 'entrada' ? 'selected' : ''; ?>>Entrada</option>
            <option value="saida" <?php echo $filtroTipo === 'saida' ? 'selected' : ''; ?>>Saída</option>
        </select>

        <select name="produto_id" aria-label="Filtrar por produto">
            <option value="0">Todos os produtos</option>
            <?php foreach ($produtos as $produto): ?>
                <option value="<?php echo (int) $produto['id']; ?>" <?php echo $filtroProduto === (int) $produto['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($produto['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="fornecedor_id" aria-label="Filtrar por fornecedor">
            <option value="0">Todos os fornecedores</option>
            <?php foreach ($fornecedores as $fornecedor): ?>
                <option value="<?php echo (int) $fornecedor['id']; ?>" <?php echo $filtroFornecedor === (int) $fornecedor['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($fornecedor['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="usuario_id" aria-label="Filtrar por usuário">
            <option value="0">Todos os usuários</option>
            <?php foreach ($usuarios as $usuario): ?>
                <option value="<?php echo (int) $usuario['id']; ?>" <?php echo $filtroUsuario === (int) $usuario['id'] ? 'selected' : ''; ?>>
                    <?php echo limpar($usuario['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="rotulo-inline">De:
            <input type="date" name="data_inicio" value="<?php echo limpar($dataInicio); ?>" aria-label="Data inicial">
        </label>
        <label class="rotulo-inline">Até:
            <input type="date" name="data_fim" value="<?php echo limpar($dataFim); ?>" aria-label="Data final">
        </label>

        <button type="submit" class="botao">Filtrar</button>
        <a href="<?php echo BASE_URL; ?>/reports/movements.php" class="botao botao-secundario">Limpar</a>
        <a href="<?php echo BASE_URL; ?>/reports/export_csv.php?<?php echo limpar($parametrosExportacao); ?>" class="botao botao-secundario">Exportar CSV</a>
        <button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir / PDF</button>
    </form>

    <p class="resumo-relatorio">
        Total de entradas: <strong><?php echo $totalEntradas; ?></strong> |
        Total de saídas: <strong><?php echo $totalSaidas; ?></strong> |
        Registros: <strong><?php echo count($movimentacoes); ?></strong>
    </p>

    <?php if (empty($movimentacoes)): ?>
        <p class="texto-vazio">Nenhuma movimentação encontrada para os filtros selecionados.</p>
    <?php else: ?>
        <div class="tabela-responsiva">
            <table class="tabela">
                <thead>
                    <tr>
                        <th scope="col">Data</th>
                        <th scope="col">Produto</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Origem</th>
                        <th scope="col">Qtd.</th>
                        <th scope="col">Saldo</th>
                        <th scope="col">Fornecedor</th>
                        <th scope="col">Responsável</th>
                        <th scope="col">Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimentacoes as $mov): ?>
                        <tr>
                            <td><?php echo formatarDataHora($mov['data_movimentacao']); ?></td>
                            <td><?php echo limpar($mov['produto_nome']); ?></td>
                            <td><?php echo $mov['tipo'] === 'entrada' ? 'Entrada' : 'Saída'; ?></td>
                            <td><?php echo limpar(ucfirst((string) ($mov['origem'] ?? 'manual'))); ?></td>
                            <td><?php echo (int) $mov['quantidade']; ?> <?php echo limpar($mov['unidade']); ?></td>
                            <td>
                                <?php echo $mov['saldo_anterior'] === null ? '-' : (int) $mov['saldo_anterior']; ?>
                                &rarr;
                                <?php echo $mov['saldo_posterior'] === null ? '-' : (int) $mov['saldo_posterior']; ?>
                            </td>
                            <td><?php echo limpar($mov['fornecedor_nome'] ?? '-'); ?></td>
                            <td><?php echo limpar($mov['usuario_nome']); ?></td>
                            <td><?php echo limpar($mov['motivo'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

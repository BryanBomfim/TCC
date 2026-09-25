<?php
/**
 * dashboard/index.php
 * Painel principal exibido após o login, com indicadores resumidos
 * do sistema, produtos com estoque baixo e últimas movimentações.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

// --- Indicadores gerais (uma única varredura da tabela de produtos) ---
$indicadoresProdutos = $pdo->query("
    SELECT
        COUNT(*) AS total_produtos,
        COALESCE(SUM(quantidade_estoque), 0) AS quantidade_total,
        COALESCE(SUM(quantidade_estoque <= estoque_minimo), 0) AS estoque_baixo,
        COALESCE(SUM(quantidade_estoque = 0), 0) AS sem_estoque
    FROM produtos
    WHERE ativo = 1
")->fetch();
$totalProdutos = (int) $indicadoresProdutos['total_produtos'];
$quantidadeTotalEstoque = (int) $indicadoresProdutos['quantidade_total'];
$qtdEstoqueBaixo = (int) $indicadoresProdutos['estoque_baixo'];
$qtdSemEstoque = (int) $indicadoresProdutos['sem_estoque'];
$totalCategorias = $pdo->query("SELECT COUNT(*) FROM categorias WHERE ativo = 1")->fetchColumn();
$totalFornecedores = $pdo->query("SELECT COUNT(*) FROM fornecedores WHERE ativo = 1")->fetchColumn();
$totalUsuarios = ehAdministrador() ? $pdo->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1")->fetchColumn() : null;
$movimentacoesPeriodo = (int) $pdo->query("
    SELECT COUNT(*)
    FROM movimentacoes
    WHERE data_movimentacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetchColumn();

// --- Produtos com estoque baixo ---
$stmtBaixo = $pdo->query("
    SELECT id, nome, quantidade_estoque, estoque_minimo
    FROM produtos
    WHERE ativo = 1 AND quantidade_estoque <= estoque_minimo
    ORDER BY quantidade_estoque ASC
    LIMIT 8
");
$produtosBaixoEstoque = $stmtBaixo->fetchAll();

$inventariosRecentes = $pdo->query("
    SELECT id, titulo, status, criado_em, finalizado_em
    FROM inventarios
    ORDER BY criado_em DESC, id DESC
    LIMIT 5
")->fetchAll();

// --- Últimas movimentações ---
$stmtMov = $pdo->query("
    SELECT m.id, m.tipo, m.quantidade, m.data_movimentacao, p.nome AS produto_nome, u.nome AS usuario_nome
    FROM movimentacoes m
    INNER JOIN produtos p ON p.id = m.produto_id
    INNER JOIN usuarios u ON u.id = m.usuario_id
    ORDER BY m.data_movimentacao DESC
    LIMIT 8
");
$ultimasMovimentacoes = $stmtMov->fetchAll();

// --- Produtos cadastrados recentemente ---
$stmtRecentes = $pdo->query("
    SELECT id, nome, quantidade_estoque, data_cadastro
    FROM produtos
    WHERE ativo = 1
    ORDER BY data_cadastro DESC
    LIMIT 5
");
$produtosRecentes = $stmtRecentes->fetchAll();

// --- Movimentações do dia (para o gráfico) ---
$stmtDia = $pdo->prepare("
    SELECT tipo, COUNT(*) AS total, COALESCE(SUM(quantidade),0) AS soma_quantidade
    FROM movimentacoes
    WHERE DATE(data_movimentacao) = :hoje
    GROUP BY tipo
");
$stmtDia->execute([':hoje' => date('Y-m-d')]);
$movimentacoesDia = $stmtDia->fetchAll();
$entradasHoje = 0;
$saidasHoje = 0;
foreach ($movimentacoesDia as $linha) {
    if ($linha['tipo'] === 'entrada')
        $entradasHoje = (int) $linha['soma_quantidade'];
    if ($linha['tipo'] === 'saida')
        $saidasHoje = (int) $linha['soma_quantidade'];
}

// --- Movimentações dos últimos 7 dias (para gráfico de linha simples em JS puro) ---
$stmtSemana = $pdo->prepare("
    SELECT DATE(data_movimentacao) AS dia, tipo, COALESCE(SUM(quantidade),0) AS total
    FROM movimentacoes
    WHERE data_movimentacao >= :inicio
    GROUP BY DATE(data_movimentacao), tipo
    ORDER BY dia ASC
");
$stmtSemana->execute([':inicio' => date('Y-m-d', strtotime('-6 days'))]);
$dadosSemana = $stmtSemana->fetchAll();

$labelsSemana = [];
$entradasSemana = [];
$saidasSemana = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-{$i} days"));
    $labelsSemana[$dia] = date('d/m', strtotime($dia));
    $entradasSemana[$dia] = 0;
    $saidasSemana[$dia] = 0;
}
foreach ($dadosSemana as $linha) {
    if (isset($entradasSemana[$linha['dia']]) && $linha['tipo'] === 'entrada') {
        $entradasSemana[$linha['dia']] = (int) $linha['total'];
    }
    if (isset($saidasSemana[$linha['dia']]) && $linha['tipo'] === 'saida') {
        $saidasSemana[$linha['dia']] = (int) $linha['total'];
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Dashboard</h1>
    <p class="descricao-pagina">Bem-vindo(a), <?php echo limpar(nomeUsuarioLogado()); ?>! Aqui está um resumo do
        sistema.</p>

    <div class="cartoes">
        <div class="cartao">
            <span class="cartao-titulo">Produtos</span>
            <span class="cartao-valor"><?php echo (int) $totalProdutos; ?></span>
        </div>
        <div class="cartao">
            <span class="cartao-titulo">Categorias</span>
            <span class="cartao-valor"><?php echo (int) $totalCategorias; ?></span>
        </div>
        <div class="cartao">
            <span class="cartao-titulo">Unidades em estoque</span>
            <span class="cartao-valor"><?php echo $quantidadeTotalEstoque; ?></span>
        </div>
        <div class="cartao">
            <span class="cartao-titulo">Fornecedores</span>
            <span class="cartao-valor"><?php echo (int) $totalFornecedores; ?></span>
        </div>
        <?php if (ehAdministrador()): ?>
            <div class="cartao">
                <span class="cartao-titulo">Usuários</span>
                <span class="cartao-valor"><?php echo (int) $totalUsuarios; ?></span>
            </div>
        <?php endif; ?>
        <div class="cartao cartao-alerta">
            <span class="cartao-titulo">Estoque Baixo</span>
            <span class="cartao-valor"><?php echo (int) $qtdEstoqueBaixo; ?></span>
        </div>
        <div class="cartao cartao-alerta">
            <span class="cartao-titulo">Sem estoque</span>
            <span class="cartao-valor"><?php echo $qtdSemEstoque; ?></span>
        </div>
        <div class="cartao">
            <span class="cartao-titulo">Movimentos em 30 dias</span>
            <span class="cartao-valor"><?php echo $movimentacoesPeriodo; ?></span>
        </div>
        <div class="cartao">
            <span class="cartao-titulo">Entradas Hoje</span>
            <span class="cartao-valor"><?php echo $entradasHoje; ?></span>
        </div>
        <div class="cartao">
            <span class="cartao-titulo">Saídas Hoje</span>
            <span class="cartao-valor"><?php echo $saidasHoje; ?></span>
        </div>
    </div>

    <div class="grade-dashboard">
        <div class="painel">
            <h2>Movimentações dos últimos 7 dias</h2>
            <canvas id="graficoSemana" width="400" height="200" role="img"
                data-labels="<?php echo limpar(json_encode(array_values($labelsSemana), JSON_UNESCAPED_UNICODE)); ?>"
                data-entradas="<?php echo limpar(json_encode(array_values($entradasSemana))); ?>"
                data-saidas="<?php echo limpar(json_encode(array_values($saidasSemana))); ?>"
                aria-describedby="descricaoGraficoSemana"></canvas>
            <p id="descricaoGraficoSemana" class="sr-only">Gráfico de movimentações dos últimos sete dias. A tabela
                acessível a seguir contém os mesmos valores de entradas e saídas.</p>
            <table class="sr-only">
                <caption>Dados do gráfico de movimentações dos últimos 7 dias</caption>
                <thead>
                    <tr>
                        <th scope="col">Dia</th>
                        <th scope="col">Entradas</th>
                        <th scope="col">Saídas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($labelsSemana as $diaIso => $diaLabel): ?>
                        <tr>
                            <th scope="row"><?php echo limpar($diaLabel); ?></th>
                            <td><?php echo (int) $entradasSemana[$diaIso]; ?></td>
                            <td><?php echo (int) $saidasSemana[$diaIso]; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="painel">
            <h2>Produtos com estoque baixo</h2>
            <?php if (empty($produtosBaixoEstoque)): ?>
                <p class="texto-vazio">Nenhum produto com estoque baixo no momento.</p>
            <?php else: ?>
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Produto</th>
                            <th scope="col">Estoque</th>
                            <th scope="col">Mínimo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtosBaixoEstoque as $produto): ?>
                            <tr>
                                <td><?php echo limpar($produto['nome']); ?></td>
                                <td><?php echo (int) $produto['quantidade_estoque']; ?></td>
                                <td><?php echo (int) $produto['estoque_minimo']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="grade-dashboard">
        <div class="painel">
            <h2>Últimas movimentações</h2>
            <?php if (empty($ultimasMovimentacoes)): ?>
                <p class="texto-vazio">Nenhuma movimentação registrada ainda.</p>
            <?php else: ?>
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Produto</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Qtd.</th>
                            <th scope="col">Usuário</th>
                            <th scope="col">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimasMovimentacoes as $mov): ?>
                            <tr>
                                <td><?php echo limpar($mov['produto_nome']); ?></td>
                                <td>
                                    <span class="etiqueta etiqueta-<?php echo $mov['tipo']; ?>">
                                        <?php echo $mov['tipo'] === 'entrada' ? 'Entrada' : 'Saída'; ?>
                                    </span>
                                </td>
                                <td><?php echo (int) $mov['quantidade']; ?></td>
                                <td><?php echo limpar($mov['usuario_nome']); ?></td>
                                <td><?php echo formatarDataHora($mov['data_movimentacao']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="painel">
            <h2>Produtos cadastrados recentemente</h2>
            <?php if (empty($produtosRecentes)): ?>
                <p class="texto-vazio">Nenhum produto cadastrado ainda.</p>
            <?php else: ?>
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Produto</th>
                            <th scope="col">Estoque</th>
                            <th scope="col">Cadastrado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtosRecentes as $produto): ?>
                            <tr>
                                <td><?php echo limpar($produto['nome']); ?></td>
                                <td><?php echo (int) $produto['quantidade_estoque']; ?></td>
                                <td><?php echo formatarDataHora($produto['data_cadastro']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="painel">
        <div class="topo-pagina">
            <h2>Inventários recentes</h2>
            <a href="<?php echo BASE_URL; ?>/inventory/index.php" class="botao botao-secundario">Ver inventários</a>
        </div>
        <?php if (empty($inventariosRecentes)): ?>
            <p class="texto-vazio">Nenhum inventário foi iniciado.</p>
        <?php else: ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Título</th>
                            <th scope="col">Status</th>
                            <th scope="col">Iniciado em</th>
                            <th scope="col">Finalizado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventariosRecentes as $inventario): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/inventory/view.php?id=<?php echo (int) $inventario['id']; ?>">
                                        <?php echo limpar($inventario['titulo']); ?>
                                    </a>
                                </td>
                                <td><span class="etiqueta"><?php echo limpar(ucfirst($inventario['status'])); ?></span></td>
                                <td><?php echo formatarDataHora($inventario['criado_em']); ?></td>
                                <td><?php echo formatarDataHora($inventario['finalizado_em']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

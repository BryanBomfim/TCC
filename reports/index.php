<?php
/**
 * reports/index.php
 * Página inicial da área de relatórios, com links para cada
 * relatório disponível no sistema.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Relatórios</h1>
    <p class="descricao-pagina">Selecione um relatório para consultar as informações do sistema.</p>

    <div class="cartoes-relatorios">
        <a href="<?php echo BASE_URL; ?>/reports/products.php" class="cartao-relatorio">
            <h3>Produtos</h3>
            <p>Lista completa de produtos cadastrados, com filtros por categoria e fornecedor.</p>
        </a>
        <a href="<?php echo BASE_URL; ?>/reports/movements.php" class="cartao-relatorio">
            <h3>Movimentações</h3>
            <p>Histórico de entradas e saídas de estoque, com filtros por período e tipo.</p>
        </a>
        <a href="<?php echo BASE_URL; ?>/reports/categories.php" class="cartao-relatorio">
            <h3>Categorias</h3>
            <p>Lista de categorias cadastradas e quantidade de produtos por categoria.</p>
        </a>
        <a href="<?php echo BASE_URL; ?>/reports/suppliers.php" class="cartao-relatorio">
            <h3>Fornecedores</h3>
            <p>Lista de fornecedores cadastrados e quantidade de produtos por fornecedor.</p>
        </a>
        <?php if (ehAdministrador()): ?>
        <a href="<?php echo BASE_URL; ?>/reports/users.php" class="cartao-relatorio">
            <h3>Usuários</h3>
            <p>Lista de usuários do sistema e quantidade de movimentações realizadas por cada um.</p>
        </a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/reports/out_of_stock.php" class="cartao-relatorio">
            <h3>Produtos sem Estoque</h3>
            <p>Itens ativos com saldo zerado e acesso rápido para reposição.</p>
        </a>
        <a href="<?php echo BASE_URL; ?>/reports/inventories.php" class="cartao-relatorio">
            <h3>Inventários</h3>
            <p>Progresso, status e quantidade de divergências por inventário.</p>
        </a>
        <a href="<?php echo BASE_URL; ?>/reports/divergences.php" class="cartao-relatorio">
            <h3>Divergências</h3>
            <p>Comparação histórica entre saldo do sistema e contagem física.</p>
        </a>
        <a href="<?php echo BASE_URL; ?>/reports/low_stock.php" class="cartao-relatorio">
            <h3>Estoque Baixo</h3>
            <p>Produtos que atingiram ou estão abaixo do estoque mínimo definido.</p>
        </a>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * includes/sidebar.php
 * Menu lateral de navegação. Os itens exibidos variam conforme o
 * perfil do usuário logado (administrador ou funcionário).
 */
$paginaAtual = basename($_SERVER['SCRIPT_NAME']);
$pastaAtual = basename(dirname($_SERVER['SCRIPT_NAME']));

function itemAtivo(string $pasta, string $pastaAtual): string
{
    return $pasta === $pastaAtual ? 'ativo' : '';
}
?>
<nav class="sidebar" id="sidebar">
    <ul>
        <li class="<?php echo itemAtivo('dashboard', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/dashboard/index.php">Dashboard</a>
        </li>
        <li class="<?php echo itemAtivo('products', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/products/list.php">Produtos</a>
        </li>
        <li class="<?php echo itemAtivo('categories', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/categories/list.php">Categorias</a>
        </li>
        <li class="<?php echo itemAtivo('suppliers', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/suppliers/list.php">Fornecedores</a>
        </li>
        <li class="<?php echo itemAtivo('stock', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/stock/history.php">Movimentações</a>
        </li>
        <?php if (ehAdministrador()): ?>
        <li class="<?php echo itemAtivo('users', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/users/list.php">Usuários</a>
        </li>
        <?php endif; ?>
        <li class="<?php echo itemAtivo('reports', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/reports/index.php">Relatórios</a>
        </li>
        <?php if (ehAdministrador()): ?>
        <li class="<?php echo itemAtivo('settings', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/settings/index.php">Configurações</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>

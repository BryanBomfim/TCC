<?php
/**
 * includes/sidebar.php
 * Menu lateral principal. Itens administrativos são exibidos apenas
 * para usuários com perfil de administrador.
 */

$pastaAtual = basename(dirname($_SERVER['SCRIPT_NAME']));

function itemAtivo(string $pasta, string $pastaAtual): string
{
    return $pasta === $pastaAtual ? 'ativo' : '';
}
?>
<nav class="sidebar" id="sidebar" aria-label="Navegação principal">
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
        <li class="<?php echo itemAtivo('inventory', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/inventory/index.php">Inventário</a>
        </li>

        <?php if (ehAdministrador()): ?>
            <li class="<?php echo itemAtivo('users', $pastaAtual); ?>">
                <a href="<?php echo BASE_URL; ?>/users/list.php">Usuários</a>
            </li>
            <li class="<?php echo itemAtivo('audit', $pastaAtual); ?>">
                <a href="<?php echo BASE_URL; ?>/audit/index.php">Auditoria</a>
            </li>
        <?php endif; ?>

        <li class="<?php echo itemAtivo('reports', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/reports/index.php">Relatórios</a>
        </li>
        <li class="<?php echo itemAtivo('account', $pastaAtual); ?>">
            <a href="<?php echo BASE_URL; ?>/account/index.php">Minha Conta</a>
        </li>

        <?php if (ehAdministrador()): ?>
            <li class="<?php echo itemAtivo('settings', $pastaAtual); ?>">
                <a href="<?php echo BASE_URL; ?>/settings/index.php">Configurações</a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/settings/api.php">API REST</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>

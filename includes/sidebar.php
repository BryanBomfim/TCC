<?php
/** Navegação lateral e breadcrumb compartilhados. */
$pastaAtual = basename(dirname($_SERVER['SCRIPT_NAME']));
$arquivoAtual = basename($_SERVER['SCRIPT_NAME']);

function itemAtivo(string $pasta, string $pastaAtual): string
{
    return $pasta === $pastaAtual ? 'ativo' : '';
}

function iconeMenu(string $nome): string
{
    $paths = [
        'dashboard' => 'M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-12h8V3h-8v6Z',
        'products' => 'm12 2 9 4.5v11L12 22l-9-4.5v-11L12 2Zm0 2.2L6 7.1l6 3 6-3-6-2.9ZM5 8.7v7.6l6 3v-7.6l-6-3Zm8 10.6 6-3V8.7l-6 3v7.6Z',
        'categories' => 'M3 5a2 2 0 0 1 2-2h6l10 10-8 8L3 11V5Zm4 1.5A1.5 1.5 0 1 0 7 9a1.5 1.5 0 0 0 0-3Z',
        'suppliers' => 'M3 21V7l9-4 9 4v14h-6v-6H9v6H3Zm3-3h2v-3H6v3Zm0-5h2v-3H6v3Zm5 0h2v-3h-2v3Zm5 0h2v-3h-2v3Z',
        'stock' => 'M7 7h11l-3-3 1.4-1.4L22 8l-5.6 5.4L15 12l3-3H7V7Zm10 10H6l3 3-1.4 1.4L2 16l5.6-5.4L9 12l-3 3h11v2Z',
        'inventory' => 'M9 3h6a2 2 0 0 1 2 2h3v16H4V5h3a2 2 0 0 1 2-2Zm0 3h6V5H9v1Zm-2 4v2h10v-2H7Zm0 5v2h7v-2H7Z',
        'users' => 'M16 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM6.5 12A3.5 3.5 0 1 0 6.5 5a3.5 3.5 0 0 0 0 7ZM16 13c-3 0-6 1.5-6 4.5V21h12v-3.5C22 14.5 19 13 16 13ZM6.5 14C3.8 14 1 15.4 1 18v3h7v-3.5c0-1.2.4-2.2 1.1-3-0.8-0.3-1.7-0.5-2.6-0.5Z',
        'audit' => 'M12 2 4 5v6c0 5.1 3.4 9.7 8 11 4.6-1.3 8-5.9 8-11V5l-8-3Zm-1 14-4-4 1.4-1.4L11 13.2l4.6-4.6L17 10l-6 6Z',
        'reports' => 'M4 20V10h4v10H4Zm6 0V4h4v16h-4Zm6 0v-7h4v7h-4Z',
        'account' => 'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-5 0-9 2.5-9 6v2h18v-2c0-3.5-4-6-9-6Z',
        'settings' => 'M19.4 13a7.8 7.8 0 0 0 0-2l2-1.6-2-3.4-2.5 1a8 8 0 0 0-1.7-1L15 3h-4l-.3 3a8 8 0 0 0-1.7 1L6.6 6l-2 3.4L7 11a7.8 7.8 0 0 0 0 2l-2.4 1.6 2 3.4 2.4-1a8 8 0 0 0 1.7 1l.3 3h4l.3-3a8 8 0 0 0 1.7-1l2.4 1 2-3.4-2-1.6ZM13 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Z',
    ];
    $path = $paths[$nome] ?? $paths['reports'];
    return '<svg class="icone-menu" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $path . '"/></svg>';
}

$rotulosPastas = [
    'dashboard' => 'Dashboard', 'products' => 'Produtos', 'categories' => 'Categorias',
    'suppliers' => 'Fornecedores', 'stock' => 'Movimentações', 'inventory' => 'Inventários',
    'users' => 'Usuários', 'audit' => 'Auditoria', 'reports' => 'Relatórios',
    'account' => 'Minha Conta', 'settings' => 'Configurações', 'notifications' => 'Notificações',
];
$rotulosArquivos = [
    'create.php' => 'Novo cadastro', 'edit.php' => 'Edição', 'view.php' => 'Detalhes',
    'entry.php' => 'Entrada', 'exit.php' => 'Saída', 'adjust.php' => 'Ajuste',
    'api_tokens.php' => 'Tokens da API',
];
$rotuloModulo = $rotulosPastas[$pastaAtual] ?? 'SmartControl';
$rotuloPagina = $rotulosArquivos[$arquivoAtual] ?? $rotuloModulo;
?>
<nav class="sidebar" id="sidebar" aria-label="Navegação principal">
    <ul>
        <li class="<?php echo itemAtivo('dashboard', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/dashboard/index.php"><?php echo iconeMenu('dashboard'); ?><span>Dashboard</span></a></li>
        <li class="<?php echo itemAtivo('products', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/products/list.php"><?php echo iconeMenu('products'); ?><span>Produtos</span></a></li>
        <li class="<?php echo itemAtivo('categories', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/categories/list.php"><?php echo iconeMenu('categories'); ?><span>Categorias</span></a></li>
        <li class="<?php echo itemAtivo('suppliers', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/suppliers/list.php"><?php echo iconeMenu('suppliers'); ?><span>Fornecedores</span></a></li>
        <li class="<?php echo itemAtivo('stock', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/stock/history.php"><?php echo iconeMenu('stock'); ?><span>Movimentações</span></a></li>
        <li class="<?php echo itemAtivo('inventory', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/inventory/index.php"><?php echo iconeMenu('inventory'); ?><span>Inventários</span></a></li>
        <?php if (ehAdministrador()): ?>
            <li class="<?php echo itemAtivo('users', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/users/list.php"><?php echo iconeMenu('users'); ?><span>Usuários</span></a></li>
            <li class="<?php echo itemAtivo('audit', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/audit/index.php"><?php echo iconeMenu('audit'); ?><span>Auditoria</span></a></li>
        <?php endif; ?>
        <li class="<?php echo itemAtivo('reports', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/reports/index.php"><?php echo iconeMenu('reports'); ?><span>Relatórios</span></a></li>
        <li class="<?php echo itemAtivo('account', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/account/index.php"><?php echo iconeMenu('account'); ?><span>Minha Conta</span></a></li>
        <?php if (ehAdministrador()): ?>
            <li class="<?php echo itemAtivo('settings', $pastaAtual); ?>"><a href="<?php echo BASE_URL; ?>/settings/index.php"><?php echo iconeMenu('settings'); ?><span>Configurações</span></a></li>
        <?php endif; ?>
    </ul>
</nav>

<div class="area-principal">
    <nav class="breadcrumbs" aria-label="Você está em">
        <a href="<?php echo BASE_URL; ?>/dashboard/index.php">Início</a>
        <span aria-hidden="true">/</span>
        <?php if ($rotuloPagina !== $rotuloModulo): ?>
            <span><?php echo limpar($rotuloModulo); ?></span>
            <span aria-hidden="true">/</span>
        <?php endif; ?>
        <span aria-current="page"><?php echo limpar($rotuloPagina); ?></span>
    </nav>

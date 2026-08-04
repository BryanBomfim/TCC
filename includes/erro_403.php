<?php
/**
 * includes/erro_403.php
 * Página exibida quando um usuário sem permissão tenta acessar
 * uma área restrita (ex.: funcionário tentando acessar Configurações).
 */
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<div class="conteudo">
    <div class="caixa-erro">
        <h1>403</h1>
        <h2>Acesso negado</h2>
        <p>Você não possui permissão para acessar esta área do sistema.</p>
        <a href="<?php echo BASE_URL; ?>/dashboard/index.php" class="botao">Voltar ao Dashboard</a>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>

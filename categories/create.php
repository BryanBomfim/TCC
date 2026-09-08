<?php
/**
 * categories/create.php
 * Formulário e processamento de cadastro de nova categoria.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$nome = '';
$descricao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        if ($nome === '') {
            $erro = 'O nome da categoria é obrigatório.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO categorias (nome, descricao, ativo) VALUES (:nome, :descricao, 1)");
            $stmt->execute([':nome' => $nome, ':descricao' => $descricao ?: null]);

            definirMensagem('sucesso', 'Categoria cadastrada com sucesso!');
            header('Location: ' . BASE_URL . '/categories/list.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Nova Categoria</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?php echo limpar($nome); ?>">

        <label for="descricao">Descrição</label>
        <textarea name="descricao" id="descricao" rows="3"><?php echo limpar($descricao); ?></textarea>

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar</button>
            <a href="<?php echo BASE_URL; ?>/categories/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * categories/edit.php
 * Formulário e processamento de edição de categoria existente.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = :id");
$stmt->execute([':id' => $id]);
$categoria = $stmt->fetch();

if (!$categoria) {
    definirMensagem('erro', 'Categoria não encontrada.');
    header('Location: ' . BASE_URL . '/categories/list.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        if ($nome === '' || mb_strlen($nome) > 100 || mb_strlen($descricao) > 255) {
            $erro = 'Informe nome (até 100 caracteres) e descrição (até 255 caracteres).';
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE categorias SET nome = :nome, descricao = :descricao WHERE id = :id");
            $stmtUpdate->execute([
                ':nome' => $nome,
                ':descricao' => $descricao ?: null,
                ':id' => $id
            ]);

            registrarAuditoria('EDITAR', 'Categorias', $id, 'Categoria atualizada.', null, ['nome' => $nome, 'descricao' => $descricao]);
            definirMensagem('sucesso', 'Categoria atualizada com sucesso!');
            header('Location: ' . BASE_URL . '/categories/list.php');
            exit;
        }
        $categoria['nome'] = $nome;
        $categoria['descricao'] = $descricao;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Editar Categoria</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?php echo limpar($categoria['nome']); ?>">

        <label for="descricao">Descrição</label>
        <textarea name="descricao" id="descricao" rows="3"><?php echo limpar($categoria['descricao'] ?? ''); ?></textarea>

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar Alterações</button>
            <a href="<?php echo BASE_URL; ?>/categories/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

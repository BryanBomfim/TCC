<?php
/**
 * suppliers/edit.php
 * Formulário e processamento de edição de fornecedor existente.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id = :id");
$stmt->execute([':id' => $id]);
$fornecedor = $stmt->fetch();

if (!$fornecedor) {
    definirMensagem('erro', 'Fornecedor não encontrado.');
    header('Location: ' . BASE_URL . '/suppliers/list.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        foreach (['nome', 'cnpj_cpf', 'telefone', 'email', 'endereco'] as $campo) {
            $fornecedor[$campo] = trim($_POST[$campo] ?? '');
        }

        if ($fornecedor['nome'] === '') {
            $erro = 'O nome do fornecedor é obrigatório.';
        } else {
            $stmtUpdate = $pdo->prepare("
                UPDATE fornecedores
                SET nome = :nome, cnpj_cpf = :cnpj_cpf, telefone = :telefone, email = :email, endereco = :endereco
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':nome' => $fornecedor['nome'],
                ':cnpj_cpf' => $fornecedor['cnpj_cpf'] ?: null,
                ':telefone' => $fornecedor['telefone'] ?: null,
                ':email' => $fornecedor['email'] ?: null,
                ':endereco' => $fornecedor['endereco'] ?: null,
                ':id' => $id
            ]);

            definirMensagem('sucesso', 'Fornecedor atualizado com sucesso!');
            header('Location: ' . BASE_URL . '/suppliers/list.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo">
    <h1>Editar Fornecedor</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?php echo limpar($fornecedor['nome']); ?>">

        <label for="cnpj_cpf">CNPJ/CPF</label>
        <input type="text" name="cnpj_cpf" id="cnpj_cpf" value="<?php echo limpar($fornecedor['cnpj_cpf'] ?? ''); ?>">

        <label for="telefone">Telefone</label>
        <input type="text" name="telefone" id="telefone" value="<?php echo limpar($fornecedor['telefone'] ?? ''); ?>">

        <label for="email">E-mail</label>
        <input type="email" name="email" id="email" value="<?php echo limpar($fornecedor['email'] ?? ''); ?>">

        <label for="endereco">Endereço</label>
        <input type="text" name="endereco" id="endereco" value="<?php echo limpar($fornecedor['endereco'] ?? ''); ?>">

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar Alterações</button>
            <a href="<?php echo BASE_URL; ?>/suppliers/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

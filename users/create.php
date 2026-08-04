<?php
/**
 * users/create.php
 * Formulário e processamento de cadastro de novo usuário.
 * Acesso restrito ao administrador.
 */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$erro = '';
$nome = '';
$email = '';
$perfil = 'funcionario';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'] ?? 'funcionario';

        if ($nome === '' || $email === '' || $senha === '') {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha deve ter no mínimo 6 caracteres.';
        } elseif (!in_array($perfil, ['administrador', 'funcionario'], true)) {
            $erro = 'Perfil inválido.';
        } else {
            $stmtVerifica = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmtVerifica->execute([':email' => $email]);

            if ($stmtVerifica->fetch()) {
                $erro = 'Já existe um usuário cadastrado com este e-mail.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nome, email, senha, perfil, ativo)
                    VALUES (:nome, :email, :senha, :perfil, 1)
                ");
                $stmt->execute([
                    ':nome' => $nome,
                    ':email' => $email,
                    ':senha' => $hash,
                    ':perfil' => $perfil,
                ]);

                definirMensagem('sucesso', 'Usuário cadastrado com sucesso!');
                header('Location: ' . BASE_URL . '/users/list.php');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo">
    <h1>Novo Usuário</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?php echo limpar($nome); ?>">

        <label for="email">E-mail *</label>
        <input type="email" name="email" id="email" required value="<?php echo limpar($email); ?>">

        <label for="senha">Senha *</label>
        <input type="password" name="senha" id="senha" required minlength="6">

        <label for="perfil">Perfil *</label>
        <select name="perfil" id="perfil" required>
            <option value="funcionario" <?php echo $perfil === 'funcionario' ? 'selected' : ''; ?>>Funcionário</option>
            <option value="administrador" <?php echo $perfil === 'administrador' ? 'selected' : ''; ?>>Administrador</option>
        </select>

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar</button>
            <a href="<?php echo BASE_URL; ?>/users/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

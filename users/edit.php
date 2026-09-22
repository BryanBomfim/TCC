<?php
/**
 * users/edit.php
 * Formulário e processamento de edição de usuário existente.
 * A senha só é alterada se um novo valor for informado.
 * Acesso restrito ao administrador.
 */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    definirMensagem('erro', 'Usuário não encontrado.');
    header('Location: ' . BASE_URL . '/users/list.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'] ?? 'funcionario';

        if ($nome === '' || $email === '') {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif ($senha !== '' && strlen($senha) < 8) {
            $erro = 'A nova senha deve ter no mínimo 8 caracteres.';
        } elseif (!in_array($perfil, ['administrador', 'funcionario'], true)) {
            $erro = 'Perfil inválido.';
        } elseif ($id === (int) $_SESSION['usuario_id'] && $perfil !== $usuario['perfil']) {
            $erro = 'Altere o perfil da sua própria conta usando outro administrador.';
        } elseif ($usuario['perfil'] === 'administrador' && $perfil !== 'administrador') {
            $totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'administrador' AND ativo = 1")->fetchColumn();
            if ($totalAdmins <= 1 && (int) $usuario['ativo'] === 1) {
                $erro = 'O sistema precisa manter pelo menos um administrador ativo.';
            }
        }

        if ($erro === '') {
            $stmtVerifica = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
            $stmtVerifica->execute([':email' => $email, ':id' => $id]);

            if ($stmtVerifica->fetch()) {
                $erro = 'Já existe outro usuário cadastrado com este e-mail.';
            } else {
                $dadosAnteriores = ['nome' => $usuario['nome'], 'email' => $usuario['email'], 'perfil' => $usuario['perfil'], 'ativo' => (int) $usuario['ativo']];
                if ($senha !== '') {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmtUpdate = $pdo->prepare("
                        UPDATE usuarios SET nome = :nome, email = :email, senha = :senha, perfil = :perfil WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':nome' => $nome, ':email' => $email, ':senha' => $hash, ':perfil' => $perfil, ':id' => $id
                    ]);
                } else {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE usuarios SET nome = :nome, email = :email, perfil = :perfil WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':nome' => $nome, ':email' => $email, ':perfil' => $perfil, ':id' => $id
                    ]);
                }

                registrarAuditoria('EDITAR', 'Usuários', $id, 'Usuário atualizado: ' . $nome . '.', $dadosAnteriores, ['nome' => $nome, 'email' => strtolower($email), 'perfil' => $perfil, 'ativo' => (int) $usuario['ativo'], 'senha_alterada' => $senha !== '']);

                if ($id === (int) $_SESSION['usuario_id']) {
                    $_SESSION['usuario_nome'] = $nome;
                    $_SESSION['usuario_perfil'] = $perfil;
                }

                definirMensagem('sucesso', 'Usuário atualizado com sucesso!');
                header('Location: ' . BASE_URL . '/users/list.php');
                exit;
            }
        }
        $usuario['nome'] = $nome;
        $usuario['email'] = $email;
        $usuario['perfil'] = $perfil;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <h1>Editar Usuário</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?php echo limpar($usuario['nome']); ?>">

        <label for="email">E-mail *</label>
        <input type="email" name="email" id="email" required value="<?php echo limpar($usuario['email']); ?>">

        <label for="senha">Nova Senha</label>
        <input type="password" name="senha" id="senha" minlength="8" placeholder="Deixe em branco para manter a senha atual">

        <label for="perfil">Perfil *</label>
        <select name="perfil" id="perfil" required>
            <option value="funcionario" <?php echo $usuario['perfil'] === 'funcionario' ? 'selected' : ''; ?>>Funcionário</option>
            <option value="administrador" <?php echo $usuario['perfil'] === 'administrador' ? 'selected' : ''; ?>>Administrador</option>
        </select>

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar Alterações</button>
            <a href="<?php echo BASE_URL; ?>/users/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

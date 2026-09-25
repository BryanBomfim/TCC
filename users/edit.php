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

        try {
            (new \SmartControl\Services\UserService($pdo))->editar(
                (int) $_SESSION['usuario_id'], $id, $nome, $email, $senha, $perfil
            );
            definirMensagem('sucesso', 'Usuário atualizado com sucesso!');
            header('Location: ' . BASE_URL . '/users/list.php');
            exit;
        } catch (DomainException $e) {
            $erro = $e->getMessage();
        } catch (PDOException $e) {
            error_log('Falha ao editar usuário: ' . $e->getCode());
            $erro = $e->getCode() === '23000' ? 'E-mail já cadastrado ou dados em conflito.' : 'Não foi possível atualizar o usuário.';
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
        <input type="password" name="senha" id="senha" minlength="10" maxlength="72"
            autocomplete="new-password" placeholder="Deixe em branco para manter a senha atual">

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

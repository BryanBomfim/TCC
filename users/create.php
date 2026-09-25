<?php
/**
 * Cadastro administrativo de usuários.
 */

use SmartControl\Services\EmailVerificationService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email.php';

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
        $email = strtolower(trim($_POST['email'] ?? ''));
        $senha = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'] ?? 'funcionario';

        if ($nome === '' || $email === '' || $senha === '') {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif (mb_strlen($nome) > 100) {
            $erro = 'O nome deve possuir no máximo 100 caracteres.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $erro = 'Informe um e-mail válido.';
        } elseif (($erroSenha = validarNovaSenha($senha)) !== null) {
            $erro = $erroSenha;
        } elseif (!in_array($perfil, ['administrador', 'funcionario'], true)) {
            $erro = 'Perfil inválido.';
        } else {
            $stmtVerifica = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
            $stmtVerifica->execute([':email' => $email]);

            if ($stmtVerifica->fetchColumn()) {
                $erro = 'Já existe um usuário cadastrado com este e-mail.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios
                            (nome, email, email_verificado_em, senha, perfil, ativo)
                        VALUES
                            (:nome, :email, NULL, :senha, :perfil, 1)
                    ");
                    $stmt->execute([
                        ':nome' => $nome,
                        ':email' => $email,
                        ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                        ':perfil' => $perfil,
                    ]);

                    $usuarioId = (int) $pdo->lastInsertId();
                    $servicoVerificacao = new EmailVerificationService($pdo, 'enviarEmail');

                    if (!$servicoVerificacao->enviar($usuarioId, $nome, $email)) {
                        $pdo->rollBack();
                        $erro = 'Não foi possível enviar o e-mail de verificação. '
                            . 'O usuário não foi cadastrado. Tente novamente.';
                    } else {
                        registrarAuditoria(
                            'CRIAR',
                            'Usuários',
                            $usuarioId,
                            'Usuário criado com verificação de e-mail pendente.',
                            null,
                            ['nome' => $nome, 'email' => $email, 'perfil' => $perfil]
                        );

                        $pdo->commit();
                        definirMensagem(
                            'sucesso',
                            'Usuário cadastrado! Um e-mail de verificação foi enviado.'
                        );
                        header('Location: ' . BASE_URL . '/users/list.php');
                        exit;
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Erro ao cadastrar usuário: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
                    $erro = 'Não foi possível cadastrar o usuário. Tente novamente.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Novo Usuário</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required maxlength="100"
            autocomplete="name" value="<?php echo limpar($nome); ?>">

        <label for="email">E-mail *</label>
        <input type="email" name="email" id="email" required maxlength="150"
            autocomplete="email" value="<?php echo limpar($email); ?>">

        <label for="senha">Senha *</label>
        <input type="password" name="senha" id="senha" required minlength="10" maxlength="72"
            autocomplete="new-password" aria-describedby="ajuda-senha">
        <small id="ajuda-senha">Use de 10 a 72 caracteres.</small>

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

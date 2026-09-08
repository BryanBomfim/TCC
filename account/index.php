<?php
/**
 * account/index.php
 * Área "Minha Conta".
 * Permite que o usuário autenticado altere seus próprios dados e senha.
 */

require_once __DIR__ . '/../config/config.php';
exigirLogin();

$usuarioId = (int) $_SESSION['usuario_id'];
$erro = '';

$stmt = $pdo->prepare("
    SELECT id, nome, email, senha, perfil
    FROM usuarios
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $usuarioId]);
$usuario = $stmt->fetch();

if (!$usuario) {
    session_destroy();
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';

    } else {

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        if ($nome === '' || $email === '') {

            $erro = 'Preencha nome e e-mail.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $erro = 'Informe um e-mail válido.';

        } else {

            // Verifica se o e-mail já pertence a outro usuário
            $stmtEmail = $pdo->prepare("
                SELECT id
                FROM usuarios
                WHERE email = :email
                AND id != :id
                LIMIT 1
            ");

            $stmtEmail->execute([
                ':email' => $email,
                ':id' => $usuarioId
            ]);

            if ($stmtEmail->fetch()) {

                $erro = 'Este e-mail já está sendo utilizado por outro usuário.';

            } else {

                $alterandoSenha = (
                    $senhaAtual !== '' ||
                    $novaSenha !== '' ||
                    $confirmarSenha !== ''
                );

                if ($alterandoSenha) {

                    if ($senhaAtual === '') {

                        $erro = 'Digite sua senha atual.';

                    } elseif (!password_verify($senhaAtual, $usuario['senha'])) {

                        $erro = 'A senha atual está incorreta.';

                    } elseif (strlen($novaSenha) < 6) {

                        $erro = 'A nova senha deve ter no mínimo 6 caracteres.';

                    } elseif ($novaSenha !== $confirmarSenha) {

                        $erro = 'A confirmação da nova senha não confere.';

                    }
                }

                if ($erro === '') {

                    if ($alterandoSenha) {

                        $novoHash = password_hash(
                            $novaSenha,
                            PASSWORD_DEFAULT
                        );

                        $stmtUpdate = $pdo->prepare("
                            UPDATE usuarios
                            SET nome = :nome,
                                email = :email,
                                senha = :senha
                            WHERE id = :id
                        ");

                        $stmtUpdate->execute([
                            ':nome' => $nome,
                            ':email' => $email,
                            ':senha' => $novoHash,
                            ':id' => $usuarioId
                        ]);

                    } else {

                        $stmtUpdate = $pdo->prepare("
                            UPDATE usuarios
                            SET nome = :nome,
                                email = :email
                            WHERE id = :id
                        ");

                        $stmtUpdate->execute([
                            ':nome' => $nome,
                            ':email' => $email,
                            ':id' => $usuarioId
                        ]);
                    }

                    // Atualiza o nome mostrado no cabeçalho
                    $_SESSION['usuario_nome'] = $nome;

                    definirMensagem(
                        'sucesso',
                        'Sua conta foi atualizada com sucesso!'
                    );

                    header(
                        'Location: ' . BASE_URL . '/account/index.php'
                    );
                    exit;
                }
            }
        }

        $usuario['nome'] = $nome;
        $usuario['email'] = $email;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="conteudo" id="conteudo-principal" tabindex="-1">

    <h1>Minha Conta</h1>

    <p>
        Atualize seus dados pessoais ou altere sua senha.
    </p>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro" role="alert">
            <?php echo limpar($erro); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">

        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">
            Nome *
        </label>

        <input type="text" name="nome" id="nome" required value="<?php echo limpar($usuario['nome']); ?>">

        <label for="email">
            E-mail *
        </label>

        <input type="email" name="email" id="email" required autocomplete="email"
            value="<?php echo limpar($usuario['email']); ?>">

        <hr>

        <h2>Alterar senha</h2>

        <p>
            Se não quiser alterar sua senha, deixe os campos abaixo em branco.
        </p>

        <label for="senha_atual">
            Senha atual
        </label>

        <div class="campo-senha">

            <input type="password" name="senha_atual" id="senha_atual" autocomplete="current-password">

            <button type="button" class="botao-mostrar-senha" data-target="senha_atual" aria-controls="senha_atual"
                aria-pressed="false">
                Mostrar
            </button>

        </div>

        <label for="nova_senha">
            Nova senha
        </label>

        <div class="campo-senha">

            <input type="password" name="nova_senha" id="nova_senha" minlength="6" autocomplete="new-password">

            <button type="button" class="botao-mostrar-senha" data-target="nova_senha" aria-controls="nova_senha"
                aria-pressed="false">
                Mostrar
            </button>

        </div>

        <label for="confirmar_senha">
            Confirmar nova senha
        </label>

        <div class="campo-senha">

            <input type="password" name="confirmar_senha" id="confirmar_senha" minlength="6"
                autocomplete="new-password">

            <button type="button" class="botao-mostrar-senha" data-target="confirmar_senha"
                aria-controls="confirmar_senha" aria-pressed="false">
                Mostrar
            </button>

        </div>
        <div class="acoes-formulario">

            <button type="submit" class="botao">
                Salvar Alterações
            </button>

            <a href="<?php echo BASE_URL; ?>/dashboard/index.php" class="botao botao-secundario">
                Cancelar
            </a>

        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

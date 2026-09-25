<?php
/**
 * account/index.php
 * Área "Minha Conta".
 * Permite que o usuário autenticado altere seus próprios dados e senha.
 */

use SmartControl\Services\EmailVerificationService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email.php';
exigirLogin();

$usuarioId = (int) $_SESSION['usuario_id'];
$erro = '';

$stmt = $pdo->prepare("
    SELECT id, nome, email, email_verificado_em, senha, perfil, auth_version
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
        $email = strtolower(trim($_POST['email'] ?? ''));
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        $emailAlterado = strcasecmp($email, (string) $usuario['email']) !== 0;
        $alterandoSenha = $novaSenha !== '' || $confirmarSenha !== '';
        $operacaoSensivel = $emailAlterado || $alterandoSenha;

        if ($nome === '' || $email === '') {
            $erro = 'Preencha nome e e-mail.';
        } elseif (mb_strlen($nome) > 100) {
            $erro = 'O nome deve possuir no máximo 100 caracteres.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif ($operacaoSensivel && $senhaAtual === '') {
            $erro = 'Confirme sua senha atual para alterar e-mail ou senha.';
        } elseif ($operacaoSensivel && !password_verify($senhaAtual, $usuario['senha'])) {
            $erro = 'A senha atual está incorreta.';
        } elseif ($alterandoSenha && ($erroSenha = validarNovaSenha($novaSenha)) !== null) {
            $erro = $erroSenha;
        } elseif ($alterandoSenha && $novaSenha !== $confirmarSenha) {
            $erro = 'A confirmação da nova senha não confere.';
        }

        if ($erro === '') {
            $stmtEmail = $pdo->prepare("
                SELECT id
                FROM usuarios
                WHERE email = :email
                  AND id <> :id
                LIMIT 1
            ");
            $stmtEmail->execute([
                ':email' => $email,
                ':id' => $usuarioId,
            ]);

            if ($stmtEmail->fetch()) {
                $erro = 'Este e-mail já está sendo utilizado por outro usuário.';
            }
        }

        if ($erro === '') {
            try {
                $pdo->beginTransaction();

                $campos = ['nome = :nome'];
                $parametros = [
                    ':nome' => $nome,
                    ':id' => $usuarioId,
                ];

                if ($emailAlterado) {
                    $campos[] = 'email = :email';
                    $campos[] = 'email_verificado_em = NULL';
                    $parametros[':email'] = $email;
                }

                if ($alterandoSenha) {
                    $campos[] = 'senha = :senha';
                    $parametros[':senha'] = password_hash($novaSenha, PASSWORD_DEFAULT);
                }

                $stmtUpdate = $pdo->prepare(
                    'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = :id'
                );
                $stmtUpdate->execute($parametros);

                if ($operacaoSensivel) {
                    revogarCredenciaisUsuario($usuarioId);
                }

                if ($emailAlterado) {
                    $servicoVerificacao = new EmailVerificationService($pdo, 'enviarEmail');

                    if (!$servicoVerificacao->enviar($usuarioId, $nome, $email)) {
                        throw new DomainException(
                            'Não foi possível enviar a confirmação. O e-mail não foi alterado.'
                        );
                    }
                }

                registrarAuditoria(
                    'EDITAR',
                    'Minha Conta',
                    $usuarioId,
                    'Dados da própria conta atualizados.',
                    [
                        'nome' => $usuario['nome'],
                        'email' => $usuario['email'],
                    ],
                    [
                        'nome' => $nome,
                        'email' => $email,
                        'senha_alterada' => $alterandoSenha,
                    ]
                );

                $pdo->commit();

                if ($emailAlterado) {
                    $_SESSION = [];
                    session_regenerate_id(true);
                    $_SESSION['mensagem'] = [
                        'tipo' => 'sucesso',
                        'texto' => 'E-mail alterado. Confirme o novo endereço antes de entrar novamente.',
                    ];
                    header('Location: ' . BASE_URL . '/auth/login.php');
                    exit;
                }

                $_SESSION['usuario_nome'] = $nome;

                if ($alterandoSenha) {
                    $_SESSION['troca_senha_obrigatoria'] = false;
                    $_SESSION['auth_assinatura'] = assinaturaAutenticacao(array_merge($usuario, ['senha' => $parametros[':senha']]));
                    session_regenerate_id(true);
                }

                definirMensagem('sucesso', 'Sua conta foi atualizada com sucesso!');
                header('Location: ' . BASE_URL . '/account/index.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Erro ao atualizar a própria conta: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
                $erro = $e instanceof DomainException
                    ? $e->getMessage()
                    : 'Não foi possível atualizar sua conta. Tente novamente.';
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
            Para alterar e-mail ou senha, confirme sua senha atual. Um novo e-mail deverá ser verificado.
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

            <input type="password" name="nova_senha" id="nova_senha" minlength="10" maxlength="72" autocomplete="new-password">

            <button type="button" class="botao-mostrar-senha" data-target="nova_senha" aria-controls="nova_senha"
                aria-pressed="false">
                Mostrar
            </button>

        </div>

        <label for="confirmar_senha">
            Confirmar nova senha
        </label>

        <div class="campo-senha">

            <input type="password" name="confirmar_senha" id="confirmar_senha" minlength="10" maxlength="72"
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

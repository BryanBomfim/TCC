<?php
/**
 * auth/redefinir_senha.php
 *
 * Valida o token de recuperação e permite ao usuário
 * definir uma nova senha.
 */

require_once __DIR__ . '/../config/config.php';

$erro = '';
$sucesso = false;

// O token pode vir pela URL (GET) ou pelo formulário (POST)
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

/**
 * Verifica se o token possui o formato esperado:
 * 64 caracteres hexadecimais.
 */
$tokenFormatoValido = (
    $token !== '' &&
    preg_match('/^[a-f0-9]{64}$/i', $token)
);

$recuperacao = null;

if ($tokenFormatoValido) {

    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.usuario_id,
            r.expira_em,
            u.nome,
            u.email
        FROM recuperacoes_senha r
        INNER JOIN usuarios u
            ON u.id = r.usuario_id
        WHERE r.token_hash = :token_hash
          AND r.utilizado_em IS NULL
          AND r.expira_em >= NOW()
          AND u.ativo = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':token_hash' => $tokenHash
    ]);

    $recuperacao = $stmt->fetch();
}

/**
 * Processamento da nova senha
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';

    } elseif (!$tokenFormatoValido || !$recuperacao) {

        $erro = 'Este link de recuperação é inválido ou expirou.';

    } else {

        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        if ($novaSenha === '' || $confirmarSenha === '') {

            $erro = 'Preencha os dois campos de senha.';

        } elseif (strlen($novaSenha) < 8) {

            $erro = 'A nova senha deve ter no mínimo 8 caracteres.';

        } elseif ($novaSenha !== $confirmarSenha) {

            $erro = 'As senhas informadas não são iguais.';

        } else {

            try {

                $pdo->beginTransaction();

                /*
                 * Verifica novamente o token durante a transação.
                 * Isso evita que um token seja usado duas vezes
                 * simultaneamente.
                 */
                $stmtVerificar = $pdo->prepare("
                    SELECT
                        id,
                        usuario_id
                    FROM recuperacoes_senha
                    WHERE token_hash = :token_hash
                      AND utilizado_em IS NULL
                      AND expira_em >= NOW()
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmtVerificar->execute([
                    ':token_hash' => $tokenHash
                ]);

                $tokenAtual = $stmtVerificar->fetch();

                if (!$tokenAtual) {

                    $pdo->rollBack();

                    $erro = 'Este link de recuperação é inválido ou expirou.';

                } else {

                    $usuarioId = (int) $tokenAtual['usuario_id'];

                    // Gera o hash seguro da nova senha
                    $senhaHash = password_hash(
                        $novaSenha,
                        PASSWORD_DEFAULT
                    );

                    // Atualiza a senha do usuário
                    $stmtSenha = $pdo->prepare("
                        UPDATE usuarios
                        SET senha = :senha
                        WHERE id = :usuario_id
                    ");

                    $stmtSenha->execute([
                        ':senha' => $senhaHash,
                        ':usuario_id' => $usuarioId
                    ]);

                    /*
                     * Invalida todos os links de recuperação
                     * que ainda estiverem ativos para esse usuário.
                     */
                    $stmtInvalidar = $pdo->prepare("
                        UPDATE recuperacoes_senha
                        SET utilizado_em = NOW()
                        WHERE usuario_id = :usuario_id
                          AND utilizado_em IS NULL
                    ");

                    $stmtInvalidar->execute([
                        ':usuario_id' => $usuarioId
                    ]);
                    /*
                     * Remove bloqueios de login após a recuperação da senha.
                     * Assim o usuário consegue entrar imediatamente com a nova senha.
                     */
                    $stmtDesbloquear = $pdo->prepare("
    DELETE FROM tentativas_login
    WHERE email = :email
      AND sucesso = 0
");

                    $stmtDesbloquear->execute([
                        ':email' => strtolower($recuperacao['email'])
                    ]);
                    $pdo->commit();

                    // Gera um novo token CSRF para próximos formulários
                    unset($_SESSION['csrf_token']);

                    $sucesso = true;
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $erro = 'Não foi possível redefinir a senha. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">

    <title>Redefinir Senha - SmartControl</title>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/accessibility.css">
</head>

<body class="pagina-login">
    <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>
    <?php require __DIR__ . '/../includes/accessibility.php'; ?>

    <main class="caixa-login" id="conteudo-principal" tabindex="-1">

        <?php if ($sucesso): ?>

            <h1>Senha redefinida</h1>

            <div class="alerta alerta-sucesso" role="status" aria-live="polite">
                Sua senha foi alterada com sucesso.
            </div>

            <p class="link-recuperacao">

                <a href="<?php echo BASE_URL; ?>/auth/login.php">
                    Entrar no SmartControl
                </a>

            </p>

        <?php elseif (!$tokenFormatoValido || !$recuperacao): ?>

            <h1>Link inválido</h1>

            <div class="alerta alerta-erro" role="alert">
                Este link de recuperação é inválido ou expirou.
            </div>

            <p class="link-recuperacao">

                <a href="<?php echo BASE_URL; ?>/auth/esqueci_senha.php">
                    Solicitar um novo link
                </a>

            </p>

            <p class="link-recuperacao">

                <a href="<?php echo BASE_URL; ?>/auth/login.php">
                    ← Voltar para o login
                </a>

            </p>

        <?php else: ?>

            <h1>Nova senha</h1>

            <p class="subtitulo">
                Crie uma nova senha para sua conta.
            </p>

            <?php if ($erro): ?>

                <div class="alerta alerta-erro" role="alert">
                    <?php echo limpar($erro); ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="">

                <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

                <input type="hidden" name="token" value="<?php echo limpar($token); ?>">

                <label for="nova_senha">
                    Nova senha
                </label>

                <div class="campo-senha">

                    <input type="password" name="nova_senha" id="nova_senha" minlength="8" required
                        autocomplete="new-password">

                    <button type="button" class="botao-mostrar-senha" data-target="nova_senha" aria-controls="nova_senha"
                        aria-pressed="false">
                        Mostrar
                    </button>

                </div>

                <label for="confirmar_senha">
                    Confirmar nova senha
                </label>

                <div class="campo-senha">

                    <input type="password" name="confirmar_senha" id="confirmar_senha" minlength="8" required
                        autocomplete="new-password">

                    <button type="button" class="botao-mostrar-senha" data-target="confirmar_senha"
                        aria-controls="confirmar_senha" aria-pressed="false">
                        Mostrar
                    </button>

                </div>

                <button type="submit" class="botao botao-largo">
                    Redefinir senha
                </button>

            </form>

            <p class="link-recuperacao">

                <a href="<?php echo BASE_URL; ?>/auth/login.php">
                    ← Voltar para o login
                </a>

            </p>

        <?php endif; ?>

    </main>

    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>

    <script src="<?php echo BASE_URL; ?>/assets/js/accessibility.js"></script>
</body>

</html>

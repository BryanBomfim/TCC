<?php
/**
 * auth/esqueci_senha.php
 *
 * Solicita a recuperação da senha.
 * O token original nunca é salvo no banco.
 * Apenas o hash SHA-256 é armazenado.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';

    } else {

        $email = trim($_POST['email'] ?? '');

        if ($email === '') {

            $erro = 'Informe seu e-mail.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $erro = 'Informe um e-mail válido.';

        } else {

            $stmt = $pdo->prepare("
    SELECT
        id,
        nome,
        email
    FROM usuarios
    WHERE email = :email
      AND ativo = 1
    LIMIT 1
");

            $stmt->execute([
                ':email' => $email
            ]);

            $usuario = $stmt->fetch();

            /*
             * A mensagem será a mesma existindo ou não o e-mail.
             * Isso evita revelar quais e-mails estão cadastrados.
             */
            $sucesso = 'Se o e-mail informado estiver cadastrado, uma solicitação de recuperação será criada.';

            if ($usuario) {

                $usuarioId = (int) $usuario['id'];

                // Invalida recuperações anteriores ainda não utilizadas
                $stmtInvalidar = $pdo->prepare("
                    UPDATE recuperacoes_senha
                    SET utilizado_em = NOW()
                    WHERE usuario_id = :usuario_id
                      AND utilizado_em IS NULL
                ");

                $stmtInvalidar->execute([
                    ':usuario_id' => $usuarioId
                ]);

                // Token aleatório de 32 bytes = 64 caracteres hexadecimal
                $token = bin2hex(random_bytes(32));

                // Somente o hash será salvo no banco
                $tokenHash = hash('sha256', $token);

                // Link válido por 30 minutos
                $expiraEm = date(
                    'Y-m-d H:i:s',
                    strtotime('+30 minutes')
                );

                $stmtToken = $pdo->prepare("
                    INSERT INTO recuperacoes_senha
                    (
                        usuario_id,
                        token_hash,
                        expira_em
                    )
                    VALUES
                    (
                        :usuario_id,
                        :token_hash,
                        :expira_em
                    )
                ");

                $stmtToken->execute([
                    ':usuario_id' => $usuarioId,
                    ':token_hash' => $tokenHash,
                    ':expira_em' => $expiraEm
                ]);

                /*
                 * Monta o link real de recuperação.
                 */
                $linkRecuperacao =
                    APP_URL .
                    '/auth/redefinir_senha.php?token=' .
                    urlencode($token);

                /*
                 * Dados do usuário que receberá o e-mail.
                 */
                $nomeUsuario = $usuario['nome'];
                $emailUsuario = $usuario['email'];

                /*
                 * Monta o conteúdo do e-mail.
                 */
                $assunto = 'Redefinição de senha - SmartControl';

                $conteudoHtml = '
    <div style="
        font-family: Arial, sans-serif;
        max-width: 600px;
        margin: 0 auto;
        color: #333;
    ">

        <h2 style="color: #1a4f8b;">
            SmartControl
        </h2>

        <p>
            Olá, ' . htmlspecialchars(
                    $nomeUsuario,
                    ENT_QUOTES,
                    'UTF-8'
                ) . '.
        </p>

        <p>
            Recebemos uma solicitação para redefinir
            a senha da sua conta.
        </p>

        <p>
            Clique no botão abaixo para criar uma nova senha:
        </p>

        <p style="margin: 30px 0;">

            <a
                href="' . htmlspecialchars(
                    $linkRecuperacao,
                    ENT_QUOTES,
                    'UTF-8'
                ) . '"
                style="
                    background: #1a4f8b;
                    color: #ffffff;
                    padding: 12px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    display: inline-block;
                "
            >
                Redefinir minha senha
            </a>

        </p>

        <p>
            Este link é válido por
            <strong>30 minutos</strong>
            e poderá ser usado apenas uma vez.
        </p>

        <p>
            Se você não solicitou a alteração da senha,
            ignore esta mensagem.
        </p>

        <hr style="border: 0; border-top: 1px solid #ddd;">

        <p style="font-size: 12px; color: #777;">
            Esta é uma mensagem automática do SmartControl.
        </p>

    </div>
';

                $conteudoTexto =
                    "SmartControl\n\n" .
                    "Olá, " . $nomeUsuario . ".\n\n" .
                    "Recebemos uma solicitação para redefinir sua senha.\n\n" .
                    "Acesse o link abaixo:\n" .
                    $linkRecuperacao . "\n\n" .
                    "O link é válido por 30 minutos e pode ser usado apenas uma vez.\n\n" .
                    "Se você não fez essa solicitação, ignore esta mensagem.";

                /*
                 * Envia efetivamente o e-mail.
                 */
                $emailEnviado = enviarEmail(
                    $emailUsuario,
                    $nomeUsuario,
                    $assunto,
                    $conteudoHtml,
                    $conteudoTexto
                );

                /*
                 * Se o envio falhar, removemos o token que acabou
                 * de ser criado, pois ninguém recebeu o link.
                 */
                if (!$emailEnviado) {

                    $stmtRemoverToken = $pdo->prepare("
        DELETE FROM recuperacoes_senha
        WHERE token_hash = :token_hash
    ");

                    $stmtRemoverToken->execute([
                        ':token_hash' => $tokenHash
                    ]);
                }
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

    <title>Recuperar Senha - SmartControl</title>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/accessibility.css">
</head>

<body class="pagina-login">
    <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>
    <?php require __DIR__ . '/../includes/accessibility.php'; ?>

    <main class="caixa-login" id="conteudo-principal" tabindex="-1">

        <h1>Recuperar senha</h1>

        <p class="subtitulo">
            Informe o e-mail associado à sua conta.
        </p>

        <?php if ($erro): ?>

            <div class="alerta alerta-erro" role="alert">
                <?php echo limpar($erro); ?>
            </div>

        <?php endif; ?>

        <?php if ($sucesso): ?>

            <div class="alerta alerta-sucesso" role="status" aria-live="polite">
                <?php echo limpar($sucesso); ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

            <label for="email">
                E-mail
            </label>

            <input type="email" name="email" id="email" required autofocus autocomplete="email"
                value="<?php echo limpar($_POST['email'] ?? ''); ?>">

            <button type="submit" class="botao botao-largo">
                Recuperar senha
            </button>

        </form>

        <p class="link-recuperacao">

            <a href="<?php echo BASE_URL; ?>/auth/login.php">
                ← Voltar para o login
            </a>

        </p>

    </main>

    <script src="<?php echo BASE_URL; ?>/assets/js/accessibility.js"></script>
</body>

</html>

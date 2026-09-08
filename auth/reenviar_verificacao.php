<?php
/**
 * auth/reenviar_verificacao.php
 * -----------------------------------------------------------------
 * Permite solicitar um novo link de confirmação de e-mail.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';

    } else {

        $email = strtolower(
            trim($_POST['email'] ?? '')
        );

        if ($email === '') {

            $erro = 'Informe seu e-mail.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $erro = 'Informe um e-mail válido.';

        } else {

            /*
             * A resposta será genérica para não revelar
             * quais endereços existem no sistema.
             */
            $sucesso =
                'Se existir uma conta pendente para este e-mail, '
                . 'um novo link de verificação será enviado.';

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    nome,
                    email,
                    email_verificado_em,
                    ativo
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ");

            $stmt->execute([
                ':email' => $email
            ]);

            $usuario = $stmt->fetch();

            /*
             * Só gera novo token se:
             * - usuário existir;
             * - estiver ativo;
             * - ainda não tiver verificado o e-mail.
             */
            if (
                $usuario &&
                (int) $usuario['ativo'] === 1 &&
                $usuario['email_verificado_em'] === null
            ) {

                $usuarioId = (int) $usuario['id'];
                $nomeUsuario = $usuario['nome'];

                // Gera token seguro
                $token = bin2hex(
                    random_bytes(32)
                );

                // Apenas o hash fica no banco
                $tokenHash = hash(
                    'sha256',
                    $token
                );

                // Novo link válido por 24 horas
                $expiraEm = date(
                    'Y-m-d H:i:s',
                    strtotime('+24 hours')
                );

                /*
                 * Primeiro cria o novo token.
                 * Não invalidamos os antigos ainda.
                 *
                 * Assim, se o envio do novo e-mail falhar,
                 * um link anterior ainda poderá continuar válido.
                 */
                $stmtToken = $pdo->prepare("
                    INSERT INTO verificacoes_email
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

                $novoTokenId = (int) $pdo->lastInsertId();

                $linkVerificacao =
                    APP_URL .
                    '/auth/verificar_email.php?token=' .
                    urlencode($token);

                $nomeSeguro = htmlspecialchars(
                    $nomeUsuario,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $linkSeguro = htmlspecialchars(
                    $linkVerificacao,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $assunto =
                    'Novo link de verificação - SmartControl';

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
                            Olá, ' . $nomeSeguro . '.
                        </p>

                        <p>
                            Recebemos uma solicitação para reenviar
                            a confirmação do seu endereço de e-mail.
                        </p>

                        <p style="margin: 30px 0;">

                            <a
                                href="' . $linkSeguro . '"
                                style="
                                    background: #1a4f8b;
                                    color: #ffffff;
                                    padding: 12px 20px;
                                    text-decoration: none;
                                    border-radius: 5px;
                                    display: inline-block;
                                "
                            >
                                Verificar meu e-mail
                            </a>

                        </p>

                        <p>
                            Este link é válido por
                            <strong>24 horas</strong>.
                        </p>

                        <p>
                            Se você não solicitou esta mensagem,
                            ignore este e-mail.
                        </p>

                        <hr style="
                            border: 0;
                            border-top: 1px solid #ddd;
                        ">

                        <p style="
                            font-size: 12px;
                            color: #777;
                        ">
                            Esta é uma mensagem automática do SmartControl.
                        </p>

                    </div>
                ';

                $conteudoTexto =
                    "SmartControl\n\n" .
                    "Olá, " . $nomeUsuario . ".\n\n" .
                    "Foi solicitado um novo link de verificação.\n\n" .
                    "Acesse:\n" .
                    $linkVerificacao . "\n\n" .
                    "Este link é válido por 24 horas.";

                $emailEnviado = enviarEmail(
                    $usuario['email'],
                    $nomeUsuario,
                    $assunto,
                    $conteudoHtml,
                    $conteudoTexto
                );

                if ($emailEnviado) {

                    /*
                     * Agora que sabemos que o novo link chegou,
                     * invalidamos os tokens antigos.
                     */
                    $stmtInvalidar = $pdo->prepare("
                        UPDATE verificacoes_email

                        SET utilizado_em = NOW()

                        WHERE usuario_id = :usuario_id
                          AND utilizado_em IS NULL
                          AND id != :novo_id
                    ");

                    $stmtInvalidar->execute([
                        ':usuario_id' => $usuarioId,
                        ':novo_id' => $novoTokenId
                    ]);

                } else {

                    /*
                     * Se o envio falhou, apagamos somente
                     * o token recém-criado.
                     */
                    $stmtRemover = $pdo->prepare("
                        DELETE FROM verificacoes_email
                        WHERE id = :id
                    ");

                    $stmtRemover->execute([
                        ':id' => $novoTokenId
                    ]);

                    $erro =
                        'Não foi possível enviar o e-mail agora. '
                        . 'Tente novamente mais tarde.';

                    $sucesso = '';
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Reenviar Verificação - SmartControl
    </title>

    <link
        rel="stylesheet"
        href="<?php echo BASE_URL; ?>/assets/css/style.css"
    >

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/accessibility.css">
</head>

<body class="pagina-login">
    <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>
    <?php require __DIR__ . '/../includes/accessibility.php'; ?>

    <main class="caixa-login" id="conteudo-principal" tabindex="-1">

        <h1>
            Verificar e-mail
        </h1>

        <p class="subtitulo">
            Solicite um novo link de confirmação.
        </p>

        <?php if ($erro): ?>

            <div
                class="alerta alerta-erro"
                role="alert"
            >
                <?php echo limpar($erro); ?>
            </div>

        <?php endif; ?>

        <?php if ($sucesso): ?>

            <div
                class="alerta alerta-sucesso"
                role="status"
                aria-live="polite"
            >
                <?php echo limpar($sucesso); ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo gerarTokenCSRF(); ?>"
            >

            <label for="email">
                E-mail
            </label>

            <input
                type="email"
                name="email"
                id="email"
                required
                autofocus
                autocomplete="email"
                value="<?php echo limpar($_POST['email'] ?? ''); ?>"
            >

            <button
                type="submit"
                class="botao botao-largo"
            >
                Reenviar verificação
            </button>

        </form>

        <p class="link-recuperacao">

            <a
                href="<?php echo BASE_URL; ?>/auth/login.php"
            >
                ← Voltar para o login
            </a>

        </p>

    </main>

    <script src="<?php echo BASE_URL; ?>/assets/js/accessibility.js"></script>
</body>

</html>

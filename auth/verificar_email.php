<?php
/**
 * auth/verificar_email.php
 * -----------------------------------------------------------------
 * Confirma o endereço de e-mail através do token enviado ao usuário.
 * O token original não fica armazenado no banco: apenas seu SHA-256.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

$erro = '';
$sucesso = false;
$nomeUsuario = '';

// Token recebido pelo link enviado por e-mail
$token = trim($_GET['token'] ?? '');

// O token gerado pelo sistema possui 64 caracteres hexadecimais
$tokenFormatoValido = (
    $token !== ''
    && preg_match('/^[a-f0-9]{64}$/i', $token)
);

if (!$tokenFormatoValido) {

    $erro = 'Este link de verificação é inválido.';

} else {

    $tokenHash = hash(
        'sha256',
        $token
    );

    try {

        $pdo->beginTransaction();

        /*
         * Procura a solicitação e bloqueia o registro
         * enquanto a verificação está sendo processada.
         */
        $stmt = $pdo->prepare("
            SELECT
                v.id,
                v.usuario_id,
                v.expira_em,
                v.utilizado_em,
                u.nome,
                u.email,
                u.email_verificado_em,
                u.ativo

            FROM verificacoes_email v

            INNER JOIN usuarios u
                ON u.id = v.usuario_id

            WHERE v.token_hash = :token_hash

            LIMIT 1

            FOR UPDATE
        ");

        $stmt->execute([
            ':token_hash' => $tokenHash
        ]);

        $verificacao = $stmt->fetch();

        if (!$verificacao) {

            $pdo->rollBack();

            $erro = 'Este link de verificação é inválido.';

        } elseif ($verificacao['utilizado_em'] !== null) {

            $pdo->rollBack();

            $erro = 'Este link de verificação já foi utilizado.';

        } elseif (
            strtotime($verificacao['expira_em']) < time()
        ) {

            $pdo->rollBack();

            $erro = 'Este link de verificação expirou.';

        } elseif ((int) $verificacao['ativo'] === 0) {

            $pdo->rollBack();

            $erro = 'Esta conta está inativa.';

        } else {

            $usuarioId = (int) $verificacao['usuario_id'];

            $nomeUsuario = $verificacao['nome'];

            /*
             * Marca o e-mail como verificado.
             */
            $stmtUsuario = $pdo->prepare("
                UPDATE usuarios

                SET email_verificado_em = NOW()

                WHERE id = :usuario_id
            ");

            $stmtUsuario->execute([
                ':usuario_id' => $usuarioId
            ]);

            /*
             * Invalida todos os tokens ainda ativos
             * desse usuário.
             */
            $stmtTokens = $pdo->prepare("
                UPDATE verificacoes_email

                SET utilizado_em = NOW()

                WHERE usuario_id = :usuario_id
                  AND utilizado_em IS NULL
            ");

            $stmtTokens->execute([
                ':usuario_id' => $usuarioId
            ]);

            $pdo->commit();

            $sucesso = true;
        }

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erro =
            'Não foi possível verificar seu e-mail. '
            . 'Tente novamente.';
    }
}
?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">

    <title>
        Verificação de E-mail - SmartControl
    </title>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/accessibility.css">
</head>

<body class="pagina-login">
    <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>
    <?php require __DIR__ . '/../includes/accessibility.php'; ?>

    <main class="caixa-login" id="conteudo-principal" tabindex="-1">

        <?php if ($sucesso): ?>

            <h1>
                E-mail verificado
            </h1>

            <div class="alerta alerta-sucesso" role="status" aria-live="polite">
                Seu endereço de e-mail foi confirmado com sucesso.
            </div>

            <?php if ($nomeUsuario): ?>

                <p class="subtitulo">
                    Olá,
                    <?php echo limpar($nomeUsuario); ?>.
                    Sua conta está pronta para ser utilizada.
                </p>

            <?php endif; ?>

            <p class="link-recuperacao">

                <a href="<?php echo BASE_URL; ?>/auth/login.php">
                    Entrar no SmartControl
                </a>

            </p>

        <?php else: ?>

            <h1>
                Não foi possível verificar
            </h1>

            <div class="alerta alerta-erro" role="alert">
                <?php echo limpar($erro); ?>
            </div>

            <p class="link-recuperacao">

                <a href="<?php echo BASE_URL; ?>/auth/login.php">
                    ← Voltar para o login
                </a>

            </p>

        <?php endif; ?>

    </main>

    <script src="<?php echo BASE_URL; ?>/assets/js/accessibility.js"></script>
</body>

</html>

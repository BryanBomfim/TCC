<?php
/**
 * Solicitação pública de um novo link de confirmação de e-mail.
 */

use SmartControl\Services\EmailVerificationService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $erro = 'Informe um e-mail válido.';
        } else {
            $sucesso = 'Se existir uma conta pendente para este e-mail, '
                . 'um novo link de verificação será enviado.';

            if (permitirRequisicaoLimitada('reenviar_verificacao', $email, 3, 15 * 60)) {
                $stmt = $pdo->prepare("
                    SELECT id, nome, email
                    FROM usuarios
                    WHERE email = :email
                      AND ativo = 1
                      AND email_verificado_em IS NULL
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $usuario = $stmt->fetch();

                if ($usuario) {
                    try {
                        $servico = new EmailVerificationService($pdo, 'enviarEmail');
                        $enviado = $servico->enviar(
                            (int) $usuario['id'],
                            (string) $usuario['nome'],
                            (string) $usuario['email']
                        );

                        if (!$enviado) {
                            $erro = 'Não foi possível enviar o e-mail agora. Tente novamente mais tarde.';
                            $sucesso = '';
                        }
                    } catch (Throwable $e) {
                        error_log('Erro ao reenviar verificação: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
                        $erro = 'Não foi possível enviar o e-mail agora. Tente novamente mais tarde.';
                        $sucesso = '';
                    }
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
    <title>Reenviar verificação - SmartControl</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/accessibility.css">
</head>
<body class="pagina-login">
    <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>
    <?php require __DIR__ . '/../includes/accessibility.php'; ?>

    <main class="caixa-login" id="conteudo-principal" tabindex="-1">
        <h1>Verificar e-mail</h1>
        <p class="subtitulo">Solicite um novo link de confirmação.</p>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alerta alerta-sucesso" role="status" aria-live="polite">
                <?php echo limpar($sucesso); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

            <label for="email">E-mail</label>
            <input type="email" name="email" id="email" required autofocus maxlength="150"
                autocomplete="email" value="<?php echo limpar($_POST['email'] ?? ''); ?>">

            <button type="submit" class="botao botao-largo">Reenviar verificação</button>
        </form>

        <p class="link-recuperacao">
            <a href="<?php echo BASE_URL; ?>/auth/login.php">Voltar para o login</a>
        </p>
    </main>

    <script src="<?php echo BASE_URL; ?>/assets/js/accessibility.js"></script>
</body>
</html>

<?php
/**
 * auth/login.php
 * Tela de autenticação do sistema.
 * Valida e-mail/senha, protege contra força bruta,
 * exige verificação de e-mail e inicia a sessão.
 */

require_once __DIR__ . '/../config/config.php';

// Se já estiver logado, vai direto para o dashboard
if (estaLogado()) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

$erro = '';
$mensagem = obterMensagem();

// Configurações de proteção contra força bruta
$maxTentativas = 5;
$tempoBloqueioMinutos = 15;

// IP de quem está tentando entrar
$ipUsuario = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (random_int(1, 100) === 1) {
        $pdo->exec("
            DELETE FROM tentativas_login
            WHERE criado_em < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }

    // -------------------------------------------------------------
    // 1. Proteção CSRF
    // -------------------------------------------------------------
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';

    } else {

        // ---------------------------------------------------------
        // 2. Recebe os dados
        // ---------------------------------------------------------
        $email = strtolower(trim($_POST['email'] ?? ''));
        $senha = $_POST['senha'] ?? '';

        if ($email === '' || $senha === '') {

            $erro = 'Preencha e-mail e senha para continuar.';

        } else {

            // -----------------------------------------------------
            // 3. Verifica tentativas erradas recentes
            // -----------------------------------------------------
            $stmtTentativas = $pdo->prepare("
                SELECT
                    COUNT(*) AS total
                FROM tentativas_login
                WHERE email = :email
                  AND ip = :ip
                  AND sucesso = 0
                  AND criado_em >= DATE_SUB(
                      NOW(),
                      INTERVAL 15 MINUTE
                  )
            ");

            $stmtTentativas->execute([
                ':email' => $email,
                ':ip' => $ipUsuario
            ]);

            $dadosTentativas = $stmtTentativas->fetch();

            $totalTentativas = (int) (
                $dadosTentativas['total'] ?? 0
            );

            // -----------------------------------------------------
            // 4. Bloqueio temporário
            // -----------------------------------------------------
            if ($totalTentativas >= $maxTentativas) {

                notificarAdministradores(
                    'critico',
                    'Tentativa de acesso suspeita',
                    'O limite de tentativas de login foi atingido pelo IP ' . $ipUsuario . '.',
                    'seguranca',
                    null,
                    BASE_URL . '/audit/index.php',
                    15
                );

                registrarEventoSeguranca(
                    'BLOQUEIO_LOGIN',
                    'Tentativa de login bloqueada pelo limite de segurança.'
                );

                $erro =
                    'Muitas tentativas de login. '
                    . 'Aguarde 15 minutos e tente novamente.';

            } else {

                // -------------------------------------------------
                // 5. Busca o usuário
                // -------------------------------------------------
                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        nome,
                        email,
                        senha,
                        auth_version,
                        perfil,
                        ativo,
                        email_verificado_em
                    FROM usuarios
                    WHERE email = :email
                    LIMIT 1
                ");

                $stmt->execute([
                    ':email' => $email
                ]);

                $usuario = $stmt->fetch();

                // -------------------------------------------------
                // 6. Verifica a senha
                // -------------------------------------------------
                $senhaCorreta = (
                    $usuario &&
                    password_verify(
                        $senha,
                        $usuario['senha']
                    )
                );

                // -------------------------------------------------
                // 7. Usuário inexistente ou senha incorreta
                // -------------------------------------------------
                if (!$senhaCorreta) {

                    $stmtFalha = $pdo->prepare("
                        INSERT INTO tentativas_login
                        (
                            email,
                            ip,
                            sucesso
                        )
                        VALUES
                        (
                            :email,
                            :ip,
                            0
                        )
                    ");

                    $stmtFalha->execute([
                        ':email' => $email,
                        ':ip' => $ipUsuario
                    ]);

                    registrarEventoSeguranca(
                        'FALHA_LOGIN',
                        'Credenciais inválidas informadas no login.'
                    );

                    $tentativasRestantes =
                        $maxTentativas -
                        ($totalTentativas + 1);

                    if ($tentativasRestantes <= 0) {

                        notificarAdministradores(
                            'critico',
                            'Tentativa de acesso suspeita',
                            'O limite de tentativas de login foi atingido pelo IP ' . $ipUsuario . '.',
                            'seguranca',
                            null,
                            BASE_URL . '/audit/index.php',
                            15
                        );

                        $erro =
                            'Muitas tentativas de login. '
                            . 'Seu acesso foi bloqueado por 15 minutos.';

                    } else {

                        $erro =
                            'E-mail ou senha inválidos. '
                            . 'Tentativas restantes: '
                            . $tentativasRestantes
                            . '.';
                    }

                    // -------------------------------------------------
                    // 8. Conta inativa
                    // -------------------------------------------------
                } elseif ((int) $usuario['ativo'] === 0) {

                    $erro =
                        'Esta conta está inativa. '
                        . 'Procure o administrador do sistema.';

                    // -------------------------------------------------
                    // 9. E-mail ainda não verificado
                    // -------------------------------------------------
                } elseif ($usuario['email_verificado_em'] === null) {

                    $erro =
                        'Seu e-mail ainda não foi verificado. '
                        . 'Abra a mensagem enviada pelo SmartControl '
                        . 'e confirme seu endereço.';

                    // -------------------------------------------------
                    // 10. Login válido
                    // -------------------------------------------------
                } else {

                    /*
                     * Remove tentativas erradas anteriores
                     * deste e-mail + IP.
                     */
                    $stmtLimpar = $pdo->prepare("
                        DELETE FROM tentativas_login
                        WHERE email = :email
                          AND ip = :ip
                          AND sucesso = 0
                    ");

                    $stmtLimpar->execute([
                        ':email' => $email,
                        ':ip' => $ipUsuario
                    ]);

                    /*
                     * Registra login bem-sucedido.
                     */
                    $stmtSucesso = $pdo->prepare("
                        INSERT INTO tentativas_login
                        (
                            email,
                            ip,
                            sucesso
                        )
                        VALUES
                        (
                            :email,
                            :ip,
                            1
                        )
                    ");

                    $stmtSucesso->execute([
                        ':email' => $email,
                        ':ip' => $ipUsuario
                    ]);

                    /*
                     * Evita session fixation.
                     */
                    session_regenerate_id(true);

                    $_SESSION['usuario_id'] =
                        $usuario['id'];

                    $_SESSION['usuario_nome'] =
                        $usuario['nome'];

                    $_SESSION['usuario_perfil'] =
                        $usuario['perfil'];

                    $_SESSION['ultima_atividade'] = time();
                    $_SESSION['auth_assinatura'] = assinaturaAutenticacao($usuario);
                    $_SESSION['troca_senha_obrigatoria'] = password_verify('SmartControl@2026!', $usuario['senha']);

                    registrarAuditoria(
                        'LOGIN',
                        'Autenticação',
                        (int) $usuario['id'],
                        'Login realizado com sucesso.'
                    );

                    /*
                     * Renova o token CSRF depois do login.
                     */
                    unset($_SESSION['csrf_token']);

                    header(
                        'Location: '
                        . BASE_URL
                        . '/dashboard/index.php'
                    );

                    exit;
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

    <title>
        Login - SmartControl
    </title>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/accessibility.css">
</head>

<body class="pagina-login">
    <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>
    <?php require __DIR__ . '/../includes/accessibility.php'; ?>

    <div class="caixa-login" id="conteudo-principal" tabindex="-1">

        <h1>
            SmartControl
        </h1>

        <p class="subtitulo">
            Sistema de Gerenciamento de Estoque
        </p>
        <p class="link-recuperacao"><a href="<?php echo BASE_URL; ?>/">Conheça o SmartControl</a></p>

        <?php if ($mensagem): ?>
            <div class="alerta alerta-<?php echo limpar($mensagem['tipo']); ?>" role="status" aria-live="polite">
                <?php echo limpar($mensagem['texto']); ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>

            <div class="alerta alerta-erro" role="alert">
                <?php echo limpar($erro); ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

            <label for="email">
                E-mail
            </label>

            <input type="email" name="email" id="email" required autofocus autocomplete="email"
                value="<?php echo limpar($email ?? ''); ?>">

            <label for="senha">
                Senha
            </label>

            <div class="campo-senha">

                <input type="password" name="senha" id="senha" required autocomplete="current-password">

                <button type="button" class="botao-mostrar-senha" id="botaoMostrarSenha" data-target="senha"
                    aria-controls="senha" aria-pressed="false">
                    Mostrar
                </button>

            </div>

            <button type="submit" class="botao botao-largo">
                Entrar
            </button>

            <p class="link-recuperacao">

                <a href="<?php echo BASE_URL; ?>/auth/esqueci_senha.php">
                    Esqueci minha senha
                </a>

            </p>
            <p class="link-recuperacao">

                <a href="<?php echo BASE_URL; ?>/auth/reenviar_verificacao.php">
                    Não recebeu o e-mail de verificação?
                </a>

            </p>

        </form>

    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>

    <script src="<?php echo BASE_URL; ?>/assets/js/accessibility.js"></script>
</body>

</html>

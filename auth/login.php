<?php
/**
 * auth/login.php
 * Tela de autenticação do sistema. Valida e-mail/senha contra a
 * tabela de usuários e inicia a sessão do usuário autenticado.
 */
require_once __DIR__ . '/../config/config.php';

// Se já estiver logado, vai direto para o dashboard
if (estaLogado()) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha para continuar.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nome, senha, perfil, ativo FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $erro = 'E-mail ou senha inválidos.';
        } elseif ((int) $usuario['ativo'] === 0) {
            $erro = 'Este usuário está inativo. Procure o administrador do sistema.';
        } elseif (!password_verify($senha, $usuario['senha'])) {
            $erro = 'E-mail ou senha inválidos.';
        } else {
            // Autenticação bem-sucedida
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_perfil'] = $usuario['perfil'];

            header('Location: ' . BASE_URL . '/dashboard/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SmartControl</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body class="pagina-login">
    <div class="caixa-login">
        <h1>SmartControl</h1>
        <p class="subtitulo">Sistema de Gerenciamento de Estoque</p>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="email">E-mail</label>
            <input type="email" name="email" id="email" required autofocus value="<?php echo limpar($email ?? ''); ?>">

            <label for="senha">Senha</label>
            <input type="password" name="senha" id="senha" required>

            <button type="submit" class="botao botao-largo">Entrar</button>
        </form>

        <p class="login-dica">
            Acesso padrão inicial: admin@smartcontrol.com / admin123<br>
            (defina a senha pelo instalador em <code>database/install_admin.php</code>)
        </p>
    </div>
</body>
</html>

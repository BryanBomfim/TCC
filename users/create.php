<?php
/**
 * users/create.php
 * Formulário e processamento de cadastro de novo usuário.
 * Acesso restrito ao administrador.
 */
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
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'] ?? 'funcionario';

        if ($nome === '' || $email === '' || $senha === '') {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif (strlen($senha) < 8) {
            $erro = 'A senha deve ter no mínimo 8 caracteres.';
        } elseif (!in_array($perfil, ['administrador', 'funcionario'], true)) {
            $erro = 'Perfil inválido.';
        } else {
            $stmtVerifica = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmtVerifica->execute([':email' => $email]);

            if ($stmtVerifica->fetch()) {
                $erro = 'Já existe um usuário cadastrado com este e-mail.';
            } else {
                $hash = password_hash(
                    $senha,
                    PASSWORD_DEFAULT
                );

                try {

                    $pdo->beginTransaction();

                    /*
                     * O usuário é criado com o e-mail ainda não verificado.
                     */
                    $stmt = $pdo->prepare("
        INSERT INTO usuarios
        (
            nome,
            email,
            email_verificado_em,
            senha,
            perfil,
            ativo
        )
        VALUES
        (
            :nome,
            :email,
            NULL,
            :senha,
            :perfil,
            1
        )
    ");

                    $stmt->execute([
                        ':nome' => $nome,
                        ':email' => strtolower($email),
                        ':senha' => $hash,
                        ':perfil' => $perfil,
                    ]);

                    $usuarioId = (int) $pdo->lastInsertId();

                    /*
                     * Cria um token aleatório para verificar o e-mail.
                     */
                    $token = bin2hex(
                        random_bytes(32)
                    );

                    /*
                     * No banco salvamos somente o hash.
                     */
                    $tokenHash = hash(
                        'sha256',
                        $token
                    );

                    /*
                     * O link de confirmação ficará válido por 24 horas.
                     */
                    $expiraEm = date(
                        'Y-m-d H:i:s',
                        strtotime('+24 hours')
                    );

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

                    /*
                     * Monta o endereço que será enviado ao usuário.
                     */
                    $linkVerificacao =
                        APP_URL .
                        '/auth/verificar_email.php?token=' .
                        urlencode($token);

                    $assunto =
                        'Confirme seu e-mail - SmartControl';

                    $nomeSeguro = htmlspecialchars(
                        $nome,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $linkSeguro = htmlspecialchars(
                        $linkVerificacao,
                        ENT_QUOTES,
                        'UTF-8'
                    );

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
                Uma conta foi criada para você no SmartControl.
            </p>

            <p>
                Antes de acessar o sistema,
                precisamos confirmar seu endereço de e-mail.
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
                Este link ficará disponível por
                <strong>24 horas</strong>.
            </p>

            <p>
                Se você não reconhece este cadastro,
                ignore esta mensagem.
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
                        "Olá, " . $nome . ".\n\n" .
                        "Uma conta foi criada para você no SmartControl.\n\n" .
                        "Confirme seu e-mail através do link abaixo:\n" .
                        $linkVerificacao . "\n\n" .
                        "Este link é válido por 24 horas.";

                    /*
                     * Confirma primeiro os dados locais. O cadastro não deve
                     * depender da disponibilidade de um serviço SMTP externo.
                     * Se o envio falhar, a conta permanece pendente e o e-mail
                     * pode ser reenviado pela tela de verificação.
                     */
                    $pdo->commit();

                    registrarAuditoria(
                        'CRIAR',
                        'Usuários',
                        $usuarioId,
                        'Usuário cadastrado: ' . $nome . '.',
                        null,
                        [
                            'nome' => $nome,
                            'email' => strtolower($email),
                            'perfil' => $perfil,
                            'ativo' => 1,
                            'email_verificado' => false,
                        ]
                    );

                    $emailEnviado = enviarEmail(
                        strtolower($email),
                        $nome,
                        $assunto,
                        $conteudoHtml,
                        $conteudoTexto
                    );

                    if ($emailEnviado) {
                        definirMensagem(
                            'sucesso',
                            'Usuário cadastrado! Um e-mail de verificação foi enviado.'
                        );
                    } else {
                        definirMensagem(
                            'alerta',
                            'Usuário cadastrado, mas o e-mail de verificação não pôde ser enviado. '
                            . 'Configure o SMTP e use a opção de reenviar verificação.'
                        );
                    }

                    header(
                        'Location: ' .
                        BASE_URL .
                        '/users/list.php'
                    );

                    exit;

                } catch (Throwable $e) {

                    error_log('Erro ao cadastrar usuário: ' . $e->getMessage());
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $erro =
                        'Não foi possível cadastrar o usuário. '
                        . 'Tente novamente.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <h1>Novo Usuário</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?php echo limpar($nome); ?>">

        <label for="email">E-mail *</label>
        <input type="email" name="email" id="email" required value="<?php echo limpar($email); ?>">

        <label for="senha">Senha *</label>
        <input type="password" name="senha" id="senha" required minlength="8">

        <label for="perfil">Perfil *</label>
        <select name="perfil" id="perfil" required>
            <option value="funcionario" <?php echo $perfil === 'funcionario' ? 'selected' : ''; ?>>Funcionário</option>
            <option value="administrador" <?php echo $perfil === 'administrador' ? 'selected' : ''; ?>>Administrador
            </option>
        </select>

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar</button>
            <a href="<?php echo BASE_URL; ?>/users/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * suppliers/create.php
 * Formulário e processamento de cadastro de novo fornecedor.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$dados = ['nome' => '', 'cnpj_cpf' => '', 'telefone' => '', 'email' => '', 'endereco' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        foreach ($dados as $campo => $valor) {
            $dados[$campo] = trim($_POST[$campo] ?? '');
        }

        if ($dados['nome'] === '') {
            $erro = 'O nome do fornecedor é obrigatório.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO fornecedores (nome, cnpj_cpf, telefone, email, endereco, ativo)
                VALUES (:nome, :cnpj_cpf, :telefone, :email, :endereco, 1)
            ");
            $stmt->execute([
                ':nome' => $dados['nome'],
                ':cnpj_cpf' => $dados['cnpj_cpf'] ?: null,
                ':telefone' => $dados['telefone'] ?: null,
                ':email' => $dados['email'] ?: null,
                ':endereco' => $dados['endereco'] ?: null,
            ]);

            definirMensagem('sucesso', 'Fornecedor cadastrado com sucesso!');
            header('Location: ' . BASE_URL . '/suppliers/list.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Novo Fornecedor</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?php echo limpar($dados['nome']); ?>">

        <label for="cnpj_cpf">CNPJ/CPF</label>
        <input type="text" name="cnpj_cpf" id="cnpj_cpf" value="<?php echo limpar($dados['cnpj_cpf']); ?>">

        <label for="telefone">Telefone</label>
        <input type="text" name="telefone" id="telefone" value="<?php echo limpar($dados['telefone']); ?>">

        <label for="email">E-mail</label>
        <input type="email" name="email" id="email" value="<?php echo limpar($dados['email']); ?>">

        <label for="endereco">Endereço</label>
        <input type="text" name="endereco" id="endereco" value="<?php echo limpar($dados['endereco']); ?>">

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar</button>
            <a href="<?php echo BASE_URL; ?>/suppliers/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

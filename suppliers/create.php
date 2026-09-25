<?php
/** Cadastro de fornecedor com endereço estruturado e integrações opcionais. */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$dados = extrairDadosFornecedor([]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $dados = extrairDadosFornecedor($_POST);
        $erro = validarDadosFornecedor($dados) ?? '';

        if ($erro === '') {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO fornecedores
                        (nome, razao_social, cnpj_cpf, telefone, email, cep, logradouro,
                         numero, complemento, bairro, cidade, uf, endereco, ativo)
                    VALUES
                        (:nome, :razao_social, :cnpj_cpf, :telefone, :email, :cep, :logradouro,
                         :numero, :complemento, :bairro, :cidade, :uf, :endereco, 1)
                ");
                $parametros = [];
                foreach ($dados as $campo => $valor) {
                    $parametros[':' . $campo] = $valor !== '' ? $valor : null;
                }
                $stmt->execute($parametros);
                $fornecedorId = (int) $pdo->lastInsertId();

                registrarAuditoria(
                    'CRIAR',
                    'Fornecedores',
                    $fornecedorId,
                    'Fornecedor "' . $dados['nome'] . '" cadastrado.',
                    null,
                    array_diff_key($dados, ['endereco' => true])
                );

                definirMensagem('sucesso', 'Fornecedor cadastrado com sucesso!');
                header('Location: ' . BASE_URL . '/suppliers/list.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $erro = 'Já existe um fornecedor com este CPF ou CNPJ.';
                } else {
                    error_log('Erro ao cadastrar fornecedor: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
                    $erro = 'Não foi possível cadastrar o fornecedor.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Novo Fornecedor</h1>
    <p class="descricao-pagina">As consultas externas são opcionais; todos os campos permanecem editáveis.</p>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div><?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
        <?php require __DIR__ . '/../includes/supplier_form_fields.php'; ?>
        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar</button>
            <a href="<?php echo BASE_URL; ?>/suppliers/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

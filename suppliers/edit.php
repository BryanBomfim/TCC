<?php
/** Edição de fornecedor com auditoria de antes/depois. */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM fornecedores WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$fornecedor = $stmt->fetch();

if (!$fornecedor) {
    definirMensagem('erro', 'Fornecedor não encontrado.');
    header('Location: ' . BASE_URL . '/suppliers/list.php');
    exit;
}

$erro = '';
$dados = extrairDadosFornecedor($fornecedor);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $dados = extrairDadosFornecedor($_POST);
        $erro = validarDadosFornecedor($dados) ?? '';

        if ($erro === '') {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE fornecedores SET
                        nome = :nome, razao_social = :razao_social, cnpj_cpf = :cnpj_cpf,
                        telefone = :telefone, email = :email, cep = :cep, logradouro = :logradouro,
                        numero = :numero, complemento = :complemento, bairro = :bairro,
                        cidade = :cidade, uf = :uf, endereco = :endereco
                    WHERE id = :id
                ");
                $parametros = [':id' => $id];
                foreach ($dados as $campo => $valor) {
                    $parametros[':' . $campo] = $valor !== '' ? $valor : null;
                }
                $stmtUpdate->execute($parametros);

                registrarAuditoria(
                    'EDITAR',
                    'Fornecedores',
                    $id,
                    'Fornecedor "' . $dados['nome'] . '" atualizado.',
                    array_intersect_key($fornecedor, $dados),
                    $dados
                );
                definirMensagem('sucesso', 'Fornecedor atualizado com sucesso!');
                header('Location: ' . BASE_URL . '/suppliers/list.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $erro = 'Já existe outro fornecedor com este CPF ou CNPJ.';
                } else {
                    error_log('Erro ao atualizar fornecedor: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
                    $erro = 'Não foi possível atualizar o fornecedor.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Editar Fornecedor</h1>
    <p class="descricao-pagina">Consulte dados públicos ou mantenha o preenchimento manual.</p>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div><?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
        <?php require __DIR__ . '/../includes/supplier_form_fields.php'; ?>
        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar Alterações</button>
            <a href="<?php echo BASE_URL; ?>/suppliers/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

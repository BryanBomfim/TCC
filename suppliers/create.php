<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$campos = ['nome','cnpj_cpf','telefone','email','cep','logradouro','numero','complemento','bairro','cidade','uf','endereco'];
$dados = array_fill_keys($campos, '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        foreach ($campos as $campo) {
            $dados[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }

        if ($dados['nome'] === '') {
            $erro = 'O nome do fornecedor é obrigatório.';
        } elseif ($dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif ($dados['uf'] !== '' && !preg_match('/^[A-Za-z]{2}$/', $dados['uf'])) {
            $erro = 'Informe uma UF válida com 2 letras.';
        } else {
            $enderecoCompleto = $dados['endereco'];
            if ($enderecoCompleto === '' && ($dados['logradouro'] !== '' || $dados['cidade'] !== '')) {
                $partes = array_filter([
                    trim($dados['logradouro'] . ($dados['numero'] !== '' ? ', ' . $dados['numero'] : '')),
                    $dados['bairro'],
                    trim($dados['cidade'] . ($dados['uf'] !== '' ? '/' . strtoupper($dados['uf']) : '')),
                ]);
                $enderecoCompleto = implode(' - ', $partes);
            }

            $stmt = $pdo->prepare("INSERT INTO fornecedores
                (nome, cnpj_cpf, telefone, email, cep, logradouro, numero, complemento, bairro, cidade, uf, endereco, ativo)
                VALUES (:nome, :cnpj_cpf, :telefone, :email, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :endereco, 1)");
            $stmt->execute([
                ':nome' => $dados['nome'], ':cnpj_cpf' => $dados['cnpj_cpf'] ?: null,
                ':telefone' => $dados['telefone'] ?: null, ':email' => $dados['email'] ?: null,
                ':cep' => $dados['cep'] ?: null, ':logradouro' => $dados['logradouro'] ?: null,
                ':numero' => $dados['numero'] ?: null, ':complemento' => $dados['complemento'] ?: null,
                ':bairro' => $dados['bairro'] ?: null, ':cidade' => $dados['cidade'] ?: null,
                ':uf' => $dados['uf'] !== '' ? strtoupper($dados['uf']) : null,
                ':endereco' => $enderecoCompleto ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();
            registrarAuditoria('CRIAR', 'Fornecedores', $id, 'Fornecedor "' . $dados['nome'] . '" cadastrado.', null, ['nome' => $dados['nome'], 'cnpj_cpf' => $dados['cnpj_cpf'] ?: null]);
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
    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div><?php endif; ?>
    <form method="POST" class="formulario" data-supplier-form>
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
        <label for="nome">Nome *</label><input type="text" name="nome" id="nome" required maxlength="150" value="<?php echo limpar($dados['nome']); ?>">
        <div class="grade-campos">
            <div><label for="cnpj_cpf">CNPJ/CPF</label><div class="acoes-formulario"><input type="text" name="cnpj_cpf" id="cnpj_cpf" maxlength="20" value="<?php echo limpar($dados['cnpj_cpf']); ?>"><button type="button" class="botao botao-secundario" data-consultar-cnpj>Consultar CNPJ</button></div></div>
            <div><label for="telefone">Telefone</label><input type="text" name="telefone" id="telefone" maxlength="20" value="<?php echo limpar($dados['telefone']); ?>"></div>
        </div>
        <label for="email">E-mail</label><input type="email" name="email" id="email" maxlength="150" value="<?php echo limpar($dados['email']); ?>">
        <h2>Endereço</h2>
        <label for="cep">CEP</label>
        <div class="acoes-formulario"><input type="text" name="cep" id="cep" maxlength="9" inputmode="numeric" value="<?php echo limpar($dados['cep']); ?>" placeholder="00000-000"><button type="button" class="botao botao-secundario" data-consultar-cep>Buscar CEP</button></div>
        <span class="ajuda-campo" data-supplier-status aria-live="polite">O preenchimento manual continua disponível se o serviço estiver indisponível.</span>
        <div class="grade-campos">
            <div><label for="logradouro">Logradouro</label><input type="text" name="logradouro" id="logradouro" maxlength="150" value="<?php echo limpar($dados['logradouro']); ?>"></div>
            <div><label for="numero">Número</label><input type="text" name="numero" id="numero" maxlength="20" value="<?php echo limpar($dados['numero']); ?>"></div>
            <div><label for="complemento">Complemento</label><input type="text" name="complemento" id="complemento" maxlength="100" value="<?php echo limpar($dados['complemento']); ?>"></div>
            <div><label for="bairro">Bairro</label><input type="text" name="bairro" id="bairro" maxlength="100" value="<?php echo limpar($dados['bairro']); ?>"></div>
            <div><label for="cidade">Cidade</label><input type="text" name="cidade" id="cidade" maxlength="100" value="<?php echo limpar($dados['cidade']); ?>"></div>
            <div><label for="uf">UF</label><input type="text" name="uf" id="uf" maxlength="2" value="<?php echo limpar($dados['uf']); ?>"></div>
        </div>
        <label for="endereco">Endereço completo (opcional)</label><input type="text" name="endereco" id="endereco" maxlength="255" value="<?php echo limpar($dados['endereco']); ?>">
        <div class="acoes-formulario"><button type="submit" class="botao">Salvar</button><a href="<?php echo BASE_URL; ?>/suppliers/list.php" class="botao botao-secundario">Cancelar</a></div>
    </form>
</div>
<script>window.smartControlBaseUrl = <?php echo json_encode(BASE_URL); ?>;</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

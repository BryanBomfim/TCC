<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM fornecedores WHERE id = :id');
$stmt->execute([':id' => $id]);
$fornecedor = $stmt->fetch();
if (!$fornecedor) { definirMensagem('erro', 'Fornecedor não encontrado.'); header('Location: ' . BASE_URL . '/suppliers/list.php'); exit; }
$erro = '';
$campos = ['nome','cnpj_cpf','telefone','email','cep','logradouro','numero','complemento','bairro','cidade','uf','endereco'];
foreach ($campos as $campo) { $fornecedor[$campo] = $fornecedor[$campo] ?? ''; }
$antes = $fornecedor;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) { $erro = 'Sessão expirada. Recarregue a página e tente novamente.'; }
    else {
        foreach ($campos as $campo) { $fornecedor[$campo] = trim((string) ($_POST[$campo] ?? '')); }
        if ($fornecedor['nome'] === '') { $erro = 'O nome do fornecedor é obrigatório.'; }
        elseif ($fornecedor['email'] !== '' && !filter_var($fornecedor['email'], FILTER_VALIDATE_EMAIL)) { $erro = 'Informe um e-mail válido.'; }
        else {
            $enderecoCompleto = $fornecedor['endereco'];
            if ($enderecoCompleto === '' && ($fornecedor['logradouro'] !== '' || $fornecedor['cidade'] !== '')) {
                $partes = array_filter([trim($fornecedor['logradouro'] . ($fornecedor['numero'] !== '' ? ', ' . $fornecedor['numero'] : '')), $fornecedor['bairro'], trim($fornecedor['cidade'] . ($fornecedor['uf'] !== '' ? '/' . strtoupper($fornecedor['uf']) : ''))]);
                $enderecoCompleto = implode(' - ', $partes);
            }
            $stmtUpdate = $pdo->prepare("UPDATE fornecedores SET nome=:nome, cnpj_cpf=:cnpj_cpf, telefone=:telefone, email=:email, cep=:cep, logradouro=:logradouro, numero=:numero, complemento=:complemento, bairro=:bairro, cidade=:cidade, uf=:uf, endereco=:endereco WHERE id=:id");
            $stmtUpdate->execute([':nome'=>$fornecedor['nome'], ':cnpj_cpf'=>$fornecedor['cnpj_cpf']?:null, ':telefone'=>$fornecedor['telefone']?:null, ':email'=>$fornecedor['email']?:null, ':cep'=>$fornecedor['cep']?:null, ':logradouro'=>$fornecedor['logradouro']?:null, ':numero'=>$fornecedor['numero']?:null, ':complemento'=>$fornecedor['complemento']?:null, ':bairro'=>$fornecedor['bairro']?:null, ':cidade'=>$fornecedor['cidade']?:null, ':uf'=>$fornecedor['uf']!==''?strtoupper($fornecedor['uf']):null, ':endereco'=>$enderecoCompleto?:null, ':id'=>$id]);
            registrarAuditoria('EDITAR', 'Fornecedores', $id, 'Fornecedor "' . $fornecedor['nome'] . '" atualizado.', ['nome'=>$antes['nome'],'cnpj_cpf'=>$antes['cnpj_cpf']], ['nome'=>$fornecedor['nome'],'cnpj_cpf'=>$fornecedor['cnpj_cpf']]);
            definirMensagem('sucesso', 'Fornecedor atualizado com sucesso!'); header('Location: ' . BASE_URL . '/suppliers/list.php'); exit;
        }
    }
}
require_once __DIR__ . '/../includes/header.php'; require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1"><h1>Editar Fornecedor</h1>
<?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div><?php endif; ?>
<form method="POST" class="formulario" data-supplier-form><input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
<label for="nome">Nome *</label><input type="text" name="nome" id="nome" required maxlength="150" value="<?php echo limpar($fornecedor['nome']); ?>">
<div class="grade-campos"><div><label for="cnpj_cpf">CNPJ/CPF</label><div class="acoes-formulario"><input type="text" name="cnpj_cpf" id="cnpj_cpf" maxlength="20" value="<?php echo limpar($fornecedor['cnpj_cpf']); ?>"><button type="button" class="botao botao-secundario" data-consultar-cnpj>Consultar CNPJ</button></div></div><div><label for="telefone">Telefone</label><input type="text" name="telefone" id="telefone" maxlength="20" value="<?php echo limpar($fornecedor['telefone']); ?>"></div></div>
<label for="email">E-mail</label><input type="email" name="email" id="email" maxlength="150" value="<?php echo limpar($fornecedor['email']); ?>">
<h2>Endereço</h2><label for="cep">CEP</label><div class="acoes-formulario"><input type="text" name="cep" id="cep" maxlength="9" inputmode="numeric" value="<?php echo limpar($fornecedor['cep']); ?>"><button type="button" class="botao botao-secundario" data-consultar-cep>Buscar CEP</button></div><span class="ajuda-campo" data-supplier-status aria-live="polite">Consulta opcional pelo ViaCEP.</span>
<div class="grade-campos"><div><label for="logradouro">Logradouro</label><input type="text" name="logradouro" id="logradouro" maxlength="150" value="<?php echo limpar($fornecedor['logradouro']); ?>"></div><div><label for="numero">Número</label><input type="text" name="numero" id="numero" maxlength="20" value="<?php echo limpar($fornecedor['numero']); ?>"></div><div><label for="complemento">Complemento</label><input type="text" name="complemento" id="complemento" maxlength="100" value="<?php echo limpar($fornecedor['complemento']); ?>"></div><div><label for="bairro">Bairro</label><input type="text" name="bairro" id="bairro" maxlength="100" value="<?php echo limpar($fornecedor['bairro']); ?>"></div><div><label for="cidade">Cidade</label><input type="text" name="cidade" id="cidade" maxlength="100" value="<?php echo limpar($fornecedor['cidade']); ?>"></div><div><label for="uf">UF</label><input type="text" name="uf" id="uf" maxlength="2" value="<?php echo limpar($fornecedor['uf']); ?>"></div></div>
<label for="endereco">Endereço completo (opcional)</label><input type="text" name="endereco" id="endereco" maxlength="255" value="<?php echo limpar($fornecedor['endereco']); ?>">
<div class="acoes-formulario"><button type="submit" class="botao">Salvar Alterações</button><a href="<?php echo BASE_URL; ?>/suppliers/list.php" class="botao botao-secundario">Cancelar</a></div></form></div>
<script>window.smartControlBaseUrl = <?php echo json_encode(BASE_URL); ?>;</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

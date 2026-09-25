<?php
if (!defined('BASE_URL') || !isset($dados) || !is_array($dados)) {
    http_response_code(403);
    exit;
}
?>
<fieldset class="grupo-formulario" data-supplier-form>
    <legend>Identificação</legend>

    <label for="cnpj_cpf">CNPJ/CPF</label>
    <div class="campo-com-acao">
        <input type="text" name="cnpj_cpf" id="cnpj_cpf" maxlength="20" inputmode="numeric"
            value="<?php echo limpar($dados['cnpj_cpf'] ?? ''); ?>" autocomplete="off">
        <button type="button" class="botao botao-secundario" data-lookup-cnpj>Consultar CNPJ</button>
    </div>
    <small data-lookup-feedback aria-live="polite"></small>

    <label for="nome">Nome comercial *</label>
    <input type="text" name="nome" id="nome" required maxlength="150"
        value="<?php echo limpar($dados['nome'] ?? ''); ?>">

    <label for="razao_social">Razão social</label>
    <input type="text" name="razao_social" id="razao_social" maxlength="180"
        value="<?php echo limpar($dados['razao_social'] ?? ''); ?>">

    <label for="telefone">Telefone</label>
    <input type="tel" name="telefone" id="telefone" maxlength="20"
        value="<?php echo limpar($dados['telefone'] ?? ''); ?>" autocomplete="tel">

    <label for="email">E-mail</label>
    <input type="email" name="email" id="email" maxlength="150"
        value="<?php echo limpar($dados['email'] ?? ''); ?>" autocomplete="email">
</fieldset>

<fieldset class="grupo-formulario">
    <legend>Endereço</legend>

    <label for="cep">CEP</label>
    <div class="campo-com-acao">
        <input type="text" name="cep" id="cep" maxlength="9" inputmode="numeric"
            value="<?php echo limpar($dados['cep'] ?? ''); ?>" autocomplete="postal-code">
        <button type="button" class="botao botao-secundario" data-lookup-cep>Consultar CEP</button>
    </div>

    <label for="logradouro">Logradouro</label>
    <input type="text" name="logradouro" id="logradouro" maxlength="180"
        value="<?php echo limpar($dados['logradouro'] ?? ''); ?>" autocomplete="address-line1">

    <label for="numero">Número</label>
    <input type="text" name="numero" id="numero" maxlength="20"
        value="<?php echo limpar($dados['numero'] ?? ''); ?>">

    <label for="complemento">Complemento</label>
    <input type="text" name="complemento" id="complemento" maxlength="100"
        value="<?php echo limpar($dados['complemento'] ?? ''); ?>" autocomplete="address-line2">

    <label for="bairro">Bairro</label>
    <input type="text" name="bairro" id="bairro" maxlength="100"
        value="<?php echo limpar($dados['bairro'] ?? ''); ?>">

    <label for="cidade">Cidade</label>
    <input type="text" name="cidade" id="cidade" maxlength="100"
        value="<?php echo limpar($dados['cidade'] ?? ''); ?>" autocomplete="address-level2">

    <label for="uf">UF</label>
    <input type="text" name="uf" id="uf" maxlength="2" pattern="[A-Za-z]{2}"
        value="<?php echo limpar($dados['uf'] ?? ''); ?>" autocomplete="address-level1">
</fieldset>

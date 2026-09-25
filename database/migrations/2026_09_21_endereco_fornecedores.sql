-- Estrutura de endereço separada para integração com CEP e CNPJ.

ALTER TABLE fornecedores
    ADD COLUMN razao_social VARCHAR(180) NULL AFTER nome,
    ADD COLUMN cep CHAR(8) NULL AFTER email,
    ADD COLUMN logradouro VARCHAR(180) NULL AFTER cep,
    ADD COLUMN numero VARCHAR(20) NULL AFTER logradouro,
    ADD COLUMN complemento VARCHAR(100) NULL AFTER numero,
    ADD COLUMN bairro VARCHAR(100) NULL AFTER complemento,
    ADD COLUMN cidade VARCHAR(100) NULL AFTER bairro,
    ADD COLUMN uf CHAR(2) NULL AFTER cidade,
    ADD KEY idx_fornecedores_cnpj_cpf (cnpj_cpf),
    ADD KEY idx_fornecedores_cep (cep);

UPDATE fornecedores
SET logradouro = endereco
WHERE logradouro IS NULL
  AND endereco IS NOT NULL
  AND endereco <> '';

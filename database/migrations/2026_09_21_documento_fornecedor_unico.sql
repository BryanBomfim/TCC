-- Evita o cadastro repetido do mesmo fornecedor.
UPDATE fornecedores
SET cnpj_cpf = NULLIF(REGEXP_REPLACE(cnpj_cpf, '[^0-9]', ''), '')
WHERE cnpj_cpf IS NOT NULL;

ALTER TABLE fornecedores
    ADD UNIQUE KEY uk_fornecedores_cnpj_cpf (cnpj_cpf);

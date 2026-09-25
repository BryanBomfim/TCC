-- Aplicar UMA VEZ na versão do ZIP recebido em 24/09, com backup prévio.
-- Se instalou com o novo smartcontrol.sql, estas colunas já existem: NÃO executar.
-- Selecione seu banco no phpMyAdmin antes da importação. Não remove registros.
ALTER TABLE usuarios ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE inventarios ADD COLUMN revisao INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE inventario_itens ADD COLUMN ultimo_movimento_id BIGINT NOT NULL DEFAULT -1;
-- -1 significa fotografia anterior à migração: exige revisão explícita dos itens abertos.
-- Contagens antigas ficam preservadas até o administrador confirmar a revisão.
INSERT INTO configuracoes (chave, valor) VALUES ('migration_2026_09_24_conclusao', NOW())
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

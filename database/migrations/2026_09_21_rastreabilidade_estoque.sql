-- SmartControl - Fase 3: integridade e rastreabilidade do estoque
-- Execute uma única vez em instalações existentes.

USE smartcontrol;

ALTER TABLE movimentacoes
    ADD COLUMN fornecedor_id INT UNSIGNED NULL AFTER usuario_id,
    ADD COLUMN inventario_id INT UNSIGNED NULL AFTER fornecedor_id,
    ADD COLUMN saldo_anterior INT NULL AFTER quantidade,
    ADD COLUMN saldo_posterior INT NULL AFTER saldo_anterior,
    ADD COLUMN valor_unitario DECIMAL(10, 2) NULL AFTER saldo_posterior,
    ADD COLUMN motivo VARCHAR(100) NULL AFTER valor_unitario,
    ADD COLUMN origem VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER motivo,
    ADD KEY idx_mov_fornecedor (fornecedor_id),
    ADD KEY idx_mov_inventario (inventario_id),
    ADD KEY idx_mov_origem (origem),
    ADD CONSTRAINT fk_mov_fornecedor
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT fk_mov_inventario
        FOREIGN KEY (inventario_id) REFERENCES inventarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- Reconstrói o saldo que já existia no cadastro antes do histórico atual.
SET @usuario_migracao = (
    SELECT id
    FROM usuarios
    ORDER BY (perfil = 'administrador' AND ativo = 1) DESC, ativo DESC, id ASC
    LIMIT 1
);

INSERT INTO movimentacoes
(
    produto_id,
    usuario_id,
    tipo,
    quantidade,
    motivo,
    origem,
    observacao,
    data_movimentacao
)
SELECT
    base.produto_id,
    @usuario_migracao,
    CASE WHEN base.saldo_inicial >= 0 THEN 'entrada' ELSE 'saida' END,
    ABS(base.saldo_inicial),
    'Saldo inicial',
    'migracao',
    'Saldo inicial reconstruído na migração de rastreabilidade.',
    base.data_cadastro
FROM
(
    SELECT
        p.id AS produto_id,
        p.data_cadastro,
        p.quantidade_estoque
            - COALESCE(SUM(
                CASE
                    WHEN m.tipo = 'entrada' THEN m.quantidade
                    ELSE -m.quantidade
                END
            ), 0) AS saldo_inicial
    FROM produtos p
    LEFT JOIN movimentacoes m ON m.produto_id = p.id
    GROUP BY p.id, p.data_cadastro, p.quantidade_estoque
) AS base
WHERE base.saldo_inicial <> 0;

-- Identifica movimentos antigos de inventário e preserva o vínculo.
UPDATE movimentacoes m
INNER JOIN inventarios i
    ON i.id = CAST(
        SUBSTRING_INDEX(
            SUBSTRING_INDEX(m.observacao, '#', -1),
            ' ',
            1
        ) AS UNSIGNED
    )
SET
    m.inventario_id = i.id,
    m.origem = 'inventario',
    m.motivo = 'Ajuste de inventário'
WHERE m.observacao LIKE 'Ajuste do inventário #%';

UPDATE movimentacoes
SET
    origem = CASE tipo
        WHEN 'entrada' THEN 'entrada'
        ELSE 'saida'
    END,
    motivo = CASE tipo
        WHEN 'entrada' THEN 'Entrada de estoque'
        ELSE 'Saída de estoque'
    END
WHERE origem = 'manual';

-- Calcula os saldos anterior e posterior de todo o histórico.
UPDATE movimentacoes m
INNER JOIN
(
    SELECT
        acumulado.id,
        acumulado.saldo_posterior
            - acumulado.variacao AS saldo_anterior,
        acumulado.saldo_posterior
    FROM
    (
        SELECT
            id,
            CASE
                WHEN tipo = 'entrada' THEN quantidade
                ELSE -quantidade
            END AS variacao,
            SUM(
                CASE
                    WHEN tipo = 'entrada' THEN quantidade
                    ELSE -quantidade
                END
            ) OVER (
                PARTITION BY produto_id
                ORDER BY data_movimentacao, id
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            ) AS saldo_posterior
        FROM movimentacoes
    ) AS acumulado
) AS calculo ON calculo.id = m.id
SET
    m.saldo_anterior = calculo.saldo_anterior,
    m.saldo_posterior = calculo.saldo_posterior;

ALTER TABLE movimentacoes
    MODIFY saldo_anterior INT NOT NULL,
    MODIFY saldo_posterior INT NOT NULL;

-- O valor 1 existe apenas para o inventário aberto; NULL pode se repetir.
ALTER TABLE inventarios
    ADD COLUMN inventario_aberto_unico TINYINT
        GENERATED ALWAYS AS (
            CASE WHEN status = 'aberto' THEN 1 ELSE NULL END
        ) PERSISTENT,
    ADD UNIQUE KEY uk_inventarios_um_aberto (inventario_aberto_unico);

INSERT INTO configuracoes (chave, valor)
VALUES ('migration_2026_09_21_rastreabilidade', NOW())
ON DUPLICATE KEY UPDATE valor = VALUES(valor);


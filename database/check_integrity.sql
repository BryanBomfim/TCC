-- SmartControl - consultas de auditoria de integridade
-- Este arquivo NÃO altera dados. Execute no phpMyAdmin após uma migração.
USE smartcontrol;

SELECT 'produtos_sem_categoria_valida' AS verificacao, COUNT(*) AS total
FROM produtos p
LEFT JOIN categorias c ON c.id = p.categoria_id
WHERE p.categoria_id IS NOT NULL AND c.id IS NULL
UNION ALL
SELECT 'produtos_sem_fornecedor_valido', COUNT(*)
FROM produtos p
LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
WHERE p.fornecedor_id IS NOT NULL AND f.id IS NULL
UNION ALL
SELECT 'movimentacoes_sem_produto', COUNT(*)
FROM movimentacoes m
LEFT JOIN produtos p ON p.id = m.produto_id
WHERE p.id IS NULL
UNION ALL
SELECT 'movimentacoes_sem_usuario', COUNT(*)
FROM movimentacoes m
LEFT JOIN usuarios u ON u.id = m.usuario_id
WHERE u.id IS NULL
UNION ALL
SELECT 'movimentacoes_fornecedor_orfao', COUNT(*)
FROM movimentacoes m
LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
WHERE m.fornecedor_id IS NOT NULL AND f.id IS NULL
UNION ALL
SELECT 'itens_inventario_sem_inventario', COUNT(*)
FROM inventario_itens ii
LEFT JOIN inventarios i ON i.id = ii.inventario_id
WHERE i.id IS NULL
UNION ALL
SELECT 'itens_inventario_sem_produto', COUNT(*)
FROM inventario_itens ii
LEFT JOIN produtos p ON p.id = ii.produto_id
WHERE p.id IS NULL
UNION ALL
SELECT 'recuperacao_senha_usuario_orfao', COUNT(*)
FROM recuperacoes_senha r
LEFT JOIN usuarios u ON u.id = r.usuario_id
WHERE u.id IS NULL
UNION ALL
SELECT 'verificacao_email_usuario_orfao', COUNT(*)
FROM verificacoes_email v
LEFT JOIN usuarios u ON u.id = v.usuario_id
WHERE u.id IS NULL
UNION ALL
SELECT 'notificacao_usuario_orfao', COUNT(*)
FROM notificacoes n
LEFT JOIN usuarios u ON u.id = n.usuario_id
WHERE u.id IS NULL
UNION ALL
SELECT 'inventario_criador_orfao', COUNT(*)
FROM inventarios i
LEFT JOIN usuarios u ON u.id = i.criado_por
WHERE i.criado_por IS NOT NULL AND u.id IS NULL
UNION ALL
SELECT 'inventario_finalizador_orfao', COUNT(*)
FROM inventarios i
LEFT JOIN usuarios u ON u.id = i.finalizado_por
WHERE i.finalizado_por IS NOT NULL AND u.id IS NULL
UNION ALL
SELECT 'item_inventario_contador_orfao', COUNT(*)
FROM inventario_itens ii
LEFT JOIN usuarios u ON u.id = ii.contado_por
WHERE ii.contado_por IS NOT NULL AND u.id IS NULL
UNION ALL
SELECT 'anexo_usuario_orfao', COUNT(*)
FROM anexos a
LEFT JOIN usuarios u ON u.id = a.usuario_id
WHERE a.usuario_id IS NOT NULL AND u.id IS NULL
UNION ALL
SELECT 'estoque_negativo', COUNT(*)
FROM produtos
WHERE quantidade_estoque < 0
UNION ALL
SELECT 'movimentacao_saldo_incoerente', COUNT(*)
FROM movimentacoes m
WHERE m.saldo_anterior IS NOT NULL
  AND m.saldo_posterior IS NOT NULL
  AND (
      (m.tipo = 'entrada' AND m.saldo_posterior <> m.saldo_anterior + m.quantidade)
      OR
      (m.tipo = 'saida' AND m.saldo_posterior <> m.saldo_anterior - m.quantidade)
  );

-- Duplicidades relevantes que não deveriam existir.
SELECT sku, COUNT(*) AS total
FROM produtos
WHERE sku IS NOT NULL AND TRIM(sku) <> ''
GROUP BY sku
HAVING COUNT(*) > 1;

SELECT codigo_barras, COUNT(*) AS total
FROM produtos
WHERE codigo_barras IS NOT NULL AND TRIM(codigo_barras) <> ''
GROUP BY codigo_barras
HAVING COUNT(*) > 1;

SELECT email, COUNT(*) AS total
FROM usuarios
GROUP BY email
HAVING COUNT(*) > 1;

-- Compara o saldo atual com a última movimentação moderna que possui
-- saldo posterior. Produtos legados sem saldo histórico não aparecem aqui.
SELECT
    p.id,
    p.sku,
    p.nome,
    p.quantidade_estoque AS estoque_atual,
    m.saldo_posterior AS ultimo_saldo_registrado,
    m.data_movimentacao AS ultima_movimentacao
FROM produtos p
INNER JOIN movimentacoes m
    ON m.id = (
        SELECT m2.id
        FROM movimentacoes m2
        WHERE m2.produto_id = p.id
          AND m2.saldo_posterior IS NOT NULL
        ORDER BY m2.data_movimentacao DESC, m2.id DESC
        LIMIT 1
    )
WHERE p.quantidade_estoque <> m.saldo_posterior;

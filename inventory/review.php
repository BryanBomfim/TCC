<?php
/** Reabre a contagem apenas dos produtos cuja fotografia de estoque ficou desatualizada. */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();
$id = (int) ($_POST['inventario_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarTokenCSRF($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Solicitação inválida. Recarregue o inventário.');
}
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, status FROM inventarios WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $inventario = $stmt->fetch();
    if (!$inventario || $inventario['status'] !== 'aberto') {
        throw new DomainException('Somente inventários abertos podem ser revisados.');
    }
    $stmt = $pdo->prepare('SELECT * FROM inventario_itens WHERE inventario_id = :id ORDER BY produto_id FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $itens = $stmt->fetchAll();
    $produtoStmt = $pdo->prepare('SELECT quantidade_estoque FROM produtos WHERE id = :id FOR UPDATE');
    $movStmt = $pdo->prepare('SELECT id FROM movimentacoes WHERE produto_id = :id ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $atualizar = $pdo->prepare('UPDATE inventario_itens SET estoque_sistema=:saldo, ultimo_movimento_id=:mov, quantidade_contada=NULL, diferenca=NULL, contado_por=NULL, contado_por_nome=NULL, contado_em=NULL WHERE id=:id');
    $revisados = 0;
    foreach ($itens as $item) {
        $produtoStmt->execute([':id' => $item['produto_id']]);
        $saldo = $produtoStmt->fetchColumn();
        if ($saldo === false) {
            throw new DomainException('Produto não encontrado.');
        }
        $movStmt->execute([':id' => $item['produto_id']]);
        $mov = (int) ($movStmt->fetchColumn() ?: 0);
        if ((int) $saldo === (int) $item['estoque_sistema'] && $mov === (int) $item['ultimo_movimento_id']) {
            continue;
        }
        $atualizar->execute([':saldo' => $saldo, ':mov' => $mov, ':id' => $item['id']]);
        registrarAuditoria('RECONTAR', 'Inventários', $id, 'Referência atualizada para nova contagem física.',
            ['produto_id' => $item['produto_id'], 'estoque_sistema' => $item['estoque_sistema'], 'quantidade_contada' => $item['quantidade_contada'], 'ultimo_movimento_id' => $item['ultimo_movimento_id']],
            ['estoque_sistema' => $saldo, 'ultimo_movimento_id' => $mov, 'quantidade_contada' => null]);
        $revisados++;
    }
    $stmt = $pdo->prepare('UPDATE inventarios SET revisao = revisao + 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $pdo->commit();
    definirMensagem('sucesso', $revisados . ' produto(s) precisam de nova contagem física.');
} catch (DomainException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    definirMensagem('erro', $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('Falha na revisão do inventário: ' . $e->getCode());
    definirMensagem('erro', 'Não foi possível revisar o inventário. Confira se a atualização do banco foi aplicada.');
}
header('Location: ' . BASE_URL . '/inventory/view.php?id=' . $id);
exit;

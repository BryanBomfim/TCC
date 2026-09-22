<?php
/** Alterna o status ativo/inativo de uma categoria com auditoria. */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarTokenCSRF($_POST['csrf_token'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);
    $stmtAtual = $pdo->prepare("SELECT id, nome, ativo FROM categorias WHERE id = :id");
    $stmtAtual->execute([':id' => $id]);
    $categoria = $stmtAtual->fetch();

    if ($categoria) {
        $novoStatus = (int) $categoria['ativo'] === 1 ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE categorias SET ativo = :ativo WHERE id = :id");
        $stmt->execute([':ativo' => $novoStatus, ':id' => $id]);
        registrarAuditoria($novoStatus ? 'ATIVAR' : 'INATIVAR', 'Categorias', $id, 'Status da categoria alterado: ' . $categoria['nome'] . '.', ['ativo' => (int) $categoria['ativo']], ['ativo' => $novoStatus]);
        definirMensagem('sucesso', 'Status da categoria atualizado com sucesso!');
    } else {
        definirMensagem('erro', 'Categoria não encontrada.');
    }
} else {
    definirMensagem('erro', 'Não foi possível processar a solicitação.');
}

header('Location: ' . BASE_URL . '/categories/list.php');
exit;

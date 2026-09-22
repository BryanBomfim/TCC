<?php
/** Alterna o status ativo/inativo de um fornecedor com auditoria. */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarTokenCSRF($_POST['csrf_token'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);
    $stmtAtual = $pdo->prepare("SELECT id, nome, ativo FROM fornecedores WHERE id = :id");
    $stmtAtual->execute([':id' => $id]);
    $fornecedor = $stmtAtual->fetch();

    if ($fornecedor) {
        $novoStatus = (int) $fornecedor['ativo'] === 1 ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE fornecedores SET ativo = :ativo WHERE id = :id");
        $stmt->execute([':ativo' => $novoStatus, ':id' => $id]);
        registrarAuditoria($novoStatus ? 'ATIVAR' : 'INATIVAR', 'Fornecedores', $id, 'Status do fornecedor alterado: ' . $fornecedor['nome'] . '.', ['ativo' => (int) $fornecedor['ativo']], ['ativo' => $novoStatus]);
        definirMensagem('sucesso', 'Status do fornecedor atualizado com sucesso!');
    } else {
        definirMensagem('erro', 'Fornecedor não encontrado.');
    }
} else {
    definirMensagem('erro', 'Não foi possível processar a solicitação.');
}

header('Location: ' . BASE_URL . '/suppliers/list.php');
exit;

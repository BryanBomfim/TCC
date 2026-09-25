<?php
/**
 * categories/toggle.php
 * Alterna o status ativo/inativo de uma categoria.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarTokenCSRF($_POST['csrf_token'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE categorias SET ativo = NOT ativo WHERE id = :id");
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() > 0) {
        registrarAuditoria('ALTERAR_STATUS', 'Categorias', $id, 'Status alterado.');
    }
    definirMensagem('sucesso', 'Status da categoria atualizado com sucesso!');
} else {
    definirMensagem('erro', 'Não foi possível processar a solicitação.');
}

header('Location: ' . BASE_URL . '/categories/list.php');
exit;

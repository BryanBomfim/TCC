<?php
/**
 * users/toggle.php
 * Alterna o status ativo/inativo de um usuário.
 * Um administrador não pode inativar a própria conta.
 */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarTokenCSRF($_POST['csrf_token'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id === (int) $_SESSION['usuario_id']) {
        definirMensagem('erro', 'Você não pode alterar o status da sua própria conta.');
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET ativo = NOT ativo WHERE id = :id");
        $stmt->execute([':id' => $id]);
        definirMensagem('sucesso', 'Status do usuário atualizado com sucesso!');
    }
} else {
    definirMensagem('erro', 'Não foi possível processar a solicitação.');
}

header('Location: ' . BASE_URL . '/users/list.php');
exit;

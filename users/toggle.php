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

    try {
        (new \SmartControl\Services\UserService($pdo))->alternar((int) $_SESSION['usuario_id'], $id);
        definirMensagem('sucesso', 'Status do usuário atualizado com sucesso!');
    } catch (DomainException $e) {
        definirMensagem('erro', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Falha ao alterar status do usuário: ' . $e->getCode());
        definirMensagem('erro', 'Não foi possível alterar o usuário.');
    }

} else {
    definirMensagem('erro', 'Não foi possível processar a solicitação.');
}

header('Location: ' . BASE_URL . '/users/list.php');
exit;

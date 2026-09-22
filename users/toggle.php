<?php
/** Alterna o status de um usuário sem permitir auto-inativação. */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarTokenCSRF($_POST['csrf_token'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id === (int) $_SESSION['usuario_id']) {
        definirMensagem('erro', 'Você não pode alterar o status da sua própria conta.');
    } else {
        $stmtAtual = $pdo->prepare("SELECT id, nome, perfil, ativo FROM usuarios WHERE id = :id");
        $stmtAtual->execute([':id' => $id]);
        $usuario = $stmtAtual->fetch();

        if (!$usuario) {
            definirMensagem('erro', 'Usuário não encontrado.');
        } else {
            $novoStatus = (int) $usuario['ativo'] === 1 ? 0 : 1;

            if (
                $novoStatus === 0
                && $usuario['perfil'] === 'administrador'
            ) {
                $totalAdmins = (int) $pdo->query("
                    SELECT COUNT(*)
                    FROM usuarios
                    WHERE perfil = 'administrador' AND ativo = 1
                ")->fetchColumn();

                if ($totalAdmins <= 1) {
                    definirMensagem(
                        'erro',
                        'O sistema precisa manter pelo menos um administrador ativo.'
                    );
                    header('Location: ' . BASE_URL . '/users/list.php');
                    exit;
                }
            }

            $stmt = $pdo->prepare("UPDATE usuarios SET ativo = :ativo WHERE id = :id");
            $stmt->execute([':ativo' => $novoStatus, ':id' => $id]);
            registrarAuditoria($novoStatus ? 'ATIVAR' : 'INATIVAR', 'Usuários', $id, 'Status do usuário alterado: ' . $usuario['nome'] . '.', ['ativo' => (int) $usuario['ativo']], ['ativo' => $novoStatus]);
            definirMensagem('sucesso', 'Status do usuário atualizado com sucesso!');
        }
    }
} else {
    definirMensagem('erro', 'Não foi possível processar a solicitação.');
}

header('Location: ' . BASE_URL . '/users/list.php');
exit;

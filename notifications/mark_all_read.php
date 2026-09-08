<?php
/**
 * notifications/mark_all_read.php
 * ---------------------------------------------------------------
 * Marca todas as notificações não lidas do usuário atual como lidas.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: '
        . BASE_URL
        . '/notifications/index.php'
    );

    exit;
}

/*
 * Proteção CSRF.
 */
if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

    definirMensagem(
        'erro',
        'Sessão expirada. Recarregue a página e tente novamente.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/notifications/index.php'
    );

    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];

try {

    $stmt = $pdo->prepare("
        UPDATE notificacoes

        SET lida_em = NOW()

        WHERE usuario_id = :usuario_id
          AND lida_em IS NULL
    ");

    $stmt->execute([
        ':usuario_id' => $usuarioId
    ]);

    $quantidade = $stmt->rowCount();

    if ($quantidade > 0) {

        definirMensagem(
            'sucesso',
            'Todas as notificações foram marcadas como lidas.'
        );

    } else {

        definirMensagem(
            'sucesso',
            'Você não possui notificações novas.'
        );
    }

} catch (Throwable $e) {

    error_log(
        'Erro ao marcar notificações como lidas: '
        . $e->getMessage()
    );

    definirMensagem(
        'erro',
        'Não foi possível atualizar as notificações.'
    );
}

header(
    'Location: '
    . BASE_URL
    . '/notifications/index.php'
);

exit;

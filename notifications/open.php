<?php
/**
 * notifications/open.php
 * ---------------------------------------------------------------
 * Marca uma notificação como lida e depois abre o destino dela.
 *
 * Segurança:
 * - exige login;
 * - aceita somente POST;
 * - valida CSRF;
 * - usuário só pode abrir a própria notificação;
 * - impede redirecionamento para sites externos.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

/*
 * ---------------------------------------------------------------
 * 1. Somente POST
 * ---------------------------------------------------------------
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: '
        . BASE_URL
        . '/notifications/index.php'
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 2. CSRF
 * ---------------------------------------------------------------
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

/*
 * ---------------------------------------------------------------
 * 3. Dados
 * ---------------------------------------------------------------
 */
$notificacaoId = (int) (
    $_POST['notificacao_id']
    ?? 0
);

$usuarioId = (int) $_SESSION['usuario_id'];

if ($notificacaoId <= 0) {

    definirMensagem(
        'erro',
        'Notificação inválida.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/notifications/index.php'
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 4. Busca a notificação
 * ---------------------------------------------------------------
 *
 * Importante:
 * Também verificamos usuario_id para impedir que alguém
 * altere o ID na requisição e leia notificação de outra pessoa.
 */
$stmt = $pdo->prepare("
    SELECT
        id,
        link,
        lida_em

    FROM notificacoes

    WHERE id = :id
      AND usuario_id = :usuario_id

    LIMIT 1
");

$stmt->execute([
    ':id' => $notificacaoId,
    ':usuario_id' => $usuarioId
]);

$notificacao = $stmt->fetch();

if (!$notificacao) {

    definirMensagem(
        'erro',
        'Notificação não encontrada.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/notifications/index.php'
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 5. Marca como lida
 * ---------------------------------------------------------------
 *
 * Só atualiza se ainda estiver não lida.
 * Assim preservamos a data da primeira leitura.
 */
if ($notificacao['lida_em'] === null) {

    $stmtUpdate = $pdo->prepare("
        UPDATE notificacoes

        SET lida_em = NOW()

        WHERE id = :id
          AND usuario_id = :usuario_id
          AND lida_em IS NULL
    ");

    $stmtUpdate->execute([
        ':id' => $notificacaoId,
        ':usuario_id' => $usuarioId
    ]);
}

/*
 * ---------------------------------------------------------------
 * 6. Define o destino
 * ---------------------------------------------------------------
 */
$destino =
    BASE_URL
    . '/notifications/index.php';

if (!empty($notificacao['link'])) {

    $link = (string) $notificacao['link'];

    /*
     * Só permitimos links internos do SmartControl.
     *
     * Exemplo permitido:
     * /smartcontrol/products/view.php?id=10
     *
     * Exemplo bloqueado:
     * https://site-malicioso.com
     */
    if (
        str_starts_with(
            $link,
            BASE_URL . '/'
        )
    ) {

        $destino = $link;
    }
}

/*
 * ---------------------------------------------------------------
 * 7. Abre o destino
 * ---------------------------------------------------------------
 */
header(
    'Location: '
    . $destino
);

exit;

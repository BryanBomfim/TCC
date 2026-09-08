<?php
/**
 * inventory/cancel.php
 * -----------------------------------------------------------------
 * Cancela um inventário físico ainda aberto.
 *
 * Somente administradores podem cancelar.
 *
 * O cancelamento:
 * - NÃO altera o estoque;
 * - NÃO cria movimentações;
 * - preserva as contagens já realizadas;
 * - registra a ação na auditoria.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirAdministrador();

/*
 * ================================================================
 * SOMENTE POST
 * ================================================================
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: '
        . BASE_URL
        . '/inventory/index.php'
    );

    exit;
}

/*
 * ================================================================
 * CSRF
 * ================================================================
 */
if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

    definirMensagem(
        'erro',
        'Sessão expirada. Recarregue a página e tente novamente.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/inventory/index.php'
    );

    exit;
}

/*
 * ================================================================
 * ID DO INVENTÁRIO
 * ================================================================
 */
$inventarioId = (int) (
    $_POST['inventario_id']
    ?? 0
);

if ($inventarioId <= 0) {

    definirMensagem(
        'erro',
        'Inventário inválido.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/inventory/index.php'
    );

    exit;
}

try {

    $pdo->beginTransaction();

    /*
     * ============================================================
     * BUSCA E BLOQUEIA O INVENTÁRIO
     * ============================================================
     */
    $stmtInventario = $pdo->prepare("
        SELECT
            id,
            titulo,
            status

        FROM inventarios

        WHERE id = :id

        LIMIT 1

        FOR UPDATE
    ");

    $stmtInventario->execute([
        ':id' => $inventarioId
    ]);

    $inventario =
        $stmtInventario->fetch();

    if (!$inventario) {

        throw new RuntimeException(
            'Inventário não encontrado.'
        );
    }

    if ($inventario['status'] !== 'aberto') {

        throw new RuntimeException(
            'Somente inventários abertos podem ser cancelados.'
        );
    }

    /*
     * ============================================================
     * CANCELA
     * ============================================================
     */
    $stmtCancelar = $pdo->prepare("
        UPDATE inventarios

        SET status = 'cancelado'

        WHERE id = :id
          AND status = 'aberto'
    ");

    $stmtCancelar->execute([
        ':id' => $inventarioId
    ]);

    /*
     * ============================================================
     * AUDITORIA
     * ============================================================
     */
    registrarAuditoria(
        'CANCELAR',
        'Inventários',
        $inventarioId,
        'Inventário "'
        . $inventario['titulo']
        . '" cancelado.',
        [
            'status' => 'aberto'
        ],
        [
            'status' => 'cancelado'
        ]
    );

    $pdo->commit();

    definirMensagem(
        'sucesso',
        'Inventário cancelado com sucesso. Nenhuma alteração foi feita no estoque.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/inventory/view.php?id='
        . $inventarioId
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao cancelar inventário: '
        . $e->getMessage()
    );

    definirMensagem(
        'erro',
        $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Ocorreu um erro ao cancelar o inventário.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/inventory/view.php?id='
        . $inventarioId
    );

    exit;
}

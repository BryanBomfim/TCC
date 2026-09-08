<?php
/**
 * attachments/delete.php
 * ---------------------------------------------------------------
 * Exclui um anexo de produto.
 *
 * Somente administradores podem excluir.
 *
 * Segurança:
 * - exige administrador;
 * - aceita somente POST;
 * - valida CSRF;
 * - busca o anexo no banco;
 * - remove o registro;
 * - registra auditoria;
 * - remove o arquivo físico.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirAdministrador();

/*
 * ---------------------------------------------------------------
 * 1. Somente POST
 * ---------------------------------------------------------------
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: '
        . BASE_URL
        . '/products/list.php'
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
        . '/products/list.php'
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 3. ID do anexo
 * ---------------------------------------------------------------
 */
$anexoId = (int) ($_POST['anexo_id'] ?? 0);

if ($anexoId <= 0) {

    definirMensagem(
        'erro',
        'Anexo inválido.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/list.php'
    );

    exit;
}

try {

    $pdo->beginTransaction();

    /*
     * -----------------------------------------------------------
     * 4. Busca e bloqueia o registro
     * -----------------------------------------------------------
     */
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.entidade,
            a.entidade_id,
            a.nome_original,
            a.nome_arquivo,
            a.tipo_mime,
            a.tamanho,
            p.nome AS produto_nome

        FROM anexos a

        LEFT JOIN produtos p
            ON p.id = a.entidade_id
            AND a.entidade = 'produto'

        WHERE a.id = :id

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        ':id' => $anexoId
    ]);

    $anexo = $stmt->fetch();

    if (!$anexo) {

        $pdo->rollBack();

        definirMensagem(
            'erro',
            'Anexo não encontrado.'
        );

        header(
            'Location: '
            . BASE_URL
            . '/products/list.php'
        );

        exit;
    }

    /*
     * Atualmente trabalhamos somente
     * com anexos de produtos.
     */
    if ($anexo['entidade'] !== 'produto') {

        $pdo->rollBack();

        definirMensagem(
            'erro',
            'Este tipo de anexo não pode ser removido aqui.'
        );

        header(
            'Location: '
            . BASE_URL
            . '/products/list.php'
        );

        exit;
    }

    $produtoId =
        (int) $anexo['entidade_id'];

    /*
     * -----------------------------------------------------------
     * 5. Exclui o registro do banco
     * -----------------------------------------------------------
     */
    $stmtDelete = $pdo->prepare("
        DELETE FROM anexos

        WHERE id = :id
    ");

    $stmtDelete->execute([
        ':id' => $anexoId
    ]);

    /*
     * -----------------------------------------------------------
     * 6. Auditoria
     * -----------------------------------------------------------
     */
    registrarAuditoria(
        'REMOVER_ANEXO',
        'Produtos',
        $produtoId,
        'Anexo "'
        . $anexo['nome_original']
        . '" removido do produto "'
        . ($anexo['produto_nome'] ?? 'Produto')
        . '".',
        [
            'anexo_id' =>
                $anexoId,

            'arquivo' =>
                $anexo['nome_original'],

            'tipo' =>
                $anexo['tipo_mime'],

            'tamanho' =>
                (int) $anexo['tamanho']
        ],
        null
    );

    /*
     * Confirma banco + auditoria.
     */
    $pdo->commit();

    /*
     * -----------------------------------------------------------
     * 7. Remove o arquivo físico
     * -----------------------------------------------------------
     */
    $caminhoArquivo =
        __DIR__
        . '/../storage/uploads/products/'
        . $anexo['nome_arquivo'];

    if (is_file($caminhoArquivo)) {

        if (!unlink($caminhoArquivo)) {

            /*
             * O usuário não precisa receber erro porque
             * o registro já foi removido corretamente.
             *
             * Registramos no log do PHP para manutenção.
             */
            error_log(
                'Não foi possível remover o arquivo físico do anexo '
                . $anexoId
                . ': '
                . $caminhoArquivo
            );
        }
    }

    definirMensagem(
        'sucesso',
        'Anexo removido com sucesso!'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/view.php?id='
        . $produtoId
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Erro ao excluir anexo: '
        . $e->getMessage()
    );

    definirMensagem(
        'erro',
        'Ocorreu um erro ao remover o anexo.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/list.php'
    );

    exit;
}

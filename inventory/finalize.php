<?php
/**
 * inventory/finalize.php
 * -----------------------------------------------------------------
 * Finaliza um inventário físico.
 *
 * Somente administradores podem finalizar.
 *
 * Ao finalizar:
 * - exige que todos os produtos tenham sido contados;
 * - verifica se o estoque não mudou durante o inventário;
 * - gera entradas/saídas para as divergências;
 * - atualiza o estoque;
 * - registra as movimentações;
 * - registra auditoria;
 * - finaliza o inventário.
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
 * INVENTÁRIO
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
     * 1. BLOQUEIA O INVENTÁRIO
     * ============================================================
     */

    $stmtInventario = $pdo->prepare("
        SELECT
            id,
            titulo,
            status,
            criado_em

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
            'Este inventário não está mais aberto.'
        );
    }

    /*
     * ============================================================
     * 2. BUSCA TODOS OS ITENS
     * ============================================================
     */

    $stmtItens = $pdo->prepare("
        SELECT
            ii.id,
            ii.produto_id,
            ii.estoque_sistema,
            ii.quantidade_contada,
            ii.diferenca,

            p.nome AS produto_nome,
            p.unidade

        FROM inventario_itens ii

        INNER JOIN produtos p
            ON p.id = ii.produto_id

        WHERE ii.inventario_id = :inventario_id

        ORDER BY p.nome ASC
    ");

    $stmtItens->execute([
        ':inventario_id' => $inventarioId
    ]);

    $itens =
        $stmtItens->fetchAll();

    if (empty($itens)) {

        throw new RuntimeException(
            'Este inventário não possui produtos.'
        );
    }

    /*
     * ============================================================
     * 3. TODOS PRECISAM TER SIDO CONTADOS
     * ============================================================
     */

    foreach ($itens as $item) {

        if ($item['quantidade_contada'] === null) {

            throw new RuntimeException(
                'Ainda existem produtos não contados. '
                . 'Finalize a contagem antes de concluir o inventário.'
            );
        }
    }

    /*
     * ============================================================
     * 4. PRIMEIRO VERIFICAMOS SE O ESTOQUE MUDOU
     * ============================================================
     *
     * Antes de fazer qualquer ajuste, bloqueamos cada produto
     * e verificamos se o estoque atual ainda é o mesmo que existia
     * quando o inventário foi iniciado.
     *
     * Isso evita sobrescrever entradas/saídas feitas durante
     * a contagem.
     */

    $produtosAtuais = [];

    $stmtProduto = $pdo->prepare("
        SELECT
            id,
            nome,
            quantidade_estoque,
            estoque_minimo,
            unidade

        FROM produtos

        WHERE id = :id

        LIMIT 1

        FOR UPDATE
    ");

    foreach ($itens as $item) {

        $produtoId =
            (int) $item['produto_id'];

        $stmtProduto->execute([
            ':id' => $produtoId
        ]);

        $produtoAtual =
            $stmtProduto->fetch();

        if (!$produtoAtual) {

            throw new RuntimeException(
                'Um dos produtos do inventário não foi encontrado.'
            );
        }

        $estoqueInicial =
            (int) $item['estoque_sistema'];

        $estoqueAtual =
            (int) $produtoAtual['quantidade_estoque'];

        /*
         * Se forem diferentes, houve movimentação de estoque
         * durante o inventário.
         */
        if ($estoqueAtual !== $estoqueInicial) {

            throw new RuntimeException(
                'O estoque do produto "'
                . $produtoAtual['nome']
                . '" mudou durante o inventário. '
                . 'Estoque no início: '
                . $estoqueInicial
                . '. Estoque atual: '
                . $estoqueAtual
                . '. Revise o inventário antes de finalizar.'
            );
        }

        $produtosAtuais[$produtoId] =
            $produtoAtual;
    }

    /*
     * ============================================================
     * 5. PREPARED STATEMENTS DOS AJUSTES
     * ============================================================
     */

    $stmtMovimentacao = $pdo->prepare("
        INSERT INTO movimentacoes
        (
            produto_id,
            usuario_id,
            tipo,
            quantidade,
            saldo_anterior,
            saldo_posterior,
            origem,
            motivo,
            referencia,
            observacao
        )
        VALUES
        (
            :produto_id,
            :usuario_id,
            :tipo,
            :quantidade,
            :saldo_anterior,
            :saldo_posterior,
            'inventario',
            'Ajuste de inventário',
            :referencia,
            :observacao
        )
    ");

    $stmtAtualizarProduto = $pdo->prepare("
        UPDATE produtos

        SET quantidade_estoque = :quantidade

        WHERE id = :id
    ");

    $stmtAtualizarItem = $pdo->prepare("
        UPDATE inventario_itens

        SET ajustado = 1

        WHERE id = :id
          AND inventario_id = :inventario_id
    ");

    /*
     * ============================================================
     * 6. PROCESSA AS DIVERGÊNCIAS
     * ============================================================
     */

    $totalEntradas = 0;
    $totalSaidas = 0;
    $totalSemDiferenca = 0;

    $unidadesEntrada = 0;
    $unidadesSaida = 0;

    $divergenciasAuditoria = [];

    foreach ($itens as $item) {

        $itemId =
            (int) $item['id'];

        $produtoId =
            (int) $item['produto_id'];

        $estoqueSistema =
            (int) $item['estoque_sistema'];

        $quantidadeContada =
            (int) $item['quantidade_contada'];

        $diferenca =
            $quantidadeContada
            - $estoqueSistema;

        /*
         * --------------------------------------------------------
         * DIFERENÇA POSITIVA = ENTRADA
         * --------------------------------------------------------
         */
        if ($diferenca > 0) {

            $quantidadeAjuste =
                $diferenca;

            $stmtMovimentacao->execute([

                ':produto_id' =>
                    $produtoId,

                ':usuario_id' =>
                    (int) $_SESSION['usuario_id'],

                ':tipo' =>
                    'entrada',

                ':quantidade' =>
                    $quantidadeAjuste,

                ':saldo_anterior' =>
                    $estoqueSistema,

                ':saldo_posterior' =>
                    $quantidadeContada,

                ':referencia' =>
                    'Inventário #' . $inventarioId,

                ':observacao' =>
                    'Ajuste do inventário #'
                    . $inventarioId
                    . ' - '
                    . $inventario['titulo']
            ]);

            $totalEntradas++;

            $unidadesEntrada +=
                $quantidadeAjuste;

            /*
             * --------------------------------------------------------
             * DIFERENÇA NEGATIVA = SAÍDA
             * --------------------------------------------------------
             */
        } elseif ($diferenca < 0) {

            $quantidadeAjuste =
                abs($diferenca);

            $stmtMovimentacao->execute([

                ':produto_id' =>
                    $produtoId,

                ':usuario_id' =>
                    (int) $_SESSION['usuario_id'],

                ':tipo' =>
                    'saida',

                ':quantidade' =>
                    $quantidadeAjuste,

                ':saldo_anterior' =>
                    $estoqueSistema,

                ':saldo_posterior' =>
                    $quantidadeContada,

                ':referencia' =>
                    'Inventário #' . $inventarioId,

                ':observacao' =>
                    'Ajuste do inventário #'
                    . $inventarioId
                    . ' - '
                    . $inventario['titulo']
            ]);

            $totalSaidas++;

            $unidadesSaida +=
                $quantidadeAjuste;

            /*
             * --------------------------------------------------------
             * SEM DIVERGÊNCIA
             * --------------------------------------------------------
             */
        } else {

            $totalSemDiferenca++;
        }

        /*
         * O estoque final passa a ser exatamente
         * a quantidade física contada.
         */
        $stmtAtualizarProduto->execute([

            ':quantidade' =>
                $quantidadeContada,

            ':id' =>
                $produtoId
        ]);

        /*
         * Marca o item como processado.
         */
        $stmtAtualizarItem->execute([

            ':id' =>
                $itemId,

            ':inventario_id' =>
                $inventarioId
        ]);

        /*
         * Só adiciona ao resumo da auditoria
         * quando houve divergência.
         */
        if ($diferenca !== 0) {

            $divergenciasAuditoria[] = [

                'produto_id' =>
                    $produtoId,

                'produto' =>
                    $item['produto_nome'],

                'estoque_sistema' =>
                    $estoqueSistema,

                'quantidade_contada' =>
                    $quantidadeContada,

                'diferenca' =>
                    $diferenca
            ];
        }
    }

    /*
     * ============================================================
     * 7. FINALIZA O INVENTÁRIO
     * ============================================================
     */

    $stmtFinalizar = $pdo->prepare("
        UPDATE inventarios

        SET
            status = 'finalizado',
            finalizado_por = :finalizado_por,
            finalizado_por_nome = :finalizado_por_nome,
            finalizado_em = NOW()

        WHERE id = :id
          AND status = 'aberto'
    ");

    $stmtFinalizar->execute([

        ':finalizado_por' =>
            (int) $_SESSION['usuario_id'],

        ':finalizado_por_nome' =>
            $_SESSION['usuario_nome']
            ?? 'Administrador',

        ':id' =>
            $inventarioId
    ]);

    /*
     * ============================================================
     * 8. AUDITORIA
     * ============================================================
     */

    registrarAuditoria(
        'FINALIZAR',
        'Inventários',
        $inventarioId,
        'Inventário "'
        . $inventario['titulo']
        . '" finalizado.',
        [
            'status' =>
                'aberto'
        ],
        [
            'status' =>
                'finalizado',

            'total_produtos' =>
                count($itens),

            'produtos_com_entrada' =>
                $totalEntradas,

            'produtos_com_saida' =>
                $totalSaidas,

            'produtos_sem_diferenca' =>
                $totalSemDiferenca,

            'unidades_entrada' =>
                $unidadesEntrada,

            'unidades_saida' =>
                $unidadesSaida,

            'divergencias' =>
                $divergenciasAuditoria
        ]
    );

    /*
     * ============================================================
     * 9. CONFIRMA TUDO
     * ============================================================
     */

    $pdo->commit();

    definirMensagem(
        'sucesso',
        'Inventário finalizado com sucesso! '
        . 'Entradas de ajuste: '
        . $totalEntradas
        . '. Saídas de ajuste: '
        . $totalSaidas
        . '.'
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
        'Erro ao finalizar inventário: '
        . $e->getMessage()
    );

    if ($e instanceof RuntimeException) {

        definirMensagem(
            'erro',
            $e->getMessage()
        );

    } else {

        definirMensagem(
            'erro',
            'Ocorreu um erro ao finalizar o inventário.'
        );
    }

    header(
        'Location: '
        . BASE_URL
        . '/inventory/view.php?id='
        . $inventarioId
    );

    exit;
}

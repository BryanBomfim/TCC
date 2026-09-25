<?php
/**
 * inventory/view.php
 * -----------------------------------------------------------------
 * Tela de contagem de um inventário físico.
 *
 * Permite:
 * - visualizar estoque registrado no início do inventário;
 * - informar a quantidade física contada;
 * - calcular automaticamente a diferença;
 * - salvar parcialmente a contagem;
 * - identificar quem realizou a contagem;
 * - finalizar inventário;
 * - cancelar inventário.
 *
 * O estoque real do produto NÃO é alterado durante a contagem.
 * O ajuste é feito somente na finalização do inventário.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

/*
 * ================================================================
 * INVENTÁRIO
 * ================================================================
 */

$inventarioId = (int) (
    $_GET['id']
    ?? $_POST['inventario_id']
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

$stmtInventario = $pdo->prepare("
    SELECT
        id,
        titulo,
        status,
        revisao,
        observacao,
        criado_por,
        criado_por_nome,
        finalizado_por,
        finalizado_por_nome,
        criado_em,
        finalizado_em

    FROM inventarios

    WHERE id = :id

    LIMIT 1
");

$stmtInventario->execute([
    ':id' => $inventarioId
]);

$inventario = $stmtInventario->fetch();

if (!$inventario) {

    definirMensagem(
        'erro',
        'Inventário não encontrado.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/inventory/index.php'
    );

    exit;
}

$erro = '';

/*
 * ================================================================
 * SALVAR CONTAGEM
 * ================================================================
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $erro =
            'Sessão expirada. Recarregue a página e tente novamente.';

    } elseif ($inventario['status'] !== 'aberto') {

        $erro =
            'Este inventário não está aberto para contagem.';

    } else {

        $quantidades =
            $_POST['quantidade']
            ?? [];

        if (!is_array($quantidades)) {

            $erro =
                'Dados da contagem inválidos.';

        } else {

            try {

                $pdo->beginTransaction();

                /*
                 * Bloqueia o inventário durante o salvamento.
                 */
                $stmtBloqueio = $pdo->prepare("
                    SELECT
                        id,
                        status, revisao

                    FROM inventarios

                    WHERE id = :id

                    LIMIT 1

                    FOR UPDATE
                ");

                $stmtBloqueio->execute([
                    ':id' => $inventarioId
                ]);

                $inventarioBloqueado =
                    $stmtBloqueio->fetch();

                if (
                    !$inventarioBloqueado
                    ||
                    $inventarioBloqueado['status'] !== 'aberto'
                ) {

                    throw new DomainException(
                        'O inventário não está mais aberto.'
                    );
                }

                if ((string) $inventarioBloqueado['revisao'] !== (string) ($_POST['revisao'] ?? '')) {
                    throw new DomainException('Outra pessoa atualizou este inventário. Recarregue a página antes de salvar.');
                }

                /*
                 * Busca os itens do inventário.
                 */
                $stmtItensBanco = $pdo->prepare("
                    SELECT
                        id,
                        produto_id,
                        estoque_sistema,
                        quantidade_contada

                    FROM inventario_itens

                    WHERE inventario_id = :inventario_id
                ");

                $stmtItensBanco->execute([
                    ':inventario_id' => $inventarioId
                ]);

                $itensBanco =
                    $stmtItensBanco->fetchAll();

                /*
                 * Atualiza os itens da contagem.
                 */
                $stmtAtualizar = $pdo->prepare("
                    UPDATE inventario_itens

                    SET
                        quantidade_contada = :quantidade_contada,
                        diferenca = :diferenca,
                        contado_por = :contado_por,
                        contado_por_nome = :contado_por_nome,
                        contado_em = :contado_em

                    WHERE id = :id
                      AND inventario_id = :inventario_id
                ");

                $totalPreenchidos = 0;

                foreach ($itensBanco as $item) {

                    $itemId =
                        (int) $item['id'];

                    if (!array_key_exists($itemId, $quantidades)) {
                        continue; // POST parcial jamais apaga contagens não enviadas.
                    }

                    $valorRecebido =
                        isset($quantidades[$itemId])
                        ? trim(
                            (string) $quantidades[$itemId]
                        )
                        : '';

                    /*
                     * Campo vazio = ainda não contado.
                     */
                    if ($valorRecebido === '') {

                        $stmtAtualizar->execute([

                            ':quantidade_contada' =>
                                null,

                            ':diferenca' =>
                                null,

                            ':contado_por' =>
                                null,

                            ':contado_por_nome' =>
                                null,

                            ':contado_em' =>
                                null,

                            ':id' =>
                                $itemId,

                            ':inventario_id' =>
                                $inventarioId
                        ]);

                        continue;
                    }

                    /*
                     * Somente inteiros >= 0.
                     */
                    if (
                        !preg_match(
                            '/^\d+$/',
                            $valorRecebido
                        )
                    ) {

                        throw new DomainException(
                            'Existe uma quantidade inválida na contagem.'
                        );
                    }

                    $quantidadeContada = quantidadeInteira($valorRecebido);
                    if ($quantidadeContada === null) {
                        throw new DomainException('Contagem fora do limite permitido.');
                    }

                    $estoqueSistema =
                        (int) $item['estoque_sistema'];

                    /*
                     * Exemplo:
                     * sistema = 20
                     * contado = 17
                     * diferença = -3
                     */
                    $diferenca =
                        $quantidadeContada
                        - $estoqueSistema;

                    $stmtAtualizar->execute([

                        ':quantidade_contada' =>
                            $quantidadeContada,

                        ':diferenca' =>
                            $diferenca,

                        ':contado_por' =>
                            (int) $_SESSION['usuario_id'],

                        ':contado_por_nome' =>
                            $_SESSION['usuario_nome']
                            ?? 'Usuário',

                        ':contado_em' =>
                            date('Y-m-d H:i:s'),

                        ':id' =>
                            $itemId,

                        ':inventario_id' =>
                            $inventarioId
                    ]);

                    $totalPreenchidos++;
                }

                /*
                 * Auditoria resumida.
                 */
                registrarAuditoria(
                    'CONTAGEM',
                    'Inventários',
                    $inventarioId,
                    'Contagem do inventário "'
                    . $inventario['titulo']
                    . '" atualizada.',
                    null,
                    [
                        'itens_preenchidos' =>
                            $totalPreenchidos
                    ]
                );

                $stmtRevisao = $pdo->prepare('UPDATE inventarios SET revisao = revisao + 1 WHERE id = :id');
                $stmtRevisao->execute([':id' => $inventarioId]);
                $pdo->commit();

                definirMensagem(
                    'sucesso',
                    'Contagem salva com sucesso!'
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
                    'Erro ao salvar inventário: '
                    . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine()
                );

                if ($e instanceof DomainException) {

                    $erro =
                        $e->getMessage();

                } else {

                    $erro =
                        'Ocorreu um erro ao salvar a contagem.';
                }
            }
        }
    }
}

/*
 * ================================================================
 * ITENS DO INVENTÁRIO
 * ================================================================
 */

$stmtResumo = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(quantidade_contada IS NOT NULL), 0) AS contados, COALESCE(SUM(diferenca <> 0), 0) AS divergencias FROM inventario_itens WHERE inventario_id = :id');
$stmtResumo->execute([':id' => $inventarioId]);
$resumo = $stmtResumo->fetch();
$paginacao = calcularPaginacao((int) $resumo['total']);

$stmtItens = $pdo->prepare("
    SELECT
        ii.id,
        ii.produto_id,
        ii.estoque_sistema,
        ii.quantidade_contada,
        ii.diferenca,
        ii.contado_por_nome,
        ii.contado_em,
        ii.ajustado,

        p.nome AS produto_nome,
        p.sku,
        p.codigo_barras,
        p.unidade

    FROM inventario_itens ii

    INNER JOIN produtos p
        ON p.id = ii.produto_id

    WHERE ii.inventario_id = :inventario_id

    ORDER BY p.nome ASC, p.id ASC
    LIMIT {$paginacao['por_pagina']} OFFSET {$paginacao['offset']}
");

$stmtItens->execute([
    ':inventario_id' => $inventarioId
]);

$itens =
    $stmtItens->fetchAll();

/*
 * ================================================================
 * RESUMO
 * ================================================================
 */

$totalItens = (int) $resumo['total'];
$totalContados = (int) $resumo['contados'];
$totalDivergencias = (int) $resumo['divergencias'];

$todosContados =
    $totalItens > 0
    &&
    $totalContados === $totalItens;

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="conteudo" id="conteudo-principal" tabindex="-1">

    <!-- =========================================================
         TOPO
         ========================================================= -->

    <div class="topo-pagina">

        <div>

            <h1>
                <?php echo limpar($inventario['titulo']); ?>
            </h1>

            <p>

                Inventário #<?php echo (int) $inventario['id']; ?>

                ·

                Criado em

                <?php
                echo formatarDataHora(
                    $inventario['criado_em']
                );
                ?>

            </p>

        </div>

        <a href="<?php echo BASE_URL; ?>/inventory/index.php" class="botao botao-secundario">
            Voltar
        </a>

    </div>

    <?php if ($inventario['status'] === 'aberto' && ehAdministrador()): ?>
    <div class="painel">
        <h2>Revisar saldos e recontar</h2>
        <p>Se houve movimentações durante a contagem, atualize a referência. Apenas os produtos com movimentações posteriores terão a contagem limpa para uma nova conferência física. Os dados anteriores ficam na auditoria.</p>
        <form method="POST" action="<?php echo BASE_URL; ?>/inventory/review.php" data-confirm="Revisar os saldos e limpar as contagens dos produtos que tiveram movimentações?">
            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
            <input type="hidden" name="inventario_id" value="<?php echo $inventarioId; ?>">
            <button class="botao botao-secundario" type="submit">Revisar saldos e recontar</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- =========================================================
         STATUS
         ========================================================= -->

    <div class="painel">

        <p>

            <strong>
                Status:
            </strong>

            <?php if ($inventario['status'] === 'aberto'): ?>

                <span class="etiqueta etiqueta-ativo">
                    Aberto
                </span>

            <?php elseif ($inventario['status'] === 'finalizado'): ?>

                <span class="etiqueta">
                    Finalizado
                </span>

            <?php else: ?>

                <span class="etiqueta etiqueta-inativo">
                    Cancelado
                </span>

            <?php endif; ?>

        </p>

        <p>

            <strong>
                Criado por:
            </strong>

            <?php
            echo limpar(
                $inventario['criado_por_nome']
                ?? 'Usuário removido'
            );
            ?>

        </p>

        <?php if (!empty($inventario['observacao'])): ?>

            <p>

                <strong>
                    Observação:
                </strong>

                <?php
                echo limpar(
                    $inventario['observacao']
                );
                ?>

            </p>

        <?php endif; ?>

        <?php if ($inventario['status'] === 'finalizado'): ?>

            <p>

                <strong>
                    Finalizado por:
                </strong>

                <?php
                echo limpar(
                    $inventario['finalizado_por_nome']
                    ?? 'Usuário removido'
                );
                ?>

            </p>

            <?php if (!empty($inventario['finalizado_em'])): ?>

                <p>

                    <strong>
                        Finalizado em:
                    </strong>

                    <?php
                    echo formatarDataHora(
                        $inventario['finalizado_em']
                    );
                    ?>

                </p>

            <?php endif; ?>

        <?php endif; ?>

    </div>

    <!-- =========================================================
         RESUMO
         ========================================================= -->

    <div class="painel">

        <h2>
            Resumo da Contagem
        </h2>

        <p>

            <strong>
                Produtos:
            </strong>

            <?php echo $totalItens; ?>

        </p>

        <p>

            <strong>
                Contados:
            </strong>

            <?php echo $totalContados; ?>
            /
            <?php echo $totalItens; ?>

        </p>

        <p>

            <strong>
                Divergências encontradas:
            </strong>

            <?php echo $totalDivergencias; ?>

        </p>

    </div>

    <!-- =========================================================
         ERROS
         ========================================================= -->

    <?php if ($erro): ?>

        <div class="alerta alerta-erro" role="alert">
            <?php echo limpar($erro); ?>
        </div>

    <?php endif; ?>

    <!-- =========================================================
         CONTAGEM
         ========================================================= -->

    <form method="POST" action="">

        <input type="hidden" name="revisao" value="<?php echo (int) $inventario['revisao']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <input type="hidden" name="inventario_id" value="<?php echo $inventarioId; ?>">

        <table class="tabela">

            <thead>

                <tr>

                    <th scope="col">
                        SKU
                    </th>

                    <th scope="col">
                        Produto
                    </th>

                    <th scope="col">
                        Unidade
                    </th>

                    <th scope="col">
                        Estoque Sistema
                    </th>

                    <th scope="col">
                        Contagem Física
                    </th>

                    <th scope="col">
                        Diferença
                    </th>

                    <th scope="col">
                        Contado por
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($itens as $item): ?>

                    <?php

                    $diferenca =
                        $item['diferenca'] !== null
                        ? (int) $item['diferenca']
                        : null;

                    ?>

                    <tr class="<?php
                    echo $diferenca !== null
                        && $diferenca !== 0
                        ? 'linha-alerta'
                        : '';
                    ?>">

                        <!-- SKU -->

                        <td>

                            <?php
                            echo limpar(
                                $item['sku']
                                ?? '-'
                            );
                            ?>

                        </td>

                        <!-- Produto -->

                        <td>

                            <strong>

                                <?php
                                echo limpar(
                                    $item['produto_nome']
                                );
                                ?>

                            </strong>

                            <?php if (!empty($item['codigo_barras'])): ?>

                                <br>

                                <small>

                                    Código:

                                    <?php
                                    echo limpar(
                                        $item['codigo_barras']
                                    );
                                    ?>

                                </small>

                            <?php endif; ?>

                        </td>

                        <!-- Unidade -->

                        <td>

                            <?php
                            echo limpar(
                                $item['unidade']
                            );
                            ?>

                        </td>

                        <!-- Estoque sistema -->

                        <td>

                            <?php
                            echo (int) $item['estoque_sistema'];
                            ?>

                        </td>

                        <!-- Contagem física -->

                        <td>

                            <?php if ($inventario['status'] === 'aberto'): ?>

                                <input type="number" name="quantidade[<?php echo (int) $item['id']; ?>]" min="0" step="1" value="<?php
                                    echo $item['quantidade_contada'] !== null
                                        ? (int) $item['quantidade_contada']
                                        : '';
                                    ?>" class="campo-quantidade-compacto">

                            <?php else: ?>

                                <?php
                                echo $item['quantidade_contada'] !== null
                                    ? (int) $item['quantidade_contada']
                                    : '-';
                                ?>

                            <?php endif; ?>

                        </td>

                        <!-- Diferença -->

                        <td>

                            <?php if ($diferenca === null): ?>

                                —

                            <?php elseif ($diferenca > 0): ?>

                                <strong>
                                    +<?php echo $diferenca; ?>
                                </strong>

                            <?php elseif ($diferenca < 0): ?>

                                <strong>
                                    <?php echo $diferenca; ?>
                                </strong>

                            <?php else: ?>

                                0

                            <?php endif; ?>

                        </td>

                        <!-- Contado por -->

                        <td>

                            <?php if (!empty($item['contado_por_nome'])): ?>

                                <?php
                                echo limpar(
                                    $item['contado_por_nome']
                                );
                                ?>

                                <?php if (!empty($item['contado_em'])): ?>

                                    <br>

                                    <small>

                                        <?php
                                        echo formatarDataHora(
                                            $item['contado_em']
                                        );
                                        ?>

                                    </small>

                                <?php endif; ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>
        <p>Salve a contagem desta página antes de navegar para outra.</p>

        <?php if ($inventario['status'] === 'aberto'): ?>

            <div class="acoes-formulario">

                <button type="submit" class="botao">
                    Salvar Contagem
                </button>

            </div>

        <?php endif; ?>

    </form>
    <?php exibirPaginacao($paginacao); ?>

    <br>

    <!-- =========================================================
         GERENCIAMENTO DO INVENTÁRIO
         ========================================================= -->

    <?php
    if (
        $inventario['status'] === 'aberto'
        &&
        ehAdministrador()
    ):
        ?>

        <div class="painel">

            <h2>
                Gerenciar Inventário
            </h2>

            <!-- =================================================
                 FINALIZAÇÃO
                 ================================================= -->

            <?php if (!$todosContados): ?>

                <p>
                    Ainda faltam
                    <strong>
                        <?php echo $totalItens - $totalContados; ?>
                    </strong>
                    produto(s) para contar.
                </p>

                <p>
                    O inventário só poderá ser finalizado quando todos os produtos tiverem sido contados.
                </p>

            <?php else: ?>

                <p>
                    Todos os produtos foram contados.
                </p>

                <p>
                    Foram encontradas
                    <strong>
                        <?php echo $totalDivergencias; ?>
                    </strong>
                    divergência(s).
                </p>

                <form method="POST" action="<?php echo BASE_URL; ?>/inventory/finalize.php"
                    data-confirm="Tem certeza que deseja finalizar este inventário? Os ajustes serão aplicados ao estoque e esta ação não poderá ser desfeita.">

                    <input type="hidden" name="revisao" value="<?php echo (int) $inventario['revisao']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

                    <input type="hidden" name="inventario_id" value="<?php echo $inventarioId; ?>">

                    <button type="submit" class="botao botao-alerta">
                        Finalizar Inventário
                    </button>

                </form>

            <?php endif; ?>

            <br>

            <!-- =================================================
                 CANCELAMENTO
                 ================================================= -->

            <form method="POST" action="<?php echo BASE_URL; ?>/inventory/cancel.php"
                data-confirm="Tem certeza que deseja cancelar este inventário? Nenhum ajuste será aplicado ao estoque.">

                <input type="hidden" name="revisao" value="<?php echo (int) $inventario['revisao']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

                <input type="hidden" name="inventario_id" value="<?php echo $inventarioId; ?>">

                <button type="submit" class="botao botao-secundario">
                    Cancelar Inventário
                </button>

            </form>

        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

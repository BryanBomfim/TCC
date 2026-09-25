<?php
/**
 * inventory/index.php
 * ---------------------------------------------------------------
 * Lista os inventários físicos do SmartControl.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

/*
 * Busca os inventários e algumas informações resumidas.
 */
$stmt = $pdo->query("
    SELECT
        i.id,
        i.titulo,
        i.status,
        i.observacao,
        i.criado_por_nome,
        i.finalizado_por_nome,
        i.criado_em,
        i.finalizado_em,

        COUNT(ii.id) AS total_itens,

        SUM(
            CASE
                WHEN ii.quantidade_contada IS NOT NULL
                THEN 1
                ELSE 0
            END
        ) AS itens_contados

    FROM inventarios i

    LEFT JOIN inventario_itens ii
        ON ii.inventario_id = i.id

    GROUP BY
        i.id,
        i.titulo,
        i.status,
        i.observacao,
        i.criado_por_nome,
        i.finalizado_por_nome,
        i.criado_em,
        i.finalizado_em

    ORDER BY i.criado_em DESC, i.id DESC
");

$inventarios = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="conteudo" id="conteudo-principal" tabindex="-1">

    <div class="topo-pagina">

        <div>

            <h1>
                Inventário Físico
            </h1>

            <p>
                Compare o estoque registrado no sistema com a contagem física real.
            </p>

        </div>

        <?php if (ehAdministrador()): ?>

            <a
                href="<?php echo BASE_URL; ?>/inventory/create.php"
                class="botao"
            >
                + Novo Inventário
            </a>

        <?php endif; ?>

    </div>

    <?php if (empty($inventarios)): ?>

        <div class="painel">

            <p class="texto-vazio">
                Nenhum inventário foi criado ainda.
            </p>

        </div>

    <?php else: ?>

        <table class="tabela">

            <thead>

                <tr>

                    <th scope="col">
                        #
                    </th>

                    <th scope="col">
                        Inventário
                    </th>

                    <th scope="col">
                        Status
                    </th>

                    <th scope="col">
                        Progresso
                    </th>

                    <th scope="col">
                        Criado por
                    </th>

                    <th scope="col">
                        Data
                    </th>

                    <th scope="col">
                        Ações
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($inventarios as $inventario): ?>

                    <?php

                    $totalItens =
                        (int) $inventario['total_itens'];

                    $itensContados =
                        (int) $inventario['itens_contados'];

                    ?>

                    <tr>

                        <td>
                            #<?php echo (int) $inventario['id']; ?>
                        </td>

                        <td>

                            <strong>
                                <?php echo limpar($inventario['titulo']); ?>
                            </strong>

                        </td>

                        <td>

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

                        </td>

                        <td>

                            <?php
                            echo $itensContados;
                            ?>

                            /

                            <?php
                            echo $totalItens;
                            ?>

                            produtos

                        </td>

                        <td>

                            <?php
                            echo limpar(
                                $inventario['criado_por_nome']
                                ?? 'Usuário removido'
                            );
                            ?>

                        </td>

                        <td>

                            <?php
                            echo formatarDataHora(
                                $inventario['criado_em']
                            );
                            ?>

                        </td>

                        <td class="acoes">

                            <a
                                href="<?php echo BASE_URL; ?>/inventory/view.php?id=<?php echo (int) $inventario['id']; ?>"
                            >
                                Ver
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

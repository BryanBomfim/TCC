<?php
/**
 * products/list.php
 * -----------------------------------------------------------------
 * Lista, pesquisa e filtra os produtos.
 *
 * Permite pesquisar por:
 * - Nome;
 * - SKU.
 *
 * Filtros:
 * - Categoria;
 * - Fornecedor;
 * - Status;
 * - Estoque baixo.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

/*
 * ================================================================
 * FILTROS
 * ================================================================
 */

$busca = trim(
    $_GET['busca']
    ?? ''
);

$filtroCategoria = (int) (
    $_GET['categoria']
    ?? 0
);

$filtroFornecedor = (int) (
    $_GET['fornecedor']
    ?? 0
);

$filtroStatus = trim(
    $_GET['status']
    ?? ''
);

$filtroEstoqueBaixo = isset(
    $_GET['baixo']
);

/*
 * ================================================================
 * CONSULTA DOS PRODUTOS
 * ================================================================
 */

$sql = "
    SELECT
        p.*,
        c.nome AS categoria_nome,
        f.nome AS fornecedor_nome

    FROM produtos p

    LEFT JOIN categorias c
        ON c.id = p.categoria_id

    LEFT JOIN fornecedores f
        ON f.id = p.fornecedor_id

    WHERE 1 = 1
";

$parametros = [];

/*
 * ---------------------------------------------------------------
 * Pesquisa por nome ou SKU
 * ---------------------------------------------------------------
 */
if ($busca !== '') {

    $sql .= "
        AND
        (
            p.nome LIKE :busca_nome
            OR p.sku LIKE :busca_sku
            OR p.codigo_barras LIKE :busca_codigo_barras
        )
    ";

    $parametros[':busca_nome'] =
        '%' . $busca . '%';

    $parametros[':busca_sku'] =
        '%' . $busca . '%';

    $parametros[':busca_codigo_barras'] =
        '%' . $busca . '%';
}

/*
 * ---------------------------------------------------------------
 * Categoria
 * ---------------------------------------------------------------
 */
if ($filtroCategoria > 0) {

    $sql .= "
        AND p.categoria_id = :categoria
    ";

    $parametros[':categoria'] =
        $filtroCategoria;
}

/*
 * ---------------------------------------------------------------
 * Fornecedor
 * ---------------------------------------------------------------
 */
if ($filtroFornecedor > 0) {

    $sql .= "
        AND p.fornecedor_id = :fornecedor
    ";

    $parametros[':fornecedor'] =
        $filtroFornecedor;
}

/*
 * ---------------------------------------------------------------
 * Status
 * ---------------------------------------------------------------
 */
if ($filtroStatus === 'ativo') {

    $sql .= "
        AND p.ativo = 1
    ";

} elseif ($filtroStatus === 'inativo') {

    $sql .= "
        AND p.ativo = 0
    ";
}

/*
 * ---------------------------------------------------------------
 * Estoque baixo
 * ---------------------------------------------------------------
 */
if ($filtroEstoqueBaixo) {

    $sql .= "
        AND p.quantidade_estoque <= p.estoque_minimo
    ";
}

$stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM (' . $sql . ') AS produtos_filtrados');
$stmtTotal->execute($parametros);
$totalProdutosFiltrados = (int) $stmtTotal->fetchColumn();
$paginacao = calcularPaginacao($totalProdutosFiltrados, 25);

/*
 * Ordenação e paginação.
 */
$sql .= "
    ORDER BY p.nome ASC
    LIMIT {$paginacao['por_pagina']} OFFSET {$paginacao['offset']}
";

$stmt = $pdo->prepare($sql);

$stmt->execute(
    $parametros
);

$produtos =
    $stmt->fetchAll();

/*
 * ================================================================
 * CATEGORIAS
 * ================================================================
 */

$categorias = $pdo->query("
    SELECT
        id,
        nome

    FROM categorias

    WHERE ativo = 1

    ORDER BY nome ASC
")->fetchAll();

/*
 * ================================================================
 * FORNECEDORES
 * ================================================================
 */

$fornecedores = $pdo->query("
    SELECT
        id,
        nome

    FROM fornecedores

    WHERE ativo = 1

    ORDER BY nome ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="conteudo" id="conteudo-principal" tabindex="-1">

    <!-- =========================================================
         TOPO
         ========================================================= -->

    <div class="topo-pagina">

        <h1>
            Produtos
        </h1>

        <a href="<?php echo BASE_URL; ?>/products/create.php" class="botao">
            + Novo Produto
        </a>

    </div>

    <!-- =========================================================
         FILTROS
         ========================================================= -->

    <form method="GET" action="" class="form-filtros">

        <!-- Pesquisa -->

        <input type="text" name="busca" placeholder="Buscar por nome, SKU ou código de barras..."
            value="<?php echo limpar($busca); ?>" aria-label="Buscar" autocomplete="off">

        <!-- Mantém estoque baixo caso tenha vindo desse filtro -->

        <?php if ($filtroEstoqueBaixo): ?>

            <input type="hidden" name="baixo" value="1">

        <?php endif; ?>

        <!-- Categoria -->

        <select name="categoria" aria-label="Filtrar por categoria">

            <option value="0">
                Todas as categorias
            </option>

            <?php foreach ($categorias as $categoria): ?>

                <option value="<?php echo (int) $categoria['id']; ?>" <?php
                    echo $filtroCategoria === (int) $categoria['id']
                        ? 'selected'
                        : '';
                    ?>>

                    <?php
                    echo limpar(
                        $categoria['nome']
                    );
                    ?>

                </option>

            <?php endforeach; ?>

        </select>

        <!-- Fornecedor -->

        <select name="fornecedor" aria-label="Filtrar por fornecedor">

            <option value="0">
                Todos os fornecedores
            </option>

            <?php foreach ($fornecedores as $fornecedor): ?>

                <option value="<?php echo (int) $fornecedor['id']; ?>" <?php
                    echo $filtroFornecedor === (int) $fornecedor['id']
                        ? 'selected'
                        : '';
                    ?>>

                    <?php
                    echo limpar(
                        $fornecedor['nome']
                    );
                    ?>

                </option>

            <?php endforeach; ?>

        </select>

        <!-- Status -->

        <select name="status" aria-label="Filtrar por status">

            <option value="">
                Todos os status
            </option>

            <option value="ativo" <?php
            echo $filtroStatus === 'ativo'
                ? 'selected'
                : '';
            ?>>
                Ativos
            </option>

            <option value="inativo" <?php
            echo $filtroStatus === 'inativo'
                ? 'selected'
                : '';
            ?>>
                Inativos
            </option>

        </select>

        <!-- Botões -->

        <button type="submit" class="botao">
            Filtrar
        </button>

        <a href="<?php echo BASE_URL; ?>/products/list.php" class="botao botao-secundario">
            Limpar
        </a>

    </form>

    <!-- =========================================================
         PRODUTOS
         ========================================================= -->

    <?php if (empty($produtos)): ?>

        <p class="texto-vazio">
            Nenhum produto encontrado.
        </p>

    <?php else: ?>

        <table class="tabela">

            <thead>

                <tr>

                    <th scope="col">
                        SKU
                    </th>

                    <th scope="col">
                        Código de Barras
                    </th>

                    <th scope="col">
                        Produto
                    </th>

                    <th scope="col">
                        Categoria
                    </th>

                    <th scope="col">
                        Fornecedor
                    </th>

                    <th scope="col">
                        Estoque
                    </th>

                    <th scope="col">
                        Preço Venda
                    </th>

                    <th scope="col">
                        Status
                    </th>

                    <th scope="col">
                        Ações
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($produtos as $produto): ?>

                    <tr class="<?php
                    echo (int) $produto['quantidade_estoque']
                        <= (int) $produto['estoque_minimo']
                        ? 'linha-alerta'
                        : '';
                    ?>">

                        <!-- SKU -->

                        <td>

                            <strong>
                                <?php
                                echo limpar(
                                    $produto['sku']
                                    ?? '-'
                                );
                                ?>
                            </strong>

                        </td>

                        <td>

                            <?php
                            echo limpar(
                                $produto['codigo_barras']
                                ?? '-'
                            );
                            ?>

                        </td>

                        <!-- Produto -->

                        <td>

                            <?php
                            echo limpar(
                                $produto['nome']
                            );
                            ?>

                        </td>

                        <!-- Categoria -->

                        <td>

                            <?php
                            echo limpar(
                                $produto['categoria_nome']
                                ?? '-'
                            );
                            ?>

                        </td>

                        <!-- Fornecedor -->

                        <td>

                            <?php
                            echo limpar(
                                $produto['fornecedor_nome']
                                ?? '-'
                            );
                            ?>

                        </td>

                        <!-- Estoque -->

                        <td>

                            <?php
                            echo (int) $produto['quantidade_estoque'];
                            ?>

                            <?php
                            echo limpar(
                                $produto['unidade']
                            );
                            ?>

                        </td>

                        <!-- Preço -->

                        <td>

                            <?php
                            echo formatarMoeda(
                                $produto['preco_venda']
                            );
                            ?>

                        </td>

                        <!-- Status -->

                        <td>

                            <span class="etiqueta <?php
                            echo $produto['ativo']
                                ? 'etiqueta-ativo'
                                : 'etiqueta-inativo';
                            ?>">

                                <?php
                                echo $produto['ativo']
                                    ? 'Ativo'
                                    : 'Inativo';
                                ?>

                            </span>

                        </td>

                        <!-- Ações -->

                        <td class="acoes">

                            <a href="<?php echo BASE_URL; ?>/products/view.php?id=<?php echo (int) $produto['id']; ?>">
                                Ver
                            </a>

                            <a href="<?php echo BASE_URL; ?>/products/edit.php?id=<?php echo (int) $produto['id']; ?>">
                                Editar
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

        <?php exibirPaginacao($paginacao, 'Paginação de produtos'); ?>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

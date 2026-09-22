<?php
/**
 * products/edit.php
 * -----------------------------------------------------------------
 * Edição de produto existente.
 *
 * Recursos:
 * - SKU somente para visualização;
 * - código de barras editável;
 * - código de barras único;
 * - estoque não pode ser alterado diretamente;
 * - auditoria das alterações;
 * - validação CSRF;
 * - tratamento de erros do banco.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

/*
 * ================================================================
 * PRODUTO
 * ================================================================
 */

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {

    definirMensagem(
        'erro',
        'Produto inválido.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/list.php'
    );

    exit;
}

$stmt = $pdo->prepare("
    SELECT *

    FROM produtos

    WHERE id = :id

    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$produto = $stmt->fetch();

if (!$produto) {

    definirMensagem(
        'erro',
        'Produto não encontrado.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/list.php'
    );

    exit;
}

/*
 * Guarda os dados originais para auditoria.
 */
$produtoAntes = $produto;

$erro = '';

/*
 * ================================================================
 * PROCESSAMENTO
 * ================================================================
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * ------------------------------------------------------------
     * CSRF
     * ------------------------------------------------------------
     */
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $erro =
            'Sessão expirada. Recarregue a página e tente novamente.';

    } else {

        /*
         * --------------------------------------------------------
         * Campos editáveis
         * --------------------------------------------------------
         */
        $camposEditaveis = [
            'nome',
            'codigo_barras',
            'descricao',
            'categoria_id',
            'fornecedor_id',
            'preco_custo',
            'preco_venda',
            'estoque_minimo',
            'unidade'
        ];

        foreach ($camposEditaveis as $campo) {

            $produto[$campo] =
                trim($_POST[$campo] ?? '');
        }

        /*
         * --------------------------------------------------------
         * Validações
         * --------------------------------------------------------
         */

        if ($produto['nome'] === '') {

            $erro =
                'O nome do produto é obrigatório.';

        } elseif (mb_strlen($produto['nome']) > 150) {

            $erro =
                'O nome do produto é muito grande.';

        } elseif (
            $produto['codigo_barras'] !== ''
            &&
            mb_strlen($produto['codigo_barras']) > 50
        ) {

            $erro =
                'O código de barras deve possuir no máximo 50 caracteres.';

        } elseif (
            $produto['preco_custo'] !== ''
            &&
            (
                !is_numeric($produto['preco_custo'])
                ||
                (float) $produto['preco_custo'] < 0
            )
        ) {

            $erro =
                'Informe um preço de custo válido.';

        } elseif (
            !is_numeric($produto['preco_venda'])
            ||
            (float) $produto['preco_venda'] < 0
        ) {

            $erro =
                'Informe um preço de venda válido.';

        } elseif (
            $produto['estoque_minimo'] !== ''
            &&
            (
                !ctype_digit($produto['estoque_minimo'])
                ||
                (int) $produto['estoque_minimo'] < 0
            )
        ) {

            $erro =
                'Informe um estoque mínimo válido.';

        } else {

            /*
             * ----------------------------------------------------
             * Verifica duplicidade do código de barras
             * ----------------------------------------------------
             *
             * O próprio produto é ignorado através de:
             *
             * id <> :id
             */
            if ($produto['codigo_barras'] !== '') {

                $stmtCodigo = $pdo->prepare("
                    SELECT
                        id,
                        nome

                    FROM produtos

                    WHERE codigo_barras = :codigo_barras
                      AND id <> :id

                    LIMIT 1
                ");

                $stmtCodigo->execute([
                    ':codigo_barras' =>
                        $produto['codigo_barras'],

                    ':id' =>
                        $id
                ]);

                $produtoComMesmoCodigo =
                    $stmtCodigo->fetch();

                if ($produtoComMesmoCodigo) {

                    $erro =
                        'Este código de barras já pertence ao produto "'
                        . $produtoComMesmoCodigo['nome']
                        . '".';
                }
            }

            /*
             * ----------------------------------------------------
             * Atualização
             * ----------------------------------------------------
             */
            if ($erro === '') {

                try {

                    $pdo->beginTransaction();

                    /*
                     * Atualiza o produto.
                     *
                     * SKU e quantidade_estoque não são modificados.
                     */
                    $stmtUpdate = $pdo->prepare("
                        UPDATE produtos

                        SET
                            nome = :nome,
                            codigo_barras = :codigo_barras,
                            descricao = :descricao,
                            categoria_id = :categoria_id,
                            fornecedor_id = :fornecedor_id,
                            preco_custo = :preco_custo,
                            preco_venda = :preco_venda,
                            estoque_minimo = :estoque_minimo,
                            unidade = :unidade

                        WHERE id = :id
                    ");

                    $stmtUpdate->execute([

                        ':nome' =>
                            $produto['nome'],

                        ':codigo_barras' =>
                            $produto['codigo_barras'] !== ''
                                ? $produto['codigo_barras']
                                : null,

                        ':descricao' =>
                            $produto['descricao'] !== ''
                                ? $produto['descricao']
                                : null,

                        ':categoria_id' =>
                            $produto['categoria_id'] !== ''
                                ? (int) $produto['categoria_id']
                                : null,

                        ':fornecedor_id' =>
                            $produto['fornecedor_id'] !== ''
                                ? (int) $produto['fornecedor_id']
                                : null,

                        ':preco_custo' =>
                            $produto['preco_custo'] !== ''
                                ? (float) $produto['preco_custo']
                                : 0,

                        ':preco_venda' =>
                            (float) $produto['preco_venda'],

                        ':estoque_minimo' =>
                            $produto['estoque_minimo'] !== ''
                                ? (int) $produto['estoque_minimo']
                                : 0,

                        ':unidade' =>
                            $produto['unidade'] !== ''
                                ? $produto['unidade']
                                : 'UN',

                        ':id' =>
                            $id
                    ]);

                    /*
                     * =================================================
                     * DADOS PARA AUDITORIA
                     * =================================================
                     */

                    $dadosAnteriores = [

                        'nome' =>
                            $produtoAntes['nome'],

                        'sku' =>
                            $produtoAntes['sku'],

                        'codigo_barras' =>
                            $produtoAntes['codigo_barras'],

                        'descricao' =>
                            $produtoAntes['descricao'],

                        'categoria_id' =>
                            $produtoAntes['categoria_id'],

                        'fornecedor_id' =>
                            $produtoAntes['fornecedor_id'],

                        'preco_custo' =>
                            (float) $produtoAntes['preco_custo'],

                        'preco_venda' =>
                            (float) $produtoAntes['preco_venda'],

                        'estoque_minimo' =>
                            (int) $produtoAntes['estoque_minimo'],

                        'unidade' =>
                            $produtoAntes['unidade']
                    ];

                    $dadosNovos = [

                        'nome' =>
                            $produto['nome'],

                        /*
                         * SKU não muda durante a edição.
                         */
                        'sku' =>
                            $produtoAntes['sku'],

                        'codigo_barras' =>
                            $produto['codigo_barras'] !== ''
                                ? $produto['codigo_barras']
                                : null,

                        'descricao' =>
                            $produto['descricao'] !== ''
                                ? $produto['descricao']
                                : null,

                        'categoria_id' =>
                            $produto['categoria_id'] !== ''
                                ? (int) $produto['categoria_id']
                                : null,

                        'fornecedor_id' =>
                            $produto['fornecedor_id'] !== ''
                                ? (int) $produto['fornecedor_id']
                                : null,

                        'preco_custo' =>
                            $produto['preco_custo'] !== ''
                                ? (float) $produto['preco_custo']
                                : 0,

                        'preco_venda' =>
                            (float) $produto['preco_venda'],

                        'estoque_minimo' =>
                            $produto['estoque_minimo'] !== ''
                                ? (int) $produto['estoque_minimo']
                                : 0,

                        'unidade' =>
                            $produto['unidade'] !== ''
                                ? $produto['unidade']
                                : 'UN'
                    ];

                    /*
                     * Só registra caso realmente tenha ocorrido
                     * alguma alteração.
                     */
                    if ($dadosAnteriores != $dadosNovos) {

                        registrarAuditoria(
                            'EDITAR',
                            'Produtos',
                            $id,
                            'Produto "'
                                . $produto['nome']
                                . '" alterado.',
                            $dadosAnteriores,
                            $dadosNovos
                        );
                    }

                    $pdo->commit();

                    definirMensagem(
                        'sucesso',
                        'Produto atualizado com sucesso!'
                    );

                    header(
                        'Location: '
                        . BASE_URL
                        . '/products/view.php?id='
                        . $id
                    );

                    exit;

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {

                        $pdo->rollBack();
                    }

                    /*
                     * Código 1062:
                     * chave UNIQUE duplicada.
                     */
                    if (
                        isset($e->errorInfo[1])
                        &&
                        (int) $e->errorInfo[1] === 1062
                    ) {

                        $erro =
                            'Este código de barras já está cadastrado em outro produto.';

                    } else {

                        error_log(
                            'Erro ao editar produto: '
                            . $e->getMessage()
                        );

                        $erro =
                            'Ocorreu um erro ao atualizar o produto.';
                    }

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {

                        $pdo->rollBack();
                    }

                    error_log(
                        'Erro ao editar produto: '
                        . $e->getMessage()
                    );

                    $erro =
                        'Ocorreu um erro ao atualizar o produto.';
                }
            }
        }
    }
}

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
    <?php renderizarBreadcrumbs(); ?>

    <h1>
        Editar Produto
    </h1>

    <?php if ($erro): ?>

        <div
            class="alerta alerta-erro"
            role="alert"
        >
            <?php echo limpar($erro); ?>
        </div>

    <?php endif; ?>

    <form
        method="POST"
        action=""
        class="formulario"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo gerarTokenCSRF(); ?>"
        >

        <!-- =====================================================
             SKU
             ===================================================== -->

        <label for="sku">
            SKU
        </label>

        <input
            type="text"
            id="sku"
            value="<?php echo limpar($produto['sku'] ?? '-'); ?>"
            disabled
        >

        <small>
            O SKU é gerado automaticamente e não pode ser alterado.
        </small>

        <!-- =====================================================
             NOME
             ===================================================== -->

        <label for="nome">
            Nome *
        </label>

        <input
            type="text"
            name="nome"
            id="nome"
            maxlength="150"
            required
            value="<?php echo limpar($produto['nome']); ?>"
        >

        <!-- =====================================================
             CÓDIGO DE BARRAS
             ===================================================== -->

        <label for="codigo_barras">
            Código de Barras
        </label>

        <input
            type="text"
            name="codigo_barras"
            id="codigo_barras"
            maxlength="50"
            autocomplete="off"
            inputmode="numeric"
            value="<?php echo limpar($produto['codigo_barras'] ?? ''); ?>"
        >

        <small>
            Opcional. O código deve ser único para cada produto.
        </small>

        <!-- =====================================================
             DESCRIÇÃO
             ===================================================== -->

        <label for="descricao">
            Descrição
        </label>

        <textarea
            name="descricao"
            id="descricao"
            rows="3"
        ><?php echo limpar($produto['descricao'] ?? ''); ?></textarea>

        <!-- =====================================================
             CATEGORIA / FORNECEDOR
             ===================================================== -->

        <div class="linha-formulario">

            <div>

                <label for="categoria_id">
                    Categoria
                </label>

                <select
                    name="categoria_id"
                    id="categoria_id"
                >

                    <option value="">
                        Selecione...
                    </option>

                    <?php foreach ($categorias as $categoria): ?>

                        <option
                            value="<?php echo (int) $categoria['id']; ?>"

                            <?php
                            echo $produto['categoria_id'] == $categoria['id']
                                ? 'selected'
                                : '';
                            ?>
                        >

                            <?php
                            echo limpar(
                                $categoria['nome']
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div>

                <label for="fornecedor_id">
                    Fornecedor
                </label>

                <select
                    name="fornecedor_id"
                    id="fornecedor_id"
                >

                    <option value="">
                        Selecione...
                    </option>

                    <?php foreach ($fornecedores as $fornecedor): ?>

                        <option
                            value="<?php echo (int) $fornecedor['id']; ?>"

                            <?php
                            echo $produto['fornecedor_id'] == $fornecedor['id']
                                ? 'selected'
                                : '';
                            ?>
                        >

                            <?php
                            echo limpar(
                                $fornecedor['nome']
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>

        <!-- =====================================================
             PREÇOS
             ===================================================== -->

        <div class="linha-formulario">

            <div>

                <label for="preco_custo">
                    Preço de Custo
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="preco_custo"
                    id="preco_custo"
                    value="<?php echo limpar($produto['preco_custo']); ?>"
                >

            </div>

            <div>

                <label for="preco_venda">
                    Preço de Venda *
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="preco_venda"
                    id="preco_venda"
                    required
                    value="<?php echo limpar($produto['preco_venda']); ?>"
                >

            </div>

        </div>

        <!-- =====================================================
             ESTOQUE
             ===================================================== -->

        <div class="linha-formulario">

            <div>

                <label>
                    Quantidade em Estoque
                </label>

                <input
                    type="text"
                    value="<?php echo (int) $produto['quantidade_estoque']; ?> <?php echo limpar($produto['unidade']); ?>"
                    disabled
                >

                <small>
                    Use Entrada/Saída para modificar o estoque.
                </small>

            </div>

            <div>

                <label for="estoque_minimo">
                    Estoque Mínimo
                </label>

                <input
                    type="number"
                    min="0"
                    step="1"
                    name="estoque_minimo"
                    id="estoque_minimo"
                    value="<?php echo limpar($produto['estoque_minimo']); ?>"
                >

            </div>

            <div>

                <label for="unidade">
                    Unidade
                </label>

                <input
                    type="text"
                    name="unidade"
                    id="unidade"
                    maxlength="20"
                    value="<?php echo limpar($produto['unidade']); ?>"
                >

            </div>

        </div>

        <!-- =====================================================
             AÇÕES
             ===================================================== -->

        <div class="acoes-formulario">

            <button
                type="submit"
                class="botao"
            >
                Salvar Alterações
            </button>

            <a
                href="<?php echo BASE_URL; ?>/products/view.php?id=<?php echo $id; ?>"
                class="botao botao-secundario"
            >
                Cancelar
            </a>

        </div>

    </form>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

<?php
/**
 * products/create.php
 * -----------------------------------------------------------------
 * Cadastro de novos produtos.
 *
 * Recursos:
 * - SKU automático;
 * - código de barras opcional;
 * - validação de código de barras duplicado;
 * - validação dos valores do produto;
 * - auditoria;
 * - transação no banco.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

$erro = '';

/*
 * ================================================================
 * DADOS DO FORMULÁRIO
 * ================================================================
 */
$dados = [
    'nome' => '',
    'codigo_barras' => '',
    'descricao' => '',
    'categoria_id' => '',
    'fornecedor_id' => '',
    'preco_custo' => '',
    'preco_venda' => '',
    'quantidade_estoque' => '0',
    'estoque_minimo' => '0',
    'unidade' => 'UN'
];

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
         * Recebe os campos
         * --------------------------------------------------------
         */
        foreach ($dados as $campo => $valor) {

            $dados[$campo] =
                trim($_POST[$campo] ?? '');
        }

        /*
         * --------------------------------------------------------
         * Validações
         * --------------------------------------------------------
         */
        if ($dados['nome'] === '') {

            $erro =
                'O nome do produto é obrigatório.';

        } elseif (mb_strlen($dados['nome']) > 150) {

            $erro =
                'O nome do produto é muito grande.';

        } elseif (
            $dados['codigo_barras'] !== ''
            &&
            mb_strlen($dados['codigo_barras']) > 50
        ) {

            $erro =
                'O código de barras deve possuir no máximo 50 caracteres.';

        } elseif (
            $dados['preco_custo'] !== ''
            &&
            (
                !is_numeric($dados['preco_custo'])
                ||
                ((float) $dados['preco_custo'] < 0 || !is_finite((float) $dados['preco_custo']) || (float) $dados['preco_custo'] > 99999999.99)
            )
        ) {

            $erro =
                'Informe um preço de custo válido.';

        } elseif (
            !is_numeric($dados['preco_venda'])
            ||
            ((float) $dados['preco_venda'] < 0 || !is_finite((float) $dados['preco_venda']) || (float) $dados['preco_venda'] > 99999999.99)
        ) {

            $erro =
                'Informe um preço de venda válido.';

        } elseif (
            $dados['quantidade_estoque'] !== ''
            &&
            (
                quantidadeInteira($dados['quantidade_estoque']) === null
            )
        ) {

            $erro =
                'Informe uma quantidade de estoque válida.';

        } elseif (
            $dados['estoque_minimo'] !== ''
            &&
            (
                quantidadeInteira($dados['estoque_minimo']) === null
            )
        ) {

            $erro =
                'Informe um estoque mínimo válido.';

        } else {

            /*
             * ----------------------------------------------------
             * Verifica se o código de barras já existe
             * ----------------------------------------------------
             */
            if ($dados['codigo_barras'] !== '') {

                $stmtCodigo = $pdo->prepare("
                    SELECT id

                    FROM produtos

                    WHERE codigo_barras = :codigo_barras

                    LIMIT 1
                ");

                $stmtCodigo->execute([
                    ':codigo_barras' =>
                        $dados['codigo_barras']
                ]);

                if ($stmtCodigo->fetch()) {

                    $erro =
                        'Este código de barras já está cadastrado em outro produto.';
                }
            }

            /*
             * ----------------------------------------------------
             * Cadastra somente se não houver erro
             * ----------------------------------------------------
             */
            if ($erro === '') {

                try {

                    $pdo->beginTransaction();

                    /*
                     * ------------------------------------------------
                     * Insere o produto
                     * ------------------------------------------------
                     */
                    $stmt = $pdo->prepare("
                        INSERT INTO produtos
                        (
                            nome,
                            codigo_barras,
                            descricao,
                            categoria_id,
                            fornecedor_id,
                            preco_custo,
                            preco_venda,
                            quantidade_estoque,
                            estoque_minimo,
                            unidade,
                            ativo
                        )
                        VALUES
                        (
                            :nome,
                            :codigo_barras,
                            :descricao,
                            :categoria_id,
                            :fornecedor_id,
                            :preco_custo,
                            :preco_venda,
                            :quantidade_estoque,
                            :estoque_minimo,
                            :unidade,
                            1
                        )
                    ");

                    $stmt->execute([

                        ':nome' =>
                            $dados['nome'],

                        ':codigo_barras' =>
                            $dados['codigo_barras'] !== ''
                            ? $dados['codigo_barras']
                            : null,

                        ':descricao' =>
                            $dados['descricao'] !== ''
                            ? $dados['descricao']
                            : null,

                        ':categoria_id' =>
                            $dados['categoria_id'] !== ''
                            ? (int) $dados['categoria_id']
                            : null,

                        ':fornecedor_id' =>
                            $dados['fornecedor_id'] !== ''
                            ? (int) $dados['fornecedor_id']
                            : null,

                        ':preco_custo' =>
                            $dados['preco_custo'] !== ''
                            ? (float) $dados['preco_custo']
                            : 0,

                        ':preco_venda' =>
                            (float) $dados['preco_venda'],

                        ':quantidade_estoque' =>
                            $dados['quantidade_estoque'] !== ''
                            ? (int) $dados['quantidade_estoque']
                            : 0,

                        ':estoque_minimo' =>
                            $dados['estoque_minimo'] !== ''
                            ? (int) $dados['estoque_minimo']
                            : 0,

                        ':unidade' =>
                            $dados['unidade'] !== ''
                            ? $dados['unidade']
                            : 'UN'
                    ]);

                    /*
                     * ID recém-criado.
                     */
                    $produtoId =
                        (int) $pdo->lastInsertId();

                    /*
                     * ------------------------------------------------
                     * GERAÇÃO AUTOMÁTICA DO SKU
                     * ------------------------------------------------
                     *
                     * Exemplos:
                     *
                     * ID 1   -> SC-000001
                     * ID 25  -> SC-000025
                     * ID 350 -> SC-000350
                     */
                    $sku =
                        'SC-'
                        . str_pad(
                            (string) $produtoId,
                            6,
                            '0',
                            STR_PAD_LEFT
                        );

                    /*
                     * Salva o SKU.
                     */
                    $stmtSku = $pdo->prepare("
                        UPDATE produtos

                        SET sku = :sku

                        WHERE id = :id
                    ");

                    $stmtSku->execute([
                        ':sku' => $sku,
                        ':id' => $produtoId
                    ]);

                    $estoqueInicial = $dados['quantidade_estoque'] !== ''
                        ? (int) $dados['quantidade_estoque']
                        : 0;

                    if ($estoqueInicial > 0) {
                        $stmtMovimentacaoInicial = $pdo->prepare("
                            INSERT INTO movimentacoes
                            (
                                produto_id,
                                usuario_id,
                                fornecedor_id,
                                tipo,
                                quantidade,
                                saldo_anterior,
                                saldo_posterior,
                                valor_unitario,
                                motivo,
                                origem,
                                observacao
                            )
                            VALUES
                            (
                                :produto_id,
                                :usuario_id,
                                :fornecedor_id,
                                'entrada',
                                :quantidade,
                                0,
                                :saldo_posterior,
                                :valor_unitario,
                                'Estoque inicial',
                                'cadastro',
                                'Saldo inicial informado no cadastro do produto.'
                            )
                        ");

                        $stmtMovimentacaoInicial->execute([
                            ':produto_id' => $produtoId,
                            ':usuario_id' => (int) $_SESSION['usuario_id'],
                            ':fornecedor_id' => $dados['fornecedor_id'] !== ''
                                ? (int) $dados['fornecedor_id']
                                : null,
                            ':quantidade' => $estoqueInicial,
                            ':saldo_posterior' => $estoqueInicial,
                            ':valor_unitario' => $dados['preco_custo'] !== ''
                                ? (float) $dados['preco_custo']
                                : null,
                        ]);
                    }

                    /*
                     * ------------------------------------------------
                     * AUDITORIA
                     * ------------------------------------------------
                     */
                    registrarAuditoria(
                        'CRIAR',
                        'Produtos',
                        $produtoId,
                        'Produto "'
                        . $dados['nome']
                        . '" cadastrado.',
                        null,
                        [
                            'nome' =>
                                $dados['nome'],

                            'sku' =>
                                $sku,

                            'codigo_barras' =>
                                $dados['codigo_barras'] !== ''
                                ? $dados['codigo_barras']
                                : null,

                            'descricao' =>
                                $dados['descricao'] !== ''
                                ? $dados['descricao']
                                : null,

                            'categoria_id' =>
                                $dados['categoria_id'] !== ''
                                ? (int) $dados['categoria_id']
                                : null,

                            'fornecedor_id' =>
                                $dados['fornecedor_id'] !== ''
                                ? (int) $dados['fornecedor_id']
                                : null,

                            'preco_custo' =>
                                $dados['preco_custo'] !== ''
                                ? (float) $dados['preco_custo']
                                : 0,

                            'preco_venda' =>
                                (float) $dados['preco_venda'],

                            'quantidade_estoque' =>
                                $dados['quantidade_estoque'] !== ''
                                ? (int) $dados['quantidade_estoque']
                                : 0,

                            'estoque_minimo' =>
                                $dados['estoque_minimo'] !== ''
                                ? (int) $dados['estoque_minimo']
                                : 0,

                            'unidade' =>
                                $dados['unidade'] !== ''
                                ? $dados['unidade']
                                : 'UN'
                        ]
                    );

                    /*
                     * Confirma produto + SKU + saldo inicial + auditoria.
                     */
                    $pdo->commit();

                    definirMensagem(
                        'sucesso',
                        'Produto cadastrado com sucesso! SKU: '
                        . $sku
                    );

                    header(
                        'Location: '
                        . BASE_URL
                        . '/products/list.php'
                    );

                    exit;

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    /*
                     * 1062 = chave UNIQUE duplicada.
                     *
                     * Isso também protege contra dois cadastros
                     * simultâneos com o mesmo código.
                     */
                    if (
                        isset($e->errorInfo[1])
                        &&
                        (int) $e->errorInfo[1] === 1062
                    ) {

                        $erro =
                            'Este código de barras já está cadastrado.';

                    } else {

                        error_log(
                            'Erro ao cadastrar produto: '
                            . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine()
                        );

                        $erro =
                            'Ocorreu um erro ao cadastrar o produto.';
                    }

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log(
                        'Erro ao cadastrar produto: '
                        . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine()
                    );

                    $erro =
                        'Ocorreu um erro ao cadastrar o produto.';
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

    <h1>
        Novo Produto
    </h1>

    <?php if ($erro): ?>

        <div class="alerta alerta-erro" role="alert">
            <?php echo limpar($erro); ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="" class="formulario">

        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <!-- =====================================================
             NOME
             ===================================================== -->

        <label for="nome">
            Nome *
        </label>

        <input type="text" name="nome" id="nome" maxlength="150" required value="<?php echo limpar($dados['nome']); ?>">

        <!-- =====================================================
             CÓDIGO DE BARRAS
             ===================================================== -->

        <label for="codigo_barras">
            Código de Barras
        </label>

        <input type="text" name="codigo_barras" id="codigo_barras" maxlength="50" autocomplete="off" inputmode="numeric"
            value="<?php echo limpar($dados['codigo_barras']); ?>">

        <small>
            Opcional. O código deve ser único para cada produto.
        </small>

        <!-- =====================================================
             SKU
             ===================================================== -->

        <p>
            <strong>SKU:</strong>
            será gerado automaticamente após o cadastro.
        </p>

        <!-- =====================================================
             DESCRIÇÃO
             ===================================================== -->

        <label for="descricao">
            Descrição
        </label>

        <textarea name="descricao" id="descricao" rows="3"><?php echo limpar($dados['descricao']); ?></textarea>

        <!-- =====================================================
             CATEGORIA / FORNECEDOR
             ===================================================== -->

        <div class="linha-formulario">

            <div>

                <label for="categoria_id">
                    Categoria
                </label>

                <select name="categoria_id" id="categoria_id">

                    <option value="">
                        Selecione...
                    </option>

                    <?php foreach ($categorias as $categoria): ?>

                        <option value="<?php echo (int) $categoria['id']; ?>" <?php
                            echo $dados['categoria_id'] == $categoria['id']
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

            </div>

            <div>

                <label for="fornecedor_id">
                    Fornecedor
                </label>

                <select name="fornecedor_id" id="fornecedor_id">

                    <option value="">
                        Selecione...
                    </option>

                    <?php foreach ($fornecedores as $fornecedor): ?>

                        <option value="<?php echo (int) $fornecedor['id']; ?>" <?php
                            echo $dados['fornecedor_id'] == $fornecedor['id']
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

                <input type="number" step="0.01" min="0" name="preco_custo" id="preco_custo"
                    value="<?php echo limpar($dados['preco_custo']); ?>">

            </div>

            <div>

                <label for="preco_venda">
                    Preço de Venda *
                </label>

                <input type="number" step="0.01" min="0" name="preco_venda" id="preco_venda" required
                    value="<?php echo limpar($dados['preco_venda']); ?>">

            </div>

        </div>

        <!-- =====================================================
             ESTOQUE
             ===================================================== -->

        <div class="linha-formulario">

            <div>

                <label for="quantidade_estoque">
                    Quantidade em Estoque
                </label>

                <input type="number" min="0" step="1" name="quantidade_estoque" id="quantidade_estoque"
                    value="<?php echo limpar($dados['quantidade_estoque']); ?>">

            </div>

            <div>

                <label for="estoque_minimo">
                    Estoque Mínimo
                </label>

                <input type="number" min="0" step="1" name="estoque_minimo" id="estoque_minimo"
                    value="<?php echo limpar($dados['estoque_minimo']); ?>">

            </div>

            <div>

                <label for="unidade">
                    Unidade
                </label>

                <input type="text" name="unidade" id="unidade" maxlength="20"
                    value="<?php echo limpar($dados['unidade']); ?>">

            </div>

        </div>

        <!-- =====================================================
             AÇÕES
             ===================================================== -->

        <div class="acoes-formulario">

            <button type="submit" class="botao">
                Salvar
            </button>

            <a href="<?php echo BASE_URL; ?>/products/list.php" class="botao botao-secundario">
                Cancelar
            </a>

        </div>

    </form>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

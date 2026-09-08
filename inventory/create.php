<?php
/**
 * inventory/create.php
 * ---------------------------------------------------------------
 * Cria um novo inventário físico.
 *
 * Somente administradores podem iniciar inventários.
 *
 * Ao criar:
 * - registra o inventário;
 * - copia todos os produtos ativos;
 * - grava o estoque atual de cada produto como referência;
 * - registra auditoria.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirAdministrador();

$erro = '';

$dados = [
    'titulo' => '',
    'observacao' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * CSRF.
     */
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $erro =
            'Sessão expirada. Recarregue a página e tente novamente.';

    } else {

        $dados['titulo'] =
            trim($_POST['titulo'] ?? '');

        $dados['observacao'] =
            trim($_POST['observacao'] ?? '');

        /*
         * Validações.
         */
        if ($dados['titulo'] === '') {

            $erro =
                'Informe um nome para o inventário.';

        } elseif (mb_strlen($dados['titulo']) > 150) {

            $erro =
                'O nome do inventário deve possuir no máximo 150 caracteres.';

        } elseif (mb_strlen($dados['observacao']) > 500) {

            $erro =
                'A observação deve possuir no máximo 500 caracteres.';

        } else {

            /*
             * Verifica se existem produtos ativos.
             */
            $totalProdutosAtivos =
                (int) $pdo->query("
                    SELECT COUNT(*)

                    FROM produtos

                    WHERE ativo = 1
                ")->fetchColumn();

            if ($totalProdutosAtivos === 0) {

                $erro =
                    'Não existem produtos ativos para adicionar ao inventário.';

            } else {

                try {

                    $pdo->beginTransaction();

                    /*
                     * ------------------------------------------------
                     * 1. Cria o inventário
                     * ------------------------------------------------
                     */
                    $stmtInventario = $pdo->prepare("
                        INSERT INTO inventarios
                        (
                            titulo,
                            status,
                            observacao,
                            criado_por,
                            criado_por_nome
                        )
                        VALUES
                        (
                            :titulo,
                            'aberto',
                            :observacao,
                            :criado_por,
                            :criado_por_nome
                        )
                    ");

                    $stmtInventario->execute([

                        ':titulo' =>
                            $dados['titulo'],

                        ':observacao' =>
                            $dados['observacao'] !== ''
                                ? $dados['observacao']
                                : null,

                        ':criado_por' =>
                            (int) $_SESSION['usuario_id'],

                        ':criado_por_nome' =>
                            $_SESSION['usuario_nome']
                            ?? 'Administrador'
                    ]);

                    $inventarioId =
                        (int) $pdo->lastInsertId();

                    /*
                     * ------------------------------------------------
                     * 2. Cria os itens automaticamente
                     * ------------------------------------------------
                     *
                     * O estoque_sistema representa a quantidade
                     * registrada no momento em que a contagem começou.
                     */
                    $stmtItens = $pdo->prepare("
                        INSERT INTO inventario_itens
                        (
                            inventario_id,
                            produto_id,
                            estoque_sistema
                        )

                        SELECT
                            :inventario_id,
                            id,
                            quantidade_estoque

                        FROM produtos

                        WHERE ativo = 1
                    ");

                    $stmtItens->execute([
                        ':inventario_id' =>
                            $inventarioId
                    ]);

                    /*
                     * ------------------------------------------------
                     * 3. Auditoria
                     * ------------------------------------------------
                     */
                    registrarAuditoria(
                        'CRIAR',
                        'Inventários',
                        $inventarioId,
                        'Inventário "'
                            . $dados['titulo']
                            . '" iniciado.',
                        null,
                        [
                            'titulo' =>
                                $dados['titulo'],

                            'produtos' =>
                                $totalProdutosAtivos,

                            'status' =>
                                'aberto'
                        ]
                    );

                    $pdo->commit();

                    definirMensagem(
                        'sucesso',
                        'Inventário criado com sucesso! '
                            . $totalProdutosAtivos
                            . ' produto(s) foram adicionados à contagem.'
                    );

                    header(
                        'Location: '
                        . BASE_URL
                        . '/inventory/index.php'
                    );

                    exit;

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log(
                        'Erro ao criar inventário: '
                        . $e->getMessage()
                    );

                    $erro =
                        'Ocorreu um erro ao criar o inventário.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="conteudo" id="conteudo-principal" tabindex="-1">

    <h1>
        Novo Inventário
    </h1>

    <?php if ($erro): ?>

        <div
            class="alerta alerta-erro"
            role="alert"
        >
            <?php echo limpar($erro); ?>
        </div>

    <?php endif; ?>

    <div class="painel">

        <p>
            Ao iniciar o inventário, todos os produtos ativos serão adicionados automaticamente.
        </p>

        <p>
            O estoque atual será salvo como referência e nenhuma quantidade será alterada até a finalização.
        </p>

    </div>

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

        <label for="titulo">
            Nome do Inventário *
        </label>

        <input
            type="text"
            name="titulo"
            id="titulo"
            maxlength="150"
            required
            placeholder="Ex: Inventário Setembro 2026"
            value="<?php echo limpar($dados['titulo']); ?>"
        >

        <label for="observacao">
            Observação
        </label>

        <textarea
            name="observacao"
            id="observacao"
            rows="4"
            maxlength="500"
            placeholder="Ex: Contagem física geral do estoque."
        ><?php echo limpar($dados['observacao']); ?></textarea>

        <div class="acoes-formulario">

            <button
                type="submit"
                class="botao"
            >
                Iniciar Inventário
            </button>

            <a
                href="<?php echo BASE_URL; ?>/inventory/index.php"
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

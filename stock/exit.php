<?php
/**
 * stock/exit.php
 * -----------------------------------------------------------------
 * Registra uma saída de estoque.
 *
 * Recursos:
 * - Validação CSRF.
 * - Transação no banco.
 * - Bloqueio do produto com FOR UPDATE.
 * - Impede estoque negativo mesmo com acessos simultâneos.
 * - Registra a saída na auditoria.
 * - Gera notificações de estoque baixo.
 * - Gera notificação crítica quando o estoque chega a zero.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

$erro = '';

$produtoSelecionado = (int) (
    $_GET['produto_id']
    ?? $_POST['produto_id']
    ?? 0
);

/*
 * ================================================================
 * PROCESSAMENTO DO FORMULÁRIO
 * ================================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * ------------------------------------------------------------
     * 1. Proteção CSRF
     * ------------------------------------------------------------
     */
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

        $erro =
            'Sessão expirada. '
            . 'Recarregue a página e tente novamente.';

    } else {

        /*
         * --------------------------------------------------------
         * 2. Dados recebidos
         * --------------------------------------------------------
         */
        $produtoId = (int) (
            $_POST['produto_id']
            ?? 0
        );

        $quantidade = (int) (
            $_POST['quantidade']
            ?? 0
        );

        $observacao = trim(
            $_POST['observacao']
            ?? ''
        );

        $produtoSelecionado = $produtoId;

        /*
         * --------------------------------------------------------
         * 3. Validações básicas
         * --------------------------------------------------------
         */
        if ($produtoId <= 0) {

            $erro =
                'Selecione um produto.';

        } elseif ($quantidade <= 0) {

            $erro =
                'Informe uma quantidade válida '
                . '(maior que zero).';

        } else {

            /*
             * ----------------------------------------------------
             * 4. Processa a saída
             * ----------------------------------------------------
             */
            try {

                $pdo->beginTransaction();

                /*
                 * ------------------------------------------------
                 * 5. Busca e bloqueia o produto
                 * ------------------------------------------------
                 *
                 * FOR UPDATE impede que duas saídas sejam
                 * processadas ao mesmo tempo utilizando o mesmo
                 * estoque disponível.
                 */
                $stmtProduto = $pdo->prepare("
                    SELECT
                        id,
                        nome,
                        quantidade_estoque,
                        estoque_minimo,
                        unidade

                    FROM produtos

                    WHERE id = :id
                      AND ativo = 1

                    LIMIT 1

                    FOR UPDATE
                ");

                $stmtProduto->execute([
                    ':id' => $produtoId
                ]);

                $produtoAtual =
                    $stmtProduto->fetch();

                /*
                 * ------------------------------------------------
                 * 6. Produto inexistente ou inativo
                 * ------------------------------------------------
                 */
                if (!$produtoAtual) {

                    $pdo->rollBack();

                    $erro =
                        'Produto não encontrado ou inativo.';

                } else {

                    /*
                     * Estoque existente antes da saída.
                     */
                    $estoqueAntes =
                        (int) $produtoAtual['quantidade_estoque'];

                    /*
                     * Estoque mínimo configurado.
                     */
                    $estoqueMinimo =
                        (int) $produtoAtual['estoque_minimo'];

                    /*
                     * --------------------------------------------
                     * 7. Verifica estoque disponível
                     * --------------------------------------------
                     */
                    if ($quantidade > $estoqueAntes) {

                        $pdo->rollBack();

                        $erro =
                            'Quantidade insuficiente em estoque. '
                            . 'Estoque atual: '
                            . $estoqueAntes
                            . ' '
                            . $produtoAtual['unidade']
                            . '.';

                    } else {

                        /*
                         * Estoque que restará depois da saída.
                         */
                        $estoqueDepois =
                            $estoqueAntes - $quantidade;

                        /*
                         * ----------------------------------------
                         * 8. Registra a movimentação
                         * ----------------------------------------
                         */
                        $stmtInsert = $pdo->prepare("
                            INSERT INTO movimentacoes
                            (
                                produto_id,
                                usuario_id,
                                tipo,
                                quantidade,
                                observacao
                            )
                            VALUES
                            (
                                :produto_id,
                                :usuario_id,
                                'saida',
                                :quantidade,
                                :observacao
                            )
                        ");

                        $stmtInsert->execute([
                            ':produto_id' =>
                                $produtoId,

                            ':usuario_id' =>
                                $_SESSION['usuario_id'],

                            ':quantidade' =>
                                $quantidade,

                            ':observacao' =>
                                $observacao !== ''
                                ? $observacao
                                : null
                        ]);

                        /*
                         * ID da movimentação criada.
                         */
                        $movimentacaoId =
                            (int) $pdo->lastInsertId();

                        /*
                         * ----------------------------------------
                         * 9. Atualiza o estoque
                         * ----------------------------------------
                         */
                        $stmtUpdate = $pdo->prepare("
                            UPDATE produtos

                            SET quantidade_estoque = :estoque

                            WHERE id = :id
                        ");

                        $stmtUpdate->execute([
                            ':estoque' =>
                                $estoqueDepois,

                            ':id' =>
                                $produtoId
                        ]);

                        /*
                         * ----------------------------------------
                         * 10. Auditoria
                         * ----------------------------------------
                         */
                        registrarAuditoria(
                            'SAIDA',
                            'Estoque',
                            $movimentacaoId,
                            'Saída de '
                            . $quantidade
                            . ' '
                            . $produtoAtual['unidade']
                            . ' do produto "'
                            . $produtoAtual['nome']
                            . '".',
                            [
                                'produto_id' =>
                                    $produtoId,

                                'produto' =>
                                    $produtoAtual['nome'],

                                'estoque' =>
                                    $estoqueAntes
                            ],
                            [
                                'produto_id' =>
                                    $produtoId,

                                'produto' =>
                                    $produtoAtual['nome'],

                                'quantidade_saida' =>
                                    $quantidade,

                                'estoque' =>
                                    $estoqueDepois,

                                'observacao' =>
                                    $observacao !== ''
                                    ? $observacao
                                    : null
                            ]
                        );

                        /*
                         * =================================================
                         * 11. NOTIFICAÇÕES AUTOMÁTICAS
                         * =================================================
                         */

                        $tipoNotificacao = null;

                        $tituloNotificacao = null;

                        $mensagemNotificacao = null;

                        /*
                         * ------------------------------------------------
                         * PRODUTO ZEROU
                         * ------------------------------------------------
                         *
                         * Só cria quando o produto realmente passa
                         * de alguma quantidade para zero.
                         */
                        if (
                            $estoqueDepois === 0
                            &&
                            $estoqueAntes > 0
                        ) {

                            $tipoNotificacao =
                                'critico';

                            $tituloNotificacao =
                                'Produto sem estoque';

                            $mensagemNotificacao =
                                'O produto "'
                                . $produtoAtual['nome']
                                . '" ficou sem estoque.';

                            /*
                             * ------------------------------------------------
                             * PRODUTO ENTROU EM ESTOQUE BAIXO
                             * ------------------------------------------------
                             *
                             * Exemplo:
                             *
                             * mínimo = 10
                             *
                             * estoque antes = 15
                             * estoque depois = 10
                             *
                             * Cria alerta.
                             *
                             * Se depois cair:
                             *
                             * 10 -> 8
                             *
                             * não cria outro alerta de estoque baixo,
                             * pois antes ele já estava dentro do limite.
                             */
                        } elseif (
                            $estoqueDepois <= $estoqueMinimo
                            &&
                            $estoqueDepois > 0
                            &&
                            $estoqueAntes > $estoqueMinimo
                        ) {

                            $tipoNotificacao =
                                'alerta';

                            $tituloNotificacao =
                                'Estoque baixo';

                            $mensagemNotificacao =
                                'O produto "'
                                . $produtoAtual['nome']
                                . '" atingiu o estoque mínimo. '
                                . 'Estoque atual: '
                                . $estoqueDepois
                                . ' '
                                . $produtoAtual['unidade']
                                . '.';
                        }

                        /*
                         * ------------------------------------------------
                         * 12. Envia a notificação aos administradores
                         * ------------------------------------------------
                         */
                        if ($tipoNotificacao !== null) {

                            $stmtAdministradores =
                                $pdo->query("
                                    SELECT
                                        id

                                    FROM usuarios

                                    WHERE perfil = 'administrador'
                                      AND ativo = 1
                                ");

                            $administradores =
                                $stmtAdministradores->fetchAll();

                            foreach (
                                $administradores
                                as $administrador
                            ) {

                                criarNotificacao(
                                    (int) $administrador['id'],
                                    $tipoNotificacao,
                                    $tituloNotificacao,
                                    $mensagemNotificacao,
                                    'produto',
                                    $produtoId,
                                    BASE_URL
                                    . '/products/view.php?id='
                                    . $produtoId
                                );
                            }
                        }

                        /*
                         * ----------------------------------------
                         * 13. Confirma tudo
                         * ----------------------------------------
                         */
                        $pdo->commit();

                        definirMensagem(
                            'sucesso',
                            'Saída de estoque registrada com sucesso!'
                        );

                        /*
                         * Volta para o produto.
                         */
                        header(
                            'Location: '
                            . BASE_URL
                            . '/products/view.php?id='
                            . $produtoId
                        );

                        exit;
                    }
                }

            } catch (Throwable $e) {

                /*
                 * Se alguma etapa falhar, desfaz tudo.
                 */
                if ($pdo->inTransaction()) {

                    $pdo->rollBack();
                }

                /*
                 * Guarda o erro real no log do PHP.
                 */
                error_log(
                    'Erro ao registrar saída de estoque: '
                    . $e->getMessage()
                );

                /*
                 * Não mostramos detalhes técnicos para o usuário.
                 */
                $erro =
                    'Ocorreu um erro ao registrar a saída. '
                    . 'Tente novamente.';
            }
        }
    }
}

/*
 * ================================================================
 * PRODUTOS DISPONÍVEIS NO FORMULÁRIO
 * ================================================================
 */
$produtos = $pdo->query("
    SELECT
        id,
        nome,
        quantidade_estoque,
        unidade

    FROM produtos

    WHERE ativo = 1

    ORDER BY nome ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="conteudo" id="conteudo-principal" tabindex="-1">

    <h1>
        Registrar Saída de Estoque
    </h1>

    <?php if ($erro): ?>

        <div class="alerta alerta-erro" role="alert">

            <?php echo limpar($erro); ?>

        </div>

    <?php endif; ?>

    <form method="POST" action="" class="formulario">

        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <!-- =====================================================
             PRODUTO
             ===================================================== -->

        <label for="produto_id">
            Produto *
        </label>

        <select name="produto_id" id="produto_id" required>

            <option value="">
                Selecione um produto...
            </option>

            <?php foreach ($produtos as $produto): ?>

                <option value="<?php echo (int) $produto['id']; ?>" <?php
                    echo $produtoSelecionado == $produto['id']
                        ? 'selected'
                        : '';
                    ?>>

                    <?php
                    echo limpar(
                        $produto['nome']
                    );
                    ?>

                    (estoque atual:

                    <?php
                    echo (int) $produto['quantidade_estoque'];
                    ?>

                    <?php
                    echo limpar(
                        $produto['unidade']
                    );
                    ?>)

                </option>

            <?php endforeach; ?>

        </select>

        <!-- =====================================================
             QUANTIDADE
             ===================================================== -->

        <label for="quantidade">
            Quantidade *
        </label>

        <input type="number" name="quantidade" id="quantidade" min="1" required
            value="<?php echo limpar($_POST['quantidade'] ?? ''); ?>">

        <!-- =====================================================
             OBSERVAÇÃO
             ===================================================== -->

        <label for="observacao">
            Observação
        </label>

        <textarea name="observacao" id="observacao" rows="3"
            placeholder="Ex: Venda ao cliente Y, pedido nº..."><?php echo limpar($_POST['observacao'] ?? ''); ?></textarea>

        <!-- =====================================================
             AÇÕES
             ===================================================== -->

        <div class="acoes-formulario">

            <button type="submit" class="botao botao-alerta">
                Registrar Saída
            </button>

            <a href="<?php echo BASE_URL; ?>/stock/history.php" class="botao botao-secundario">
                Cancelar
            </a>

        </div>

    </form>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

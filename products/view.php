<?php
/**
 * products/view.php
 * ---------------------------------------------------------------
 * Exibe os detalhes de um produto, seus anexos e
 * o histórico de movimentações.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

/*
 * ---------------------------------------------------------------
 * 1. Busca o produto
 * ---------------------------------------------------------------
 */
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        p.*,
        c.nome AS categoria_nome,
        f.nome AS fornecedor_nome

    FROM produtos p

    LEFT JOIN categorias c
        ON c.id = p.categoria_id

    LEFT JOIN fornecedores f
        ON f.id = p.fornecedor_id

    WHERE p.id = :id
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
 * ---------------------------------------------------------------
 * 2. Busca os anexos do produto
 * ---------------------------------------------------------------
 */
$stmtAnexos = $pdo->prepare("
    SELECT
        id,
        nome_original,
        tipo_mime,
        extensao,
        tamanho,
        usuario_nome,
        criado_em

    FROM anexos

    WHERE entidade = 'produto'
      AND entidade_id = :produto_id

    ORDER BY criado_em DESC, id DESC
");

$stmtAnexos->execute([
    ':produto_id' => $id
]);

$anexos = $stmtAnexos->fetchAll();

/*
 * ---------------------------------------------------------------
 * 3. Histórico de movimentações
 * ---------------------------------------------------------------
 */
$stmtMov = $pdo->prepare("
    SELECT
        m.*,
        u.nome AS usuario_nome

    FROM movimentacoes m

    INNER JOIN usuarios u
        ON u.id = m.usuario_id

    WHERE m.produto_id = :produto_id

    ORDER BY m.data_movimentacao DESC
");

$stmtMov->execute([
    ':produto_id' => $id
]);

$movimentacoes = $stmtMov->fetchAll();

/*
 * ---------------------------------------------------------------
 * 4. Função para mostrar tamanho dos anexos
 * ---------------------------------------------------------------
 */
function formatarTamanhoAnexo(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {

        return number_format(
            $bytes / (1024 * 1024),
            2,
            ',',
            '.'
        ) . ' MB';
    }

    if ($bytes >= 1024) {

        return number_format(
            $bytes / 1024,
            1,
            ',',
            '.'
        ) . ' KB';
    }

    return $bytes . ' bytes';
}

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="conteudo" id="conteudo-principal" tabindex="-1">

    <!-- =========================================================
         TOPO
         ========================================================= -->

    <div class="topo-pagina">

        <h1>
            <?php echo limpar($produto['nome']); ?>
        </h1>

        <div>

            <a href="<?php echo BASE_URL; ?>/products/edit.php?id=<?php echo (int) $produto['id']; ?>" class="botao">
                Editar
            </a>

            <a href="<?php echo BASE_URL; ?>/stock/entry.php?produto_id=<?php echo (int) $produto['id']; ?>"
                class="botao botao-sucesso">
                + Entrada
            </a>

            <?php if (ehAdministrador()): ?>
                <a href="<?php echo BASE_URL; ?>/stock/adjust.php?produto_id=<?php echo (int) $produto['id']; ?>"
                    class="botao botao-secundario">Ajustar saldo</a>
            <?php endif; ?>

            <a href="<?php echo BASE_URL; ?>/stock/exit.php?produto_id=<?php echo (int) $produto['id']; ?>"
                class="botao botao-alerta">
                - Saída
            </a>

        </div>

    </div>

    <!-- =========================================================
         DADOS DO PRODUTO
         ========================================================= -->

    <div class="painel">

        <div class="grade-detalhes">

            <div>

                <strong>
                    SKU:
                </strong>

                <?php
                echo limpar(
                    $produto['sku']
                    ?? '-'
                );
                ?>

            </div>

            <div>

                <strong>
                    Código de Barras:
                </strong>

                <?php
                echo limpar(
                    $produto['codigo_barras']
                    ?? '-'
                );
                ?>

            </div>

            <div>

                <strong>
                    Categoria:
                </strong>

                <?php
                echo limpar(
                    $produto['categoria_nome']
                    ?? '-'
                );
                ?>

            </div>

            <div>

                <strong>
                    Fornecedor:
                </strong>

                <?php
                echo limpar(
                    $produto['fornecedor_nome']
                    ?? '-'
                );
                ?>

            </div>

            <div>

                <strong>
                    Preço de Custo:
                </strong>

                <?php
                echo formatarMoeda(
                    $produto['preco_custo']
                );
                ?>

            </div>

            <div>

                <strong>
                    Preço de Venda:
                </strong>

                <?php
                echo formatarMoeda(
                    $produto['preco_venda']
                );
                ?>

            </div>

            <div>

                <strong>
                    Estoque Atual:
                </strong>

                <?php
                echo (int) $produto['quantidade_estoque'];
                ?>

                <?php
                echo limpar(
                    $produto['unidade']
                );
                ?>

            </div>

            <div>

                <strong>
                    Estoque Mínimo:
                </strong>

                <?php
                echo (int) $produto['estoque_minimo'];
                ?>

            </div>

            <div>

                <strong>
                    Status:
                </strong>

                <span class="etiqueta <?php echo $produto['ativo']
                    ? 'etiqueta-ativo'
                    : 'etiqueta-inativo'; ?>">

                    <?php
                    echo $produto['ativo']
                        ? 'Ativo'
                        : 'Inativo';
                    ?>

                </span>

            </div>

            <div>

                <strong>
                    Cadastrado em:
                </strong>

                <?php
                echo formatarDataHora(
                    $produto['data_cadastro']
                );
                ?>

            </div>

        </div>

        <?php if (!empty($produto['descricao'])): ?>

            <p class="descricao-produto">

                <strong>
                    Descrição:
                </strong>

                <?php
                echo nl2br(
                    limpar(
                        $produto['descricao']
                    )
                );
                ?>

            </p>

        <?php endif; ?>

    </div>

    <!-- =========================================================
         ANEXOS
         ========================================================= -->

    <div class="painel">

        <h2>
            Anexos
        </h2>

        <p>
            Adicione documentos ou imagens relacionados a este produto.
        </p>

        <form method="POST" action="<?php echo BASE_URL; ?>/attachments/upload.php" enctype="multipart/form-data"
            class="formulario">

            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

            <input type="hidden" name="produto_id" value="<?php echo (int) $produto['id']; ?>">

            <label for="anexo">
                Selecionar arquivo
            </label>

            <input type="file" name="anexo" id="anexo" accept=".pdf,.jpg,.jpeg,.png,.webp" required>

            <small>
                Formatos permitidos:
                PDF, JPG, PNG e WEBP.
                Máximo de 5 MB.
            </small>

            <div class="acoes-formulario">

                <button type="submit" class="botao">
                    Enviar Anexo
                </button>

            </div>

        </form>

        <hr>

        <h3>
            Arquivos deste produto
        </h3>

        <?php if (empty($anexos)): ?>

            <p class="texto-vazio">
                Nenhum anexo cadastrado para este produto.
            </p>

        <?php else: ?>

            <table class="tabela">

                <thead>

                    <tr>

                        <th scope="col">
                            Arquivo
                        </th>

                        <th scope="col">
                            Tipo
                        </th>

                        <th scope="col">
                            Tamanho
                        </th>

                        <th scope="col">
                            Enviado por
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

                    <?php foreach ($anexos as $anexo): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?php
                                    echo limpar(
                                        $anexo['nome_original']
                                    );
                                    ?>
                                </strong>

                            </td>

                            <td>

                                <?php
                                echo strtoupper(
                                    limpar(
                                        $anexo['extensao']
                                    )
                                );
                                ?>

                            </td>

                            <td>

                                <?php
                                echo limpar(
                                    formatarTamanhoAnexo(
                                        (int) $anexo['tamanho']
                                    )
                                );
                                ?>

                            </td>

                            <td>

                                <?php
                                echo limpar(
                                    $anexo['usuario_nome']
                                    ?? 'Usuário removido'
                                );
                                ?>

                            </td>

                            <td>

                                <?php
                                echo formatarDataHora(
                                    $anexo['criado_em']
                                );
                                ?>

                            </td>

                            <td>

                                <a href="<?php echo BASE_URL; ?>/attachments/view.php?id=<?php echo (int) $anexo['id']; ?>"
                                    class="botao botao-secundario" target="_blank" rel="noopener">
                                    Visualizar
                                </a>

                                <a href="<?php echo BASE_URL; ?>/attachments/view.php?id=<?php echo (int) $anexo['id']; ?>&download=1"
                                    class="botao">
                                    Baixar
                                </a>

                                <?php if (ehAdministrador()): ?>

                                    <form method="POST" action="<?php echo BASE_URL; ?>/attachments/delete.php"
                                        class="form-inline"
                                        data-confirm="Tem certeza que deseja excluir este anexo? Esta ação não pode ser desfeita.">

                                        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

                                        <input type="hidden" name="anexo_id" value="<?php echo (int) $anexo['id']; ?>">

                                        <button type="submit" class="botao botao-alerta">
                                            Excluir
                                        </button>

                                    </form>

                                <?php endif; ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

    <!-- =========================================================
         HISTÓRICO DE MOVIMENTAÇÕES
         ========================================================= -->

    <div class="painel">

        <h2>
            Histórico de Movimentações
        </h2>

        <?php if (empty($movimentacoes)): ?>

            <p class="texto-vazio">
                Nenhuma movimentação registrada para este produto.
            </p>

        <?php else: ?>

            <table class="tabela">

                <thead>

                    <tr>

                        <th scope="col">
                            Tipo
                        </th>

                        <th scope="col">
                            Quantidade
                        </th>

                        <th scope="col">
                            Usuário
                        </th>

                        <th scope="col">
                            Data
                        </th>

                        <th scope="col">
                            Observação
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($movimentacoes as $mov): ?>

                        <tr>

                            <td>

                                <span class="etiqueta etiqueta-<?php echo limpar($mov['tipo']); ?>">

                                    <?php
                                    echo $mov['tipo'] === 'entrada'
                                        ? 'Entrada'
                                        : 'Saída';
                                    ?>

                                </span>

                            </td>

                            <td>
                                <?php echo (int) $mov['quantidade']; ?>
                            </td>

                            <td>
                                <?php echo limpar($mov['usuario_nome']); ?>
                            </td>

                            <td>

                                <?php
                                echo formatarDataHora(
                                    $mov['data_movimentacao']
                                );
                                ?>

                            </td>

                            <td>

                                <?php
                                echo limpar(
                                    $mov['observacao']
                                    ?? ''
                                );
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

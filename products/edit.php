<?php
/**
 * products/edit.php
 * Formulário e processamento de edição de produto existente.
 * OBS: a quantidade em estoque não é editável diretamente aqui;
 * ela é alterada apenas através dos registros de movimentação
 * (entrada/saída), garantindo consistência do histórico.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = :id");
$stmt->execute([':id' => $id]);
$produto = $stmt->fetch();

if (!$produto) {
    definirMensagem('erro', 'Produto não encontrado.');
    header('Location: ' . BASE_URL . '/products/list.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        foreach (['nome', 'descricao', 'categoria_id', 'fornecedor_id', 'preco_custo', 'preco_venda', 'estoque_minimo', 'unidade'] as $campo) {
            $produto[$campo] = trim($_POST[$campo] ?? '');
        }

        if ($produto['nome'] === '') {
            $erro = 'O nome do produto é obrigatório.';
        } elseif (!is_numeric($produto['preco_venda']) || (float) $produto['preco_venda'] < 0) {
            $erro = 'Informe um preço de venda válido.';
        } else {
            $stmtUpdate = $pdo->prepare("
                UPDATE produtos SET
                    nome = :nome, descricao = :descricao, categoria_id = :categoria_id,
                    fornecedor_id = :fornecedor_id, preco_custo = :preco_custo, preco_venda = :preco_venda,
                    estoque_minimo = :estoque_minimo, unidade = :unidade
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':nome' => $produto['nome'],
                ':descricao' => $produto['descricao'] ?: null,
                ':categoria_id' => $produto['categoria_id'] ?: null,
                ':fornecedor_id' => $produto['fornecedor_id'] ?: null,
                ':preco_custo' => $produto['preco_custo'] !== '' ? $produto['preco_custo'] : 0,
                ':preco_venda' => $produto['preco_venda'],
                ':estoque_minimo' => $produto['estoque_minimo'] !== '' ? (int) $produto['estoque_minimo'] : 0,
                ':unidade' => $produto['unidade'] ?: 'UN',
                ':id' => $id
            ]);

            definirMensagem('sucesso', 'Produto atualizado com sucesso!');
            header('Location: ' . BASE_URL . '/products/list.php');
            exit;
        }
    }
}

$categorias = $pdo->query("SELECT id, nome FROM categorias WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();
$fornecedores = $pdo->query("SELECT id, nome FROM fornecedores WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo">
    <h1>Editar Produto</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?php echo limpar($produto['nome']); ?>">

        <label for="descricao">Descrição</label>
        <textarea name="descricao" id="descricao" rows="3"><?php echo limpar($produto['descricao'] ?? ''); ?></textarea>

        <div class="linha-formulario">
            <div>
                <label for="categoria_id">Categoria</label>
                <select name="categoria_id" id="categoria_id">
                    <option value="">Selecione...</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?php echo $categoria['id']; ?>" <?php echo $produto['categoria_id'] == $categoria['id'] ? 'selected' : ''; ?>>
                            <?php echo limpar($categoria['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="fornecedor_id">Fornecedor</label>
                <select name="fornecedor_id" id="fornecedor_id">
                    <option value="">Selecione...</option>
                    <?php foreach ($fornecedores as $fornecedor): ?>
                        <option value="<?php echo $fornecedor['id']; ?>" <?php echo $produto['fornecedor_id'] == $fornecedor['id'] ? 'selected' : ''; ?>>
                            <?php echo limpar($fornecedor['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="linha-formulario">
            <div>
                <label for="preco_custo">Preço de Custo</label>
                <input type="number" step="0.01" min="0" name="preco_custo" id="preco_custo" value="<?php echo limpar($produto['preco_custo']); ?>">
            </div>
            <div>
                <label for="preco_venda">Preço de Venda *</label>
                <input type="number" step="0.01" min="0" name="preco_venda" id="preco_venda" required value="<?php echo limpar($produto['preco_venda']); ?>">
            </div>
        </div>

        <div class="linha-formulario">
            <div>
                <label>Quantidade em Estoque (atual)</label>
                <input type="text" value="<?php echo (int) $produto['quantidade_estoque']; ?>" disabled>
                <small>Para alterar o estoque, use os registros de Entrada/Saída.</small>
            </div>
            <div>
                <label for="estoque_minimo">Estoque Mínimo</label>
                <input type="number" min="0" name="estoque_minimo" id="estoque_minimo" value="<?php echo limpar($produto['estoque_minimo']); ?>">
            </div>
            <div>
                <label for="unidade">Unidade</label>
                <input type="text" name="unidade" id="unidade" maxlength="20" value="<?php echo limpar($produto['unidade']); ?>">
            </div>
        </div>

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar Alterações</button>
            <a href="<?php echo BASE_URL; ?>/products/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

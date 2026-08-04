<?php
/**
 * products/create.php
 * Formulário e processamento de cadastro de novo produto.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$erro = '';
$dados = [
    'nome' => '', 'descricao' => '', 'categoria_id' => '', 'fornecedor_id' => '',
    'preco_custo' => '', 'preco_venda' => '', 'quantidade_estoque' => '0',
    'estoque_minimo' => '0', 'unidade' => 'UN'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        foreach ($dados as $campo => $valor) {
            $dados[$campo] = trim($_POST[$campo] ?? '');
        }

        if ($dados['nome'] === '') {
            $erro = 'O nome do produto é obrigatório.';
        } elseif (!is_numeric($dados['preco_venda']) || (float) $dados['preco_venda'] < 0) {
            $erro = 'Informe um preço de venda válido.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO produtos
                    (nome, descricao, categoria_id, fornecedor_id, preco_custo, preco_venda,
                     quantidade_estoque, estoque_minimo, unidade, ativo)
                VALUES
                    (:nome, :descricao, :categoria_id, :fornecedor_id, :preco_custo, :preco_venda,
                     :quantidade_estoque, :estoque_minimo, :unidade, 1)
            ");
            $stmt->execute([
                ':nome' => $dados['nome'],
                ':descricao' => $dados['descricao'] ?: null,
                ':categoria_id' => $dados['categoria_id'] ?: null,
                ':fornecedor_id' => $dados['fornecedor_id'] ?: null,
                ':preco_custo' => $dados['preco_custo'] !== '' ? $dados['preco_custo'] : 0,
                ':preco_venda' => $dados['preco_venda'],
                ':quantidade_estoque' => $dados['quantidade_estoque'] !== '' ? (int) $dados['quantidade_estoque'] : 0,
                ':estoque_minimo' => $dados['estoque_minimo'] !== '' ? (int) $dados['estoque_minimo'] : 0,
                ':unidade' => $dados['unidade'] ?: 'UN',
            ]);

            definirMensagem('sucesso', 'Produto cadastrado com sucesso!');
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
    <h1>Novo Produto</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?php echo limpar($dados['nome']); ?>">

        <label for="descricao">Descrição</label>
        <textarea name="descricao" id="descricao" rows="3"><?php echo limpar($dados['descricao']); ?></textarea>

        <div class="linha-formulario">
            <div>
                <label for="categoria_id">Categoria</label>
                <select name="categoria_id" id="categoria_id">
                    <option value="">Selecione...</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?php echo $categoria['id']; ?>" <?php echo $dados['categoria_id'] == $categoria['id'] ? 'selected' : ''; ?>>
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
                        <option value="<?php echo $fornecedor['id']; ?>" <?php echo $dados['fornecedor_id'] == $fornecedor['id'] ? 'selected' : ''; ?>>
                            <?php echo limpar($fornecedor['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="linha-formulario">
            <div>
                <label for="preco_custo">Preço de Custo</label>
                <input type="number" step="0.01" min="0" name="preco_custo" id="preco_custo" value="<?php echo limpar($dados['preco_custo']); ?>">
            </div>
            <div>
                <label for="preco_venda">Preço de Venda *</label>
                <input type="number" step="0.01" min="0" name="preco_venda" id="preco_venda" required value="<?php echo limpar($dados['preco_venda']); ?>">
            </div>
        </div>

        <div class="linha-formulario">
            <div>
                <label for="quantidade_estoque">Quantidade em Estoque</label>
                <input type="number" min="0" name="quantidade_estoque" id="quantidade_estoque" value="<?php echo limpar($dados['quantidade_estoque']); ?>">
            </div>
            <div>
                <label for="estoque_minimo">Estoque Mínimo</label>
                <input type="number" min="0" name="estoque_minimo" id="estoque_minimo" value="<?php echo limpar($dados['estoque_minimo']); ?>">
            </div>
            <div>
                <label for="unidade">Unidade</label>
                <input type="text" name="unidade" id="unidade" maxlength="20" value="<?php echo limpar($dados['unidade']); ?>">
            </div>
        </div>

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar</button>
            <a href="<?php echo BASE_URL; ?>/products/list.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

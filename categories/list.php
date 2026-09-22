<?php
/**
 * categories/list.php
 * Lista, pesquisa e permite ativar/inativar categorias.
 * Administradores e funcionários podem cadastrar/editar categorias.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$busca = trim($_GET['busca'] ?? '');

if ($busca !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM categorias
        WHERE nome LIKE :busca
        ORDER BY nome ASC
    ");
    $stmt->execute([':busca' => '%' . $busca . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC");
}
$categorias = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <div class="topo-pagina">
        <h1>Categorias</h1>
        <a href="<?php echo BASE_URL; ?>/categories/create.php" class="botao">+ Nova Categoria</a>
    </div>

    <form method="GET" action="" class="form-busca">
        <input type="text" name="busca" placeholder="Buscar categoria pelo nome..." value="<?php echo limpar($busca); ?>" aria-label="Buscar">
        <button type="submit" class="botao">Buscar</button>
        <?php if ($busca !== ''): ?>
            <a href="<?php echo BASE_URL; ?>/categories/list.php" class="botao botao-secundario">Limpar</a>
        <?php endif; ?>
    </form>

    <?php if (empty($categorias)): ?>
        <p class="texto-vazio">Nenhuma categoria encontrada.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">Descrição</th>
                    <th scope="col">Status</th>
                    <th scope="col">Cadastrada em</th>
                    <th scope="col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <td><?php echo limpar($categoria['nome']); ?></td>
                    <td><?php echo limpar($categoria['descricao'] ?? ''); ?></td>
                    <td>
                        <span class="etiqueta <?php echo $categoria['ativo'] ? 'etiqueta-ativo' : 'etiqueta-inativo'; ?>">
                            <?php echo $categoria['ativo'] ? 'Ativa' : 'Inativa'; ?>
                        </span>
                    </td>
                    <td><?php echo formatarData($categoria['data_cadastro']); ?></td>
                    <td class="acoes">
                        <a href="<?php echo BASE_URL; ?>/categories/edit.php?id=<?php echo $categoria['id']; ?>">Editar</a>
                        <form method="POST" action="<?php echo BASE_URL; ?>/categories/toggle.php" class="form-inline" onsubmit="return confirm('Deseja realmente alterar o status desta categoria?');">
                            <input type="hidden" name="id" value="<?php echo $categoria['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
                            <button type="submit" class="link-acao">
                                <?php echo $categoria['ativo'] ? 'Inativar' : 'Ativar'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

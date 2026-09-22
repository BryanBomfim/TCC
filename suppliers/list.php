<?php
/**
 * suppliers/list.php
 * Lista, pesquisa e permite ativar/inativar fornecedores.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$busca = trim($_GET['busca'] ?? '');

if ($busca !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM fornecedores
        WHERE nome LIKE :busca OR cnpj_cpf LIKE :busca
        ORDER BY nome ASC
    ");
    $stmt->execute([':busca' => '%' . $busca . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM fornecedores ORDER BY nome ASC");
}
$fornecedores = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <div class="topo-pagina">
        <h1>Fornecedores</h1>
        <a href="<?php echo BASE_URL; ?>/suppliers/create.php" class="botao">+ Novo Fornecedor</a>
    </div>

    <form method="GET" action="" class="form-busca">
        <input type="text" name="busca" placeholder="Buscar por nome ou CNPJ/CPF..." value="<?php echo limpar($busca); ?>" aria-label="Buscar">
        <button type="submit" class="botao">Buscar</button>
        <?php if ($busca !== ''): ?>
            <a href="<?php echo BASE_URL; ?>/suppliers/list.php" class="botao botao-secundario">Limpar</a>
        <?php endif; ?>
    </form>

    <?php if (empty($fornecedores)): ?>
        <p class="texto-vazio">Nenhum fornecedor encontrado.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">CNPJ/CPF</th>
                    <th scope="col">Telefone</th>
                    <th scope="col">E-mail</th>
                    <th scope="col">Status</th>
                    <th scope="col">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fornecedores as $fornecedor): ?>
                <tr>
                    <td><?php echo limpar($fornecedor['nome']); ?></td>
                    <td><?php echo limpar($fornecedor['cnpj_cpf'] ?? ''); ?></td>
                    <td><?php echo limpar($fornecedor['telefone'] ?? ''); ?></td>
                    <td><?php echo limpar($fornecedor['email'] ?? ''); ?></td>
                    <td>
                        <span class="etiqueta <?php echo $fornecedor['ativo'] ? 'etiqueta-ativo' : 'etiqueta-inativo'; ?>">
                            <?php echo $fornecedor['ativo'] ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </td>
                    <td class="acoes">
                        <a href="<?php echo BASE_URL; ?>/suppliers/edit.php?id=<?php echo $fornecedor['id']; ?>">Editar</a>
                        <form method="POST" action="<?php echo BASE_URL; ?>/suppliers/toggle.php" class="form-inline" onsubmit="return confirm('Deseja realmente alterar o status deste fornecedor?');">
                            <input type="hidden" name="id" value="<?php echo $fornecedor['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
                            <button type="submit" class="link-acao">
                                <?php echo $fornecedor['ativo'] ? 'Inativar' : 'Ativar'; ?>
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

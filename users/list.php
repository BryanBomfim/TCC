<?php
/**
 * users/list.php
 * Lista os usuários do sistema. Acesso restrito ao administrador.
 */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$busca = trim($_GET['busca'] ?? '');

if ($busca !== '') {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nome LIKE :busca OR email LIKE :busca ORDER BY nome ASC");
    $stmt->execute([':busca' => '%' . $busca . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome ASC");
}
$usuarios = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <div class="topo-pagina">
        <h1>Usuários</h1>
        <a href="<?php echo BASE_URL; ?>/users/create.php" class="botao">+ Novo Usuário</a>
    </div>

    <form method="GET" action="" class="form-busca">
        <input type="text" name="busca" placeholder="Buscar por nome ou e-mail..." value="<?php echo limpar($busca); ?>" aria-label="Buscar">
        <button type="submit" class="botao">Buscar</button>
        <?php if ($busca !== ''): ?>
            <a href="<?php echo BASE_URL; ?>/users/list.php" class="botao botao-secundario">Limpar</a>
        <?php endif; ?>
    </form>

    <?php if (empty($usuarios)): ?>
        <p class="texto-vazio">Nenhum usuário encontrado.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr><th scope="col">Nome</th><th scope="col">E-mail</th><th scope="col">Perfil</th><th scope="col">Status</th><th scope="col">Cadastrado em</th><th scope="col">Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?php echo limpar($usuario['nome']); ?></td>
                    <td><?php echo limpar($usuario['email']); ?></td>
                    <td><?php echo $usuario['perfil'] === 'administrador' ? 'Administrador' : 'Funcionário'; ?></td>
                    <td>
                        <span class="etiqueta <?php echo $usuario['ativo'] ? 'etiqueta-ativo' : 'etiqueta-inativo'; ?>">
                            <?php echo $usuario['ativo'] ? 'Ativo' : 'Inativo'; ?>
                        </span>
                    </td>
                    <td><?php echo formatarData($usuario['data_cadastro']); ?></td>
                    <td class="acoes">
                        <a href="<?php echo BASE_URL; ?>/users/edit.php?id=<?php echo $usuario['id']; ?>">Editar</a>
                        <?php if ((int) $usuario['id'] !== (int) $_SESSION['usuario_id']): ?>
                        <form method="POST" action="<?php echo BASE_URL; ?>/users/toggle.php" class="form-inline" data-confirm="Deseja realmente alterar o status deste usuário?">
                            <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
                            <button type="submit" class="link-acao">
                                <?php echo $usuario['ativo'] ? 'Inativar' : 'Ativar'; ?>
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="texto-esmaecido">(você)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

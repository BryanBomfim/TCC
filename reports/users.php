<?php
/**
 * reports/users.php
 * Relatório de usuários com a quantidade de movimentações realizadas por cada um.
 * Acesso restrito ao administrador.
 */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$stmt = $pdo->query("
    SELECT u.*, COUNT(m.id) AS total_movimentacoes
    FROM usuarios u
    LEFT JOIN movimentacoes m ON m.usuario_id = u.id
    GROUP BY u.id
    ORDER BY u.nome ASC
");
$usuarios = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo">
    <div class="topo-pagina">
        <h1>Relatório de Usuários</h1>
        <button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir</button>
    </div>

    <?php if (empty($usuarios)): ?>
        <p class="texto-vazio">Nenhum usuário cadastrado.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Movimentações Realizadas</th></tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?php echo limpar($usuario['nome']); ?></td>
                    <td><?php echo limpar($usuario['email']); ?></td>
                    <td><?php echo $usuario['perfil'] === 'administrador' ? 'Administrador' : 'Funcionário'; ?></td>
                    <td><?php echo $usuario['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                    <td><?php echo (int) $usuario['total_movimentacoes']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

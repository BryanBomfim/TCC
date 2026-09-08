<?php
/**
 * reports/suppliers.php
 * Relatório de fornecedores com a quantidade de produtos vinculados a cada um.
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin();

$stmt = $pdo->query("
    SELECT f.*, COUNT(p.id) AS total_produtos
    FROM fornecedores f
    LEFT JOIN produtos p ON p.fornecedor_id = f.id
    GROUP BY f.id
    ORDER BY f.nome ASC
");
$fornecedores = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <div class="topo-pagina">
        <h1>Relatório de Fornecedores</h1>
        <button type="button" class="botao botao-secundario" onclick="window.print()">Imprimir</button>
    </div>

    <?php if (empty($fornecedores)): ?>
        <p class="texto-vazio">Nenhum fornecedor cadastrado.</p>
    <?php else: ?>
        <table class="tabela">
            <thead>
                <tr><th scope="col">Fornecedor</th><th scope="col">CNPJ/CPF</th><th scope="col">Telefone</th><th scope="col">Status</th><th scope="col">Produtos Vinculados</th></tr>
            </thead>
            <tbody>
                <?php foreach ($fornecedores as $fornecedor): ?>
                <tr>
                    <td><?php echo limpar($fornecedor['nome']); ?></td>
                    <td><?php echo limpar($fornecedor['cnpj_cpf'] ?? '-'); ?></td>
                    <td><?php echo limpar($fornecedor['telefone'] ?? '-'); ?></td>
                    <td><?php echo $fornecedor['ativo'] ? 'Ativo' : 'Inativo'; ?></td>
                    <td><?php echo (int) $fornecedor['total_produtos']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * settings/index.php
 * Área de configurações gerais do sistema. Acesso restrito ao administrador.
 * Permite alterar parâmetros armazenados na tabela "configuracoes".
 */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $nomeSistema = trim($_POST['nome_sistema'] ?? '');
        $nomeEmpresa = trim($_POST['nome_empresa'] ?? '');
        $estoqueMinimoPadrao = trim($_POST['estoque_minimo_padrao'] ?? '');

        if ($nomeSistema === '' || $nomeEmpresa === '') {
            $erro = 'Preencha os campos obrigatórios.';
        } else {
            $valores = [
                'nome_sistema' => $nomeSistema,
                'nome_empresa' => $nomeEmpresa,
                'estoque_minimo_padrao' => $estoqueMinimoPadrao !== '' ? (int) $estoqueMinimoPadrao : 0,
            ];

            $stmt = $pdo->prepare("
                INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)
                ON DUPLICATE KEY UPDATE valor = :valor
            ");
            foreach ($valores as $chave => $valor) {
                $stmt->execute([':chave' => $chave, ':valor' => $valor]);
            }

            definirMensagem('sucesso', 'Configurações atualizadas com sucesso!');
            header('Location: ' . BASE_URL . '/settings/index.php');
            exit;
        }
    }
}

$stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
$configuracoesAtuais = [];
foreach ($stmt->fetchAll() as $linha) {
    $configuracoesAtuais[$linha['chave']] = $linha['valor'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <h1>Configurações do Sistema</h1>

    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?php echo limpar($erro); ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="formulario">
        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

        <label for="nome_sistema">Nome do Sistema *</label>
        <input type="text" name="nome_sistema" id="nome_sistema" required
               value="<?php echo limpar($configuracoesAtuais['nome_sistema'] ?? 'SmartControl'); ?>">

        <label for="nome_empresa">Nome da Empresa *</label>
        <input type="text" name="nome_empresa" id="nome_empresa" required
               value="<?php echo limpar($configuracoesAtuais['nome_empresa'] ?? ''); ?>">

        <label for="estoque_minimo_padrao">Estoque Mínimo Padrão (para novos produtos)</label>
        <input type="number" min="0" name="estoque_minimo_padrao" id="estoque_minimo_padrao"
               value="<?php echo limpar($configuracoesAtuais['estoque_minimo_padrao'] ?? '5'); ?>">

        <div class="acoes-formulario">
            <button type="submit" class="botao">Salvar Configurações</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

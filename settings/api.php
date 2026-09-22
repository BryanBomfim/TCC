<?php
/** Gerenciamento de tokens da API REST. */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$erro = '';
$tokenCriado = $_SESSION['api_token_criado'] ?? null;
unset($_SESSION['api_token_criado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'criar') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $dias = max(1, min(365, (int) ($_POST['dias'] ?? 90)));

            if ($nome === '') {
                $erro = 'Informe um nome para identificar o token.';
            } else {
                $token = 'sc_' . bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiraEm = date('Y-m-d H:i:s', strtotime('+' . $dias . ' days'));

                $stmt = $pdo->prepare("
                    INSERT INTO api_tokens (usuario_id, nome, token_hash, expira_em)
                    VALUES (:usuario_id, :nome, :token_hash, :expira_em)
                ");
                $stmt->execute([
                    ':usuario_id' => (int) $_SESSION['usuario_id'],
                    ':nome' => mb_substr($nome, 0, 100),
                    ':token_hash' => $tokenHash,
                    ':expira_em' => $expiraEm,
                ]);

                registrarAuditoria(
                    'CRIAR_TOKEN_API',
                    'API',
                    (int) $pdo->lastInsertId(),
                    'Token de API criado: ' . $nome . '.',
                    null,
                    ['nome' => $nome, 'expira_em' => $expiraEm]
                );

                $_SESSION['api_token_criado'] = $token;
                definirMensagem('sucesso', 'Token criado. Copie-o agora, pois ele não será exibido novamente.');
                header('Location: ' . BASE_URL . '/settings/api.php');
                exit;
            }
        } elseif ($acao === 'revogar') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("
                UPDATE api_tokens
                SET revogado_em = NOW()
                WHERE id = :id AND revogado_em IS NULL
            ");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                registrarAuditoria('REVOGAR_TOKEN_API', 'API', $id, 'Token de API revogado.');
                definirMensagem('sucesso', 'Token revogado com sucesso.');
            }
            header('Location: ' . BASE_URL . '/settings/api.php');
            exit;
        }
    }
}

$stmt = $pdo->query("
    SELECT t.id, t.nome, t.expira_em, t.ultimo_uso_em, t.revogado_em, t.criado_em, u.nome AS usuario_nome
    FROM api_tokens t
    INNER JOIN usuarios u ON u.id = t.usuario_id
    ORDER BY t.criado_em DESC
    LIMIT 100
");
$tokens = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>
    <div class="topo-pagina">
        <div>
            <h1>API REST</h1>
            <p class="descricao-pagina">Crie tokens para demonstrar integrações com a API v1 do SmartControl.</p>
        </div>
        <a class="botao botao-secundario" href="<?php echo BASE_URL; ?>/docs/API.md">Documentação</a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div><?php endif; ?>

    <?php if ($tokenCriado): ?>
        <div class="painel painel-token-api" role="status">
            <h2>Token gerado</h2>
            <p>Copie este valor agora. O SmartControl armazena somente o hash e não consegue mostrá-lo novamente.</p>
            <div class="campo-copia">
                <input id="tokenApiGerado" type="text" readonly value="<?php echo limpar($tokenCriado); ?>" aria-label="Token de API gerado">
                <button type="button" class="botao botao-secundario" data-copy-target="tokenApiGerado">Copiar</button>
            </div>
        </div>
    <?php endif; ?>

    <div class="painel">
        <h2>Novo token</h2>
        <form method="POST" class="formulario formulario-compacto">
            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
            <input type="hidden" name="acao" value="criar">
            <label for="nome">Identificação *</label>
            <input type="text" id="nome" name="nome" maxlength="100" required placeholder="Ex: integração da apresentação">
            <label for="dias">Validade</label>
            <select id="dias" name="dias">
                <option value="30">30 dias</option>
                <option value="90" selected>90 dias</option>
                <option value="180">180 dias</option>
                <option value="365">1 ano</option>
            </select>
            <div class="acoes-formulario"><button class="botao" type="submit">Gerar token</button></div>
        </form>
    </div>

    <div class="painel">
        <h2>Tokens cadastrados</h2>
        <?php if (!$tokens): ?>
            <p class="texto-vazio">Nenhum token criado.</p>
        <?php else: ?>
            <div class="tabela-responsiva">
                <table class="tabela">
                    <thead><tr><th>Nome</th><th>Criado por</th><th>Criado em</th><th>Expira em</th><th>Último uso</th><th>Status</th><th>Ação</th></tr></thead>
                    <tbody>
                    <?php foreach ($tokens as $token): ?>
                        <tr>
                            <td><?php echo limpar($token['nome']); ?></td>
                            <td><?php echo limpar($token['usuario_nome']); ?></td>
                            <td><?php echo formatarDataHora($token['criado_em']); ?></td>
                            <td><?php echo formatarDataHora($token['expira_em']); ?></td>
                            <td><?php echo formatarDataHora($token['ultimo_uso_em']); ?></td>
                            <td><?php echo $token['revogado_em'] ? 'Revogado' : (strtotime($token['expira_em']) < time() ? 'Expirado' : 'Ativo'); ?></td>
                            <td>
                                <?php if (!$token['revogado_em'] && strtotime($token['expira_em']) >= time()): ?>
                                    <form method="POST" onsubmit="return confirm('Revogar este token de API?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
                                        <input type="hidden" name="acao" value="revogar">
                                        <input type="hidden" name="id" value="<?php echo (int) $token['id']; ?>">
                                        <button class="botao-link botao-link-perigo" type="submit">Revogar</button>
                                    </form>
                                <?php else: ?>-
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

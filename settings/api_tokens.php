<?php
/** Gestão administrativa dos tokens de leitura da API REST. */
require_once __DIR__ . '/../config/config.php';
exigirAdministrador();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'criar') {
            $nome = trim($_POST['nome'] ?? '');
            $dias = (int) ($_POST['dias_validade'] ?? 90);

            if ($nome === '' || mb_strlen($nome) > 100) {
                $erro = 'Informe um nome com até 100 caracteres.';
            } elseif ($dias < 1 || $dias > 365) {
                $erro = 'A validade deve estar entre 1 e 365 dias.';
            } else {
                try {
                    $token = 'sc_' . bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare("
                        INSERT INTO api_tokens
                            (usuario_id, nome, token_hash, escopos, expira_em)
                        VALUES
                            (:usuario_id, :nome, :token_hash, 'leitura', :expira_em)
                    ");
                    $stmt->bindValue(':usuario_id', (int) $_SESSION['usuario_id'], PDO::PARAM_INT);
                    $stmt->bindValue(':nome', $nome);
                    $stmt->bindValue(':token_hash', hash('sha256', $token));
                    $stmt->bindValue(':expira_em', date('Y-m-d H:i:s', strtotime('+' . $dias . ' days')));
                    $stmt->execute();
                    $tokenId = (int) $pdo->lastInsertId();

                    registrarAuditoria(
                        'CRIAR_TOKEN_API',
                        'Configurações',
                        $tokenId,
                        'Token de leitura da API criado.',
                        null,
                        ['nome' => $nome, 'validade_dias' => $dias, 'escopo' => 'leitura']
                    );

                    $_SESSION['api_token_criado'] = $token;
                    definirMensagem('sucesso', 'Token criado. Copie-o agora; ele não será exibido novamente.');
                    header('Location: ' . BASE_URL . '/settings/api_tokens.php');
                    exit;
                } catch (Throwable $e) {
                    error_log('Erro ao criar token da API: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
                    $erro = 'Não foi possível criar o token.';
                }
            }
        } elseif ($acao === 'revogar') {
            $tokenId = (int) ($_POST['token_id'] ?? 0);
            $stmt = $pdo->prepare("
                UPDATE api_tokens
                SET revogado_em = NOW()
                WHERE id = :id AND revogado_em IS NULL
            ");
            $stmt->execute([':id' => $tokenId]);

            if ($stmt->rowCount() > 0) {
                registrarAuditoria('REVOGAR_TOKEN_API', 'Configurações', $tokenId, 'Token da API revogado.');
                definirMensagem('sucesso', 'Token revogado com sucesso.');
            }
            header('Location: ' . BASE_URL . '/settings/api_tokens.php');
            exit;
        }
    }
}

$tokenCriado = $_SESSION['api_token_criado'] ?? null;
unset($_SESSION['api_token_criado']);
$tokens = $pdo->query("
    SELECT t.id, t.nome, t.escopos, t.expira_em, t.ultimo_uso_em,
           t.revogado_em, t.criado_em, u.nome AS usuario_nome
    FROM api_tokens t
    INNER JOIN usuarios u ON u.id = t.usuario_id
    ORDER BY t.criado_em DESC, t.id DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <div class="topo-pagina">
        <div><h1>Tokens da API</h1><p class="descricao-pagina">Credenciais revogáveis para consultar a API REST v1.</p></div>
        <a class="botao botao-secundario" href="<?php echo BASE_URL; ?>/settings/index.php">Voltar às configurações</a>
    </div>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?php echo limpar($erro); ?></div><?php endif; ?>

    <?php if ($tokenCriado): ?>
        <div class="alerta alerta-aviso" role="status">
            <strong>Copie este token agora.</strong> Por segurança, somente o hash foi salvo.
            <div class="campo-copia">
                <input id="token-api-criado" type="text" readonly value="<?php echo limpar($tokenCriado); ?>">
                <button type="button" class="botao" data-copy-target="token-api-criado">Copiar</button>
            </div>
        </div>
    <?php endif; ?>

    <div class="painel">
        <h2>Novo token de leitura</h2>
        <form method="POST" class="formulario">
            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
            <input type="hidden" name="acao" value="criar">
            <label for="nome">Identificação *</label>
            <input id="nome" name="nome" required maxlength="100" placeholder="Ex.: integração do aplicativo móvel">
            <label for="dias_validade">Validade em dias *</label>
            <input id="dias_validade" name="dias_validade" type="number" min="1" max="365" value="90" required>
            <button class="botao" type="submit">Gerar token</button>
        </form>
    </div>

    <div class="painel">
        <h2>Tokens emitidos</h2>
        <?php if (!$tokens): ?>
            <p class="texto-vazio">Nenhum token foi criado.</p>
        <?php else: ?>
            <div class="tabela-responsiva"><table class="tabela">
                <thead><tr><th>Nome</th><th>Criado por</th><th>Escopo</th><th>Validade</th><th>Último uso</th><th>Status</th><th>Ação</th></tr></thead>
                <tbody><?php foreach ($tokens as $token): ?>
                    <?php $ativo = $token['revogado_em'] === null && ($token['expira_em'] === null || strtotime($token['expira_em']) > time()); ?>
                    <tr>
                        <td><?php echo limpar($token['nome']); ?></td>
                        <td><?php echo limpar($token['usuario_nome']); ?></td>
                        <td><?php echo limpar($token['escopos']); ?></td>
                        <td><?php echo formatarDataHora($token['expira_em']); ?></td>
                        <td><?php echo formatarDataHora($token['ultimo_uso_em']); ?></td>
                        <td><span class="etiqueta <?php echo $ativo ? 'etiqueta-ativo' : 'etiqueta-inativo'; ?>"><?php echo $ativo ? 'Ativo' : 'Inativo'; ?></span></td>
                        <td>
                            <?php if ($ativo): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
                                    <input type="hidden" name="acao" value="revogar">
                                    <input type="hidden" name="token_id" value="<?php echo (int) $token['id']; ?>">
                                    <button class="botao botao-alerta" type="submit" data-confirm="Revogar este token? A integração perderá o acesso imediatamente.">Revogar</button>
                                </form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

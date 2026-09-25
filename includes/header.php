<?php
/**
 * includes/header.php
 * -----------------------------------------------------------------
 * Cabeçalho comum das páginas internas do SmartControl.
 * O config/config.php deve ser carregado antes deste arquivo.
 * -----------------------------------------------------------------
 */

$mensagem = obterMensagem();
$totalNotificacoesNaoLidas = 0;

if (isset($_SESSION['usuario_id'])) {
    try {
        $stmtNotificacoesHeader = $pdo->prepare("
            SELECT COUNT(*)
            FROM notificacoes
            WHERE usuario_id = :usuario_id
              AND lida_em IS NULL
        ");

        $stmtNotificacoesHeader->execute([
            ':usuario_id' => (int) $_SESSION['usuario_id'],
        ]);

        $totalNotificacoesNaoLidas = (int) $stmtNotificacoesHeader->fetchColumn();
    } catch (Throwable $e) {
        // Uma falha nas notificações não deve derrubar o sistema.
        error_log('Erro ao contar notificações: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="<?php echo gerarTokenCSRF(); ?>">
    <meta name="app-base-url" content="<?php echo limpar(BASE_URL); ?>">
    <title>SmartControl - Sistema de Gerenciamento de Estoque</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/accessibility.css">
</head>
<body>
    <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo principal</a>

    <?php require __DIR__ . '/accessibility.php'; ?>

    <header class="topo">
        <div class="topo-titulo">
            <button
                class="botao-menu"
                id="botaoMenu"
                type="button"
                aria-label="Abrir menu"
                aria-controls="sidebar"
                aria-expanded="false"
            >
                &#9776;
            </button>

            <span>SmartControl</span>
        </div>

        <div class="topo-usuario">
            <a
                href="<?php echo BASE_URL; ?>/notifications/index.php"
                class="notificacoes-topo"
                title="Notificações"
                aria-label="<?php
                echo $totalNotificacoesNaoLidas > 0
                    ? $totalNotificacoesNaoLidas . ' notificações não lidas'
                    : 'Nenhuma notificação não lida';
                ?>"
            >
                <svg class="icone-notificacao" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M12 22a2.4 2.4 0 0 0 2.3-2h-4.6a2.4 2.4 0 0 0 2.3 2Zm7-5-2-2.5V10a5 5 0 0 0-4-4.9V4a1 1 0 1 0-2 0v1.1A5 5 0 0 0 7 10v4.5L5 17v1h14v-1Z"/>
                </svg>

                <?php if ($totalNotificacoesNaoLidas > 0): ?>
                    <span class="contador-notificacoes" aria-hidden="true">
                        <?php echo $totalNotificacoesNaoLidas > 99 ? '99+' : $totalNotificacoesNaoLidas; ?>
                    </span>
                <?php endif; ?>
            </a>

            <span class="usuario-logado">
                <?php echo limpar(nomeUsuarioLogado()); ?>
                (<?php echo $_SESSION['usuario_perfil'] === 'administrador' ? 'Administrador' : 'Funcionário'; ?>)
            </span>

            <form method="POST" action="<?php echo BASE_URL; ?>/auth/logout.php" class="form-sair">
                <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">
                <button type="submit" class="link-sair">Sair</button>
            </form>
        </div>
    </header>

    <?php if ($mensagem): ?>
        <div
            class="alerta alerta-<?php echo limpar($mensagem['tipo']); ?>"
            role="status"
            aria-live="polite"
        >
            <?php echo limpar($mensagem['texto']); ?>
        </div>
    <?php endif; ?>

    <div class="wrapper">

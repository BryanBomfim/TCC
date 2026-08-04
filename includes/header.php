<?php
/**
 * includes/header.php
 * Cabeçalho HTML comum a todas as páginas internas do sistema.
 * Espera que config/config.php já tenha sido incluído antes.
 */
$mensagem = obterMensagem();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartControl - Sistema de Gerenciamento de Estoque</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
<header class="topo">
    <div class="topo-titulo">
        <button class="botao-menu" id="botaoMenu" aria-label="Abrir menu">&#9776;</button>
        <span>SmartControl</span>
    </div>
    <div class="topo-usuario">
        <span>
            <?php echo limpar(nomeUsuarioLogado()); ?>
            (<?php echo $_SESSION['usuario_perfil'] === 'administrador' ? 'Administrador' : 'Funcionário'; ?>)
        </span>
        <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="link-sair">Sair</a>
    </div>
</header>
<?php if ($mensagem): ?>
    <div class="alerta alerta-<?php echo $mensagem['tipo']; ?>">
        <?php echo limpar($mensagem['texto']); ?>
    </div>
<?php endif; ?>
<div class="wrapper">

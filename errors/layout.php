<?php
/** @var int $codigo */
/** @var string $titulo */
/** @var string $mensagem */

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$baseCalculada = rtrim(dirname(dirname($scriptName)), '/.');
$baseErro = defined('BASE_URL') ? BASE_URL : ($baseCalculada !== '' ? $baseCalculada : '/smartcontrol');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo (int) $codigo; ?> - SmartControl</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseErro, ENT_QUOTES, 'UTF-8'); ?>/assets/css/style.css">
</head>
<body class="pagina-login">
    <main class="caixa-login caixa-erro" id="conteudo-principal">
        <p class="codigo-erro" aria-hidden="true"><?php echo (int) $codigo; ?></p>
        <h1><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
        <a class="botao botao-largo" href="<?php echo htmlspecialchars($baseErro, ENT_QUOTES, 'UTF-8'); ?>/index.php">
            Voltar ao SmartControl
        </a>
    </main>
</body>
</html>

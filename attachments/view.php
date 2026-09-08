<?php
/**
 * attachments/view.php
 * ---------------------------------------------------------------
 * Permite visualizar ou baixar um anexo somente para
 * usuários autenticados no SmartControl.
 *
 * A pasta storage/uploads continua bloqueada pelo .htaccess.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

/*
 * ---------------------------------------------------------------
 * 1. Recebe o ID do anexo
 * ---------------------------------------------------------------
 */
$anexoId = (int) ($_GET['id'] ?? 0);

/*
 * Se download=1 estiver na URL,
 * força o download do arquivo.
 *
 * Caso contrário, tenta abrir no navegador.
 */
$baixar = isset($_GET['download'])
    && $_GET['download'] === '1';

if ($anexoId <= 0) {

    http_response_code(404);

    exit('Anexo não encontrado.');
}

/*
 * ---------------------------------------------------------------
 * 2. Busca o anexo no banco
 * ---------------------------------------------------------------
 */
$stmt = $pdo->prepare("
    SELECT
        id,
        entidade,
        entidade_id,
        nome_original,
        nome_arquivo,
        tipo_mime,
        extensao,
        tamanho

    FROM anexos

    WHERE id = :id

    LIMIT 1
");

$stmt->execute([
    ':id' => $anexoId
]);

$anexo = $stmt->fetch();

if (!$anexo) {

    http_response_code(404);

    exit('Anexo não encontrado.');
}

/*
 * ---------------------------------------------------------------
 * 3. Neste momento aceitamos anexos de produtos
 * ---------------------------------------------------------------
 */
if ($anexo['entidade'] !== 'produto') {

    http_response_code(403);

    exit('Acesso não permitido.');
}

/*
 * ---------------------------------------------------------------
 * 4. Monta o caminho físico do arquivo
 * ---------------------------------------------------------------
 *
 * O nome usado vem do banco.
 *
 * Nunca usamos um nome enviado diretamente pela URL.
 */
$caminhoArquivo =
    __DIR__
    . '/../storage/uploads/products/'
    . $anexo['nome_arquivo'];

/*
 * Verifica se o arquivo realmente existe.
 */
if (
    !is_file($caminhoArquivo)
    ||
    !is_readable($caminhoArquivo)
) {

    http_response_code(404);

    exit('Arquivo não encontrado no servidor.');
}

/*
 * ---------------------------------------------------------------
 * 5. Tipos permitidos
 * ---------------------------------------------------------------
 */
$tiposPermitidos = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp'
];

if (
    !in_array(
        $anexo['tipo_mime'],
        $tiposPermitidos,
        true
    )
) {

    http_response_code(403);

    exit('Tipo de arquivo não permitido.');
}

/*
 * ---------------------------------------------------------------
 * 6. Nome original mostrado ao usuário
 * ---------------------------------------------------------------
 */
$nomeOriginal = basename(
    $anexo['nome_original']
);

/*
 * Remove caracteres que poderiam causar problema
 * nos cabeçalhos HTTP.
 */
$nomeOriginal = str_replace(
    [
        "\r",
        "\n",
        '"'
    ],
    '',
    $nomeOriginal
);

/*
 * ---------------------------------------------------------------
 * 7. Cabeçalhos de segurança
 * ---------------------------------------------------------------
 */
header(
    'X-Content-Type-Options: nosniff'
);

header(
    'Content-Type: '
    . $anexo['tipo_mime']
);

header(
    'Content-Length: '
    . filesize($caminhoArquivo)
);

header(
    'Cache-Control: private, no-store, max-age=0'
);

/*
 * ---------------------------------------------------------------
 * 8. Define se abre ou baixa
 * ---------------------------------------------------------------
 */
$disposicao = $baixar
    ? 'attachment'
    : 'inline';

header(
    'Content-Disposition: '
    . $disposicao
    . '; filename="'
    . $nomeOriginal
    . '"; filename*=UTF-8\'\''
    . rawurlencode($nomeOriginal)
);

/*
 * ---------------------------------------------------------------
 * 9. Envia o arquivo para o navegador
 * ---------------------------------------------------------------
 */
readfile($caminhoArquivo);

exit;

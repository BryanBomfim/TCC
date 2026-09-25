<?php
/**
 * attachments/upload.php
 * ---------------------------------------------------------------
 * Recebe anexos enviados para produtos.
 *
 * Segurança:
 * - exige login;
 * - exige CSRF;
 * - verifica produto;
 * - limita tamanho;
 * - valida o MIME real;
 * - gera nome aleatório;
 * - não usa o nome enviado pelo usuário no disco;
 * - registra o anexo no banco;
 * - registra auditoria.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

/*
 * O upload só pode acontecer via POST.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: '
        . BASE_URL
        . '/products/list.php'
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 1. Proteção CSRF
 * ---------------------------------------------------------------
 */
if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {

    definirMensagem(
        'erro',
        'Sessão expirada. Tente novamente.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/list.php'
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 2. Produto
 * ---------------------------------------------------------------
 */
$produtoId = (int) ($_POST['produto_id'] ?? 0);

if ($produtoId <= 0) {

    definirMensagem(
        'erro',
        'Produto inválido.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/list.php'
    );

    exit;
}

$stmtProduto = $pdo->prepare("
    SELECT
        id,
        nome

    FROM produtos

    WHERE id = :id
      AND ativo = 1

    LIMIT 1
");

$stmtProduto->execute([
    ':id' => $produtoId
]);

$produto = $stmtProduto->fetch();

if (!$produto) {

    definirMensagem(
        'erro',
        'Produto não encontrado ou inativo.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/list.php'
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 3. Verifica se um arquivo foi enviado
 * ---------------------------------------------------------------
 */
if (
    !isset($_FILES['anexo'])
    ||
    !is_array($_FILES['anexo'])
) {

    definirMensagem(
        'erro',
        'Nenhum arquivo foi enviado.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/view.php?id='
        . $produtoId
    );

    exit;
}

$arquivo = $_FILES['anexo'];

/*
 * ---------------------------------------------------------------
 * 4. Verifica erros do upload
 * ---------------------------------------------------------------
 */
if ($arquivo['error'] !== UPLOAD_ERR_OK) {

    $mensagemErro = 'Não foi possível enviar o arquivo.';

    if ($arquivo['error'] === UPLOAD_ERR_INI_SIZE) {

        $mensagemErro =
            'O arquivo ultrapassa o limite permitido pelo servidor.';

    } elseif ($arquivo['error'] === UPLOAD_ERR_FORM_SIZE) {

        $mensagemErro =
            'O arquivo é maior que o tamanho permitido.';

    } elseif ($arquivo['error'] === UPLOAD_ERR_NO_FILE) {

        $mensagemErro =
            'Selecione um arquivo para enviar.';
    }

    definirMensagem(
        'erro',
        $mensagemErro
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/view.php?id='
        . $produtoId
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 5. Limite de tamanho
 * ---------------------------------------------------------------
 *
 * 5 MB.
 */
$limiteBytes = 5 * 1024 * 1024;

if ((int) $arquivo['size'] > $limiteBytes) {

    definirMensagem(
        'erro',
        'O anexo deve possuir no máximo 5 MB.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/view.php?id='
        . $produtoId
    );

    exit;
}

if ((int) $arquivo['size'] <= 0) {

    definirMensagem(
        'erro',
        'O arquivo enviado está vazio.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/view.php?id='
        . $produtoId
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 6. Descobre o tipo REAL do arquivo
 * ---------------------------------------------------------------
 *
 * Não confiamos somente na extensão enviada pelo navegador.
 */
$finfo = new finfo(FILEINFO_MIME_TYPE);

$tipoMime = $finfo->file(
    $arquivo['tmp_name']
);

/*
 * Tipos permitidos inicialmente.
 */
$tiposPermitidos = [

    'application/pdf' => 'pdf',

    'image/jpeg' => 'jpg',

    'image/png' => 'png',

    'image/webp' => 'webp'
];

if (!isset($tiposPermitidos[$tipoMime])) {

    definirMensagem(
        'erro',
        'Tipo de arquivo não permitido. '
        . 'Envie PDF, JPG, PNG ou WEBP.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/view.php?id='
        . $produtoId
    );

    exit;
}

/*
 * A extensão será definida pelo MIME real,
 * e não pelo nome fornecido pelo usuário.
 */
$extensao = $tiposPermitidos[$tipoMime];

/*
 * ---------------------------------------------------------------
 * 7. Nome original
 * ---------------------------------------------------------------
 */
$nomeOriginal = basename(
    (string) $arquivo['name']
);

/*
 * Limita nomes excessivamente grandes.
 */
if (mb_strlen($nomeOriginal) > 255) {

    $nomeOriginal = mb_substr(
        $nomeOriginal,
        0,
        255
    );
}

/*
 * ---------------------------------------------------------------
 * 8. Gera nome seguro e aleatório
 * ---------------------------------------------------------------
 */
$nomeArquivo =
    bin2hex(random_bytes(20))
    . '.'
    . $extensao;

/*
 * ---------------------------------------------------------------
 * 9. Caminho de armazenamento
 * ---------------------------------------------------------------
 */
$pastaUpload =
    __DIR__
    . '/../storage/uploads/products';

if (!is_dir($pastaUpload)) {

    definirMensagem(
        'erro',
        'A pasta de anexos não está disponível.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/view.php?id='
        . $produtoId
    );

    exit;
}

$caminhoCompleto =
    $pastaUpload
    . DIRECTORY_SEPARATOR
    . $nomeArquivo;

/*
 * ---------------------------------------------------------------
 * 10. Move o arquivo
 * ---------------------------------------------------------------
 */
if (
    !move_uploaded_file(
        $arquivo['tmp_name'],
        $caminhoCompleto
    )
) {

    definirMensagem(
        'erro',
        'Não foi possível salvar o anexo.'
    );

    header(
        'Location: '
        . BASE_URL
        . '/products/view.php?id='
        . $produtoId
    );

    exit;
}

/*
 * ---------------------------------------------------------------
 * 11. Registra no banco
 * ---------------------------------------------------------------
 */
try {

    $stmtAnexo = $pdo->prepare("
        INSERT INTO anexos
        (
            entidade,
            entidade_id,
            nome_original,
            nome_arquivo,
            tipo_mime,
            extensao,
            tamanho,
            usuario_id,
            usuario_nome
        )
        VALUES
        (
            'produto',
            :entidade_id,
            :nome_original,
            :nome_arquivo,
            :tipo_mime,
            :extensao,
            :tamanho,
            :usuario_id,
            :usuario_nome
        )
    ");

    $stmtAnexo->execute([

        ':entidade_id' =>
            $produtoId,

        ':nome_original' =>
            $nomeOriginal,

        ':nome_arquivo' =>
            $nomeArquivo,

        ':tipo_mime' =>
            $tipoMime,

        ':extensao' =>
            $extensao,

        ':tamanho' =>
            (int) $arquivo['size'],

        ':usuario_id' =>
            $_SESSION['usuario_id'],

        ':usuario_nome' =>
            $_SESSION['usuario_nome'] ?? 'Usuário'
    ]);

    $anexoId =
        (int) $pdo->lastInsertId();

    /*
     * -----------------------------------------------------------
     * 12. Auditoria
     * -----------------------------------------------------------
     */
    registrarAuditoria(
        'ANEXAR',
        'Produtos',
        $produtoId,
        'Anexo "'
        . $nomeOriginal
        . '" adicionado ao produto "'
        . $produto['nome']
        . '".',
        null,
        [
            'anexo_id' => $anexoId,
            'arquivo' => $nomeOriginal,
            'tipo' => $tipoMime,
            'tamanho' => (int) $arquivo['size']
        ]
    );

    definirMensagem(
        'sucesso',
        'Anexo enviado com sucesso!'
    );

} catch (Throwable $e) {

    /*
     * Se o banco falhar, removemos o arquivo físico
     * para não deixar arquivo órfão no servidor.
     */
    if (is_file($caminhoCompleto)) {
        unlink($caminhoCompleto);
    }

    error_log(
        'Erro ao registrar anexo: '
        . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine()
    );

    definirMensagem(
        'erro',
        'Ocorreu um erro ao registrar o anexo.'
    );
}

/*
 * Volta para o produto.
 */
header(
    'Location: '
    . BASE_URL
    . '/products/view.php?id='
    . $produtoId
);

exit;

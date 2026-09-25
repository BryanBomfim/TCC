<?php
declare(strict_types=1);

use SmartControl\Services\StockService;
use SmartControl\Services\ApiAuthService;
use SmartControl\Services\ExternalLookupService;

require_once __DIR__ . '/../config/config.php';

$aprovados = 0;
$falhas = [];

function verificar(string $descricao, bool $condicao): void
{
    global $aprovados, $falhas;

    if ($condicao) {
        $aprovados++;
        echo "[OK] {$descricao}" . PHP_EOL;
        return;
    }

    $falhas[] = $descricao;
    echo "[FALHA] {$descricao}" . PHP_EOL;
}

/** @return array{status:int,body:string,headers:string} */
function requisicaoHttpTeste(string $url, array $headers = []): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Não foi possível iniciar o cURL.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $resposta = curl_exec($curl);
    if ($resposta === false) {
        $erro = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('Falha HTTP: ' . $erro);
    }

    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $tamanhoCabecalho = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    return [
        'status' => $status,
        'headers' => substr($resposta, 0, $tamanhoCabecalho),
        'body' => substr($resposta, $tamanhoCabecalho),
    ];
}

verificar('Conexão com o banco', $pdo->query('SELECT 1')->fetchColumn() === 1);
verificar('Ambiente da aplicação definido', in_array(APP_ENV, ['development', 'testing', 'production'], true));
verificar('Arquivo .env não é versionável', str_contains((string) file_get_contents(__DIR__ . '/../.gitignore'), '/.env'));
verificar('Diretório de uploads disponível', is_dir(__DIR__ . '/../storage/uploads/products'));
verificar('Diretório de uploads gravável', is_writable(__DIR__ . '/../storage/uploads/products'));
verificar('Autoload do Composer disponível', is_file(__DIR__ . '/../vendor/autoload.php'));

$tabelasEsperadas = [
    'anexos',
    'api_tokens',
    'categorias',
    'configuracoes',
    'fornecedores',
    'inventarios',
    'inventario_itens',
    'limites_requisicao',
    'logs_auditoria',
    'movimentacoes',
    'notificacoes',
    'produtos',
    'recuperacoes_senha',
    'tentativas_login',
    'usuarios',
    'verificacoes_email',
];

$stmtTabelas = $pdo->prepare("
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = :banco
");
$stmtTabelas->execute([':banco' => DB_NAME]);
$tabelasAtuais = $stmtTabelas->fetchAll(PDO::FETCH_COLUMN);

verificar(
    'Todas as tabelas obrigatórias existem',
    count(array_diff($tabelasEsperadas, $tabelasAtuais)) === 0
);

$colunasInventario = $pdo->query('SHOW COLUMNS FROM inventario_itens')->fetchAll(PDO::FETCH_COLUMN);
verificar('Inventário possui referência da última movimentação', in_array('ultimo_movimento_id', $colunasInventario, true));
$colunasCabecalho = $pdo->query('SHOW COLUMNS FROM inventarios')->fetchAll(PDO::FETCH_COLUMN);
verificar('Inventário possui revisão concorrente', in_array('revisao', $colunasCabecalho, true));

$colunasMovimentacao = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'movimentacoes'
")->fetchAll(PDO::FETCH_COLUMN);

foreach (['saldo_anterior', 'saldo_posterior', 'origem', 'motivo', 'fornecedor_id', 'inventario_id'] as $coluna) {
    verificar('Movimentações possuem ' . $coluna, in_array($coluna, $colunasMovimentacao, true));
}

$indiceInventario = (int) $pdo->query("
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'inventarios'
      AND index_name = 'uk_inventarios_um_aberto'
      AND non_unique = 0
")->fetchColumn();

verificar('Banco impede dois inventários abertos', $indiceInventario === 1);

$inventariosAbertos = (int) $pdo->query("
    SELECT COUNT(*) FROM inventarios WHERE status = 'aberto'
")->fetchColumn();
verificar('Há no máximo um inventário aberto', $inventariosAbertos <= 1);

$estoquesNegativos = (int) $pdo->query("
    SELECT COUNT(*) FROM produtos WHERE quantidade_estoque < 0
")->fetchColumn();
verificar('Não existem estoques negativos', $estoquesNegativos === 0);

$saldosDivergentes = (int) $pdo->query("
    SELECT COUNT(*)
    FROM produtos p
    WHERE p.quantidade_estoque <> COALESCE((
        SELECT m.saldo_posterior
        FROM movimentacoes m
        WHERE m.produto_id = p.id
        ORDER BY m.data_movimentacao DESC, m.id DESC
        LIMIT 1
    ), 0)
")->fetchColumn();
verificar('Saldo atual confere com o último movimento', $saldosDivergentes === 0);

$quebrasHistorico = (int) $pdo->query("
    SELECT COUNT(*)
    FROM (
        SELECT
            produto_id,
            saldo_anterior,
            LAG(saldo_posterior) OVER (
                PARTITION BY produto_id
                ORDER BY data_movimentacao, id
            ) AS saldo_anterior_esperado
        FROM movimentacoes
    ) historico
    WHERE saldo_anterior_esperado IS NOT NULL
      AND saldo_anterior <> saldo_anterior_esperado
")->fetchColumn();
verificar('Histórico de saldos é contínuo', $quebrasHistorico === 0);

$token = gerarTokenCSRF();
verificar('Token CSRF válido é aceito', validarTokenCSRF($token));
verificar('Token CSRF inválido é recusado', !validarTokenCSRF('token-invalido'));
verificar('Escape HTML neutraliza script', limpar('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;');

$produtoTeste = ['id' => 0, 'quantidade_estoque' => 0];
$usuarioTeste = $pdo->query("
    SELECT id
    FROM usuarios
    WHERE ativo = 1
    ORDER BY id
    LIMIT 1
")->fetchColumn();

verificar('Existe usuário ativo para testes transacionais', (int) $usuarioTeste > 0);

if ($produtoTeste && (int) $usuarioTeste > 0) {
    try {
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO produtos (nome, quantidade_estoque, ativo) VALUES ('Produto temporário da suíte', 0, 1)");
        $produtoTeste['id'] = (int) $pdo->lastInsertId();
        $servicoEstoque = new StockService($pdo);
        $saldoOriginal = (int) $produtoTeste['quantidade_estoque'];

        $entradaTeste = $servicoEstoque->registrarEntrada(
            (int) $usuarioTeste,
            (int) $produtoTeste['id'],
            2,
            null,
            null,
            'Teste automatizado transacional'
        );
        verificar(
            'Serviço de estoque registra entrada com saldo correto',
            $entradaTeste['saldo_anterior'] === $saldoOriginal
            && $entradaTeste['saldo_posterior'] === $saldoOriginal + 2
        );

        $saidaTeste = $servicoEstoque->registrarSaida(
            (int) $usuarioTeste,
            (int) $produtoTeste['id'],
            2,
            'Teste automatizado',
            'Operação revertida ao fim do teste'
        );
        verificar(
            'Serviço de estoque registra saída sem saldo negativo',
            $saidaTeste['saldo_anterior'] === $saldoOriginal + 2
            && $saidaTeste['saldo_posterior'] === $saldoOriginal
        );

        $ajusteTeste = $servicoEstoque->ajustarSaldo(
            (int) $usuarioTeste,
            (int) $produtoTeste['id'],
            $saldoOriginal + 1,
            'Validação automatizada do ajuste'
        );
        verificar(
            'Ajuste administrativo registra diferença e novo saldo',
            $ajusteTeste['saldo_anterior'] === $saldoOriginal
            && $ajusteTeste['saldo_posterior'] === $saldoOriginal + 1
            && $ajusteTeste['tipo'] === 'entrada'
        );

        $recusada = false;
        $movimentosAntes = (int) $pdo->query('SELECT COUNT(*) FROM movimentacoes')->fetchColumn();
        try {
            $servicoEstoque->registrarSaida((int) $usuarioTeste, (int) $produtoTeste['id'], 999, 'Teste excesso', null);
        } catch (DomainException) { $recusada = true; }
        verificar('Saída excessiva rejeitada sem movimento parcial', $recusada && (int) $pdo->query('SELECT COUNT(*) FROM movimentacoes')->fetchColumn() === $movimentosAntes);
        $pdo->rollBack();
        verificar('Produto de teste removido pelo rollback', (int) $pdo->query('SELECT COUNT(*) FROM produtos WHERE id = ' . (int) $produtoTeste['id'])->fetchColumn() === 0);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Falha no teste transacional de estoque: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
        verificar('Serviços transacionais de estoque executam sem erro', false);
    }
}

if ((int) $usuarioTeste > 0) {
    try {
        $pdo->beginTransaction();
        $stmtUsuarioFixture = $pdo->prepare("INSERT INTO usuarios (nome,email,senha,perfil,ativo,email_verificado_em) VALUES ('Teste isolado',:email,:senha,'administrador',1,NOW())");
        $stmtUsuarioFixture->execute([':email'=>'teste-' . bin2hex(random_bytes(8)) . '@example.invalid', ':senha'=>password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
        $usuarioApiTeste = (int) $pdo->lastInsertId();
        $tokenTeste = 'sc_' . bin2hex(random_bytes(32));
        $stmtTokenTeste = $pdo->prepare("
            INSERT INTO api_tokens
                (usuario_id, nome, token_hash, escopos, expira_em)
            VALUES
                (:usuario_id, 'Teste automatizado', :token_hash, 'leitura', DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ");
        $stmtTokenTeste->execute([
            ':usuario_id' => (int) $usuarioApiTeste,
            ':token_hash' => hash('sha256', $tokenTeste),
        ]);
        $identidadeApi = (new ApiAuthService($pdo))->autenticar('Bearer ' . $tokenTeste);
        verificar(
            'Autenticação da API aceita token válido armazenado como hash',
            (int) $identidadeApi['usuario_id'] === (int) $usuarioApiTeste
            && $identidadeApi['escopos'] === 'leitura'
        );

        $tokenInvalidoRecusado = false;
        try {
            (new ApiAuthService($pdo))->autenticar('Bearer sc_' . str_repeat('0', 64));
        } catch (DomainException) {
            $tokenInvalidoRecusado = true;
        }
        verificar('Autenticação da API recusa token inválido', $tokenInvalidoRecusado);
        $stmtRevogarTeste = $pdo->prepare('UPDATE api_tokens SET revogado_em = NOW() WHERE token_hash = :hash');
        $stmtRevogarTeste->execute([':hash' => hash('sha256', $tokenTeste)]);
        $revogadoRecusado = false;
        try { (new ApiAuthService($pdo))->autenticar('Bearer ' . $tokenTeste); }
        catch (DomainException) { $revogadoRecusado = true; }
        verificar('API recusa token revogado', $revogadoRecusado);
        $stmtExpirarTeste = $pdo->prepare('UPDATE api_tokens SET revogado_em = NULL, expira_em = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE token_hash = :hash');
        $stmtExpirarTeste->execute([':hash' => hash('sha256', $tokenTeste)]);
        $expiradoRecusado = false;
        try { (new ApiAuthService($pdo))->autenticar('Bearer ' . $tokenTeste); }
        catch (DomainException) { $expiradoRecusado = true; }
        verificar('API recusa token expirado', $expiradoRecusado);
        $pdo->rollBack();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Falha no teste de autenticação da API: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
        verificar('Serviço de autenticação da API executa sem erro', false);
    }
}

if (getenv('RUN_EXTERNAL_LOOKUP') === '1') {
    try {
        $cepExterno = (new ExternalLookupService(8))->consultarCep('01001000');
        verificar(
            'Integração externa consulta CEP real com TLS validado',
            ($cepExterno['cep'] ?? '') === '01001000'
            && ($cepExterno['cidade'] ?? '') === 'São Paulo'
        );
    } catch (Throwable $e) {
        error_log('Falha no teste externo de CEP: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
        verificar('Integração externa consulta CEP real com TLS validado', false);
    }
}

if (getenv('RUN_HTTP_SMOKE') === '1') {
    $baseUrl = rtrim((string) (getenv('HTTP_SMOKE_BASE_URL') ?: 'http://localhost/smartcontrol'), '/');
    $tokenIdHttp = null;
    $limiteHashHttp = null;

    try {
        $login = requisicaoHttpTeste($baseUrl . '/auth/login.php');
        verificar('Tela de login responde HTTP 200', $login['status'] === 200);
        verificar(
            'Cabeçalhos HTTP de segurança estão presentes',
            stripos($login['headers'], 'X-Content-Type-Options: nosniff') !== false
            && stripos($login['headers'], 'X-Frame-Options: DENY') !== false
            && stripos($login['headers'], 'Content-Security-Policy:') !== false
        );

        $dashboard = requisicaoHttpTeste($baseUrl . '/dashboard/index.php');
        verificar(
            'Rota autenticada redireciona visitante para o login',
            $dashboard['status'] === 302
            && stripos($dashboard['headers'], 'Location: ' . BASE_URL . '/auth/login.php') !== false
        );

        $interno = requisicaoHttpTeste($baseUrl . '/config/config.php');
        verificar('Diretório interno é bloqueado pelo Apache', $interno['status'] === 403);

        $inexistente = requisicaoHttpTeste($baseUrl . '/rota-inexistente-' . bin2hex(random_bytes(4)));
        verificar('Página inexistente responde HTTP 404', $inexistente['status'] === 404);

        $apiSemToken = requisicaoHttpTeste($baseUrl . '/api/v1/products');
        $jsonSemToken = json_decode($apiSemToken['body'], true);
        verificar(
            'API recusa chamada sem Bearer token',
            $apiSemToken['status'] === 401 && ($jsonSemToken['success'] ?? true) === false
        );

        $tokenHttp = 'sc_' . bin2hex(random_bytes(32));
        $tokenHashHttp = hash('sha256', $tokenHttp);
        $limiteHashHttp = hash('sha256', $tokenHashHttp);
        $stmtTokenHttp = $pdo->prepare("
            INSERT INTO api_tokens (usuario_id, nome, token_hash, escopos, expira_em)
            VALUES (:usuario_id, 'Smoke test HTTP', :token_hash, 'leitura', DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ");
        $stmtTokenHttp->execute([
            ':usuario_id' => (int) $usuarioTeste,
            ':token_hash' => $tokenHashHttp,
        ]);
        $tokenIdHttp = (int) $pdo->lastInsertId();

        $api = requisicaoHttpTeste($baseUrl . '/api/v1/products?per_page=1', [
            'Authorization: Bearer ' . $tokenHttp,
            'Accept: application/json',
        ]);
        $jsonApi = json_decode($api['body'], true);
        verificar(
            'API autenticada retorna envelope JSON e paginação',
            $api['status'] === 200
            && ($jsonApi['success'] ?? false) === true
            && is_array($jsonApi['data'] ?? null)
            && isset($jsonApi['meta']['total'])
        );

        foreach (['categories', 'suppliers', 'inventory'] as $colecao) {
            $respostaColecao = requisicaoHttpTeste($baseUrl . '/api/v1/' . $colecao . '?per_page=1', ['Authorization: Bearer ' . $tokenHttp]);
            $jsonColecao = json_decode($respostaColecao['body'], true);
            verificar('API pagina ' . $colecao, $respostaColecao['status'] === 200 && count($jsonColecao['data'] ?? []) <= 1 && isset($jsonColecao['meta']['total']));
        }
        $rotaInvalida = requisicaoHttpTeste($baseUrl . '/api/v1/products/abc', ['Authorization: Bearer ' . $tokenHttp]);
        verificar('API rejeita identificador inválido', $rotaInvalida['status'] === 404);
        $parametroInvalido = requisicaoHttpTeste($baseUrl . '/api/v1/products?page%5B%5D=2', ['Authorization: Bearer ' . $tokenHttp]);
        verificar('API rejeita parâmetro de paginação em array', $parametroInvalido['status'] === 400);

        $relatorios = requisicaoHttpTeste($baseUrl . '/api/v1/reports', [
            'Authorization: Bearer ' . $tokenHttp,
            'Accept: application/json',
        ]);
        $jsonRelatorios = json_decode($relatorios['body'], true);
        verificar(
            'Endpoint de indicadores responde com autenticação',
            $relatorios['status'] === 200
            && ($jsonRelatorios['success'] ?? false) === true
            && isset($jsonRelatorios['data']['produtos_ativos'])
        );
    } catch (Throwable $e) {
        error_log('Falha no smoke test HTTP: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
        verificar('Smoke test HTTP executa sem erro', false);
    } finally {
        if ($tokenIdHttp !== null) {
            $stmtExcluirToken = $pdo->prepare('DELETE FROM api_tokens WHERE id = :id AND nome = :nome');
            $stmtExcluirToken->execute([':id' => $tokenIdHttp, ':nome' => 'Smoke test HTTP']);
        }
        if ($limiteHashHttp !== null) {
            $stmtExcluirLimite = $pdo->prepare("
                DELETE FROM limites_requisicao
                WHERE acao = 'api_v1' AND identificador_hash = :hash
            ");
            $stmtExcluirLimite->execute([':hash' => $limiteHashHttp]);
        }
    }
}

echo PHP_EOL . "Aprovados: {$aprovados}" . PHP_EOL;
echo 'Falhas: ' . count($falhas) . PHP_EOL;

exit($falhas === [] ? 0 : 1);

<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../api.php';

// Execute SOMENTE contra uma instância de teste. O script cria e remove apenas um banco aleatório próprio.
// Ex.: MOBILE_RUN_DB_TESTS=1, MOBILE_TEST_DB_HOST=127.0.0.1, MOBILE_TEST_DB_PORT=13379.
if (getenv('MOBILE_RUN_DB_TESTS') !== '1') {
    fwrite(STDERR, "Teste opcional não executado. Defina MOBILE_RUN_DB_TESTS=1 e uma instância MySQL exclusiva de teste.\n");
    exit(2);
}
$host = getenv('MOBILE_TEST_DB_HOST') ?: '127.0.0.1';
if (!in_array($host, ['127.0.0.1', 'localhost'], true)) { throw new RuntimeException('Use instância de teste local.'); }
$port = (int) (getenv('MOBILE_TEST_DB_PORT') ?: 13379);
$db = new PDO('mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4',
    getenv('MOBILE_TEST_DB_USER') ?: 'root', getenv('MOBILE_TEST_DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false]);
$database = 'smartcontrol_mobile_test_' . bin2hex(random_bytes(6));
$created = false;
$checks = 0;
function expect(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException('Falhou: ' . $message); }
}
function fails(callable $work, int $status, string $message): void
{
    try { $work(); } catch (ApiError $error) { expect($error->status === $status, $message); return; }
    throw new RuntimeException('Deveria rejeitar: ' . $message);
}
function applySql(PDO $db, string $sql): void
{
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') { $db->exec($statement); }
    }
}
try {
    $db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db->exec("USE `$database`");
    $schema = file_get_contents(__DIR__ . '/../../../database/smartcontrol.sql');
    $schema = preg_replace('/^CREATE DATABASE.*;\R|^USE smartcontrol;\R/m', '', $schema);
    applySql($db, $schema);
    $migration = file_get_contents(__DIR__ . '/../../../database/mobile_api.sql');
    applySql($db, $migration);
    applySql($db, $migration);
    expect((int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 1, 'Migration idempotente preserva dados');
    $password = bin2hex(random_bytes(16));
    $db->prepare('UPDATE usuarios SET senha = ? WHERE id = 1')->execute([password_hash($password, PASSWORD_DEFAULT)]);
    $api = new MobileApi($db, ['token_hours' => 24]);
    fails(fn() => $api->login(['email' => 'admin@smartcontrol.com', 'password' => 'incorreta'], '127.0.0.1'), 401, 'Senha incorreta');
    $login = $api->login(['email' => 'admin@smartcontrol.com', 'password' => $password], '127.0.0.1');
    expect(strlen($login['token']) === 64 && !isset($login['user']['senha']), 'Login não expõe hash de senha');
    $stored = $db->query('SELECT token_hash FROM mobile_api_tokens LIMIT 1')->fetchColumn();
    expect($stored !== $login['token'] && $stored === hash('sha256', $login['token']), 'Armazena somente hash do token');
    $admin = $api->authenticate('Bearer ' . $login['token']);
    $bootstrap = $api->bootstrap($admin);
    expect(count($bootstrap['users']) === 1 && count($bootstrap['categories']) === 3, 'Bootstrap completo');
    $category = $api->mutate('categories', ['nome' => 'Teste', 'descricao' => 'Validação'], $admin);
    expect(count($category['categories']) === 4, 'Cria categoria');
    $supplier = $api->mutate('suppliers', ['nome' => 'Fornecedor teste', 'email' => 'fornecedor@example.com'], $admin);
    expect(count($supplier['suppliers']) === 3, 'Cria fornecedor');
    $productBody = ['nome' => 'Produto teste', 'categoria_id' => 1, 'fornecedor_id' => 1,
        'preco_custo' => 5.50, 'preco_venda' => 9.99, 'quantidade_estoque' => 10, 'estoque_minimo' => 3, 'unidade' => 'UN'];
    $result = $api->mutate('products', $productBody, $admin);
    $product = $result['products'][0];
    $id = (int) $product['id'];
    expect((int) $product['quantidade_estoque'] === 10 && count($result['movements']) === 1, 'Saldo inicial tem histórico');
    expect(isset($product['categoria'], $product['fornecedor']), 'Produtos trazem nomes associados');
    $out = ['produto_id' => $id, 'tipo' => 'saida', 'quantidade' => 4];
    $result = $api->mutate('movements', $out, $admin);
    expect((int) $result['products'][0]['quantidade_estoque'] === 6 && count($result['movements']) === 2, 'Saída atualiza saldo e histórico');
    fails(fn() => $api->mutate('movements', array_replace($out, ['quantidade' => 7]), $admin), 409, 'Saída acima do saldo rejeitada');
    $result = $api->bootstrap($admin);
    expect((int) $result['products'][0]['quantidade_estoque'] === 6 && count($result['movements']) === 2, 'Rollback preserva saldo e histórico');
    fails(fn() => $api->mutate('products', array_replace($productBody, ['id' => $id, 'quantidade_estoque' => 99]), $admin), 422, 'Edição não altera saldo');
    $api->mutate('products/toggle', ['id' => $id], $admin);
    fails(fn() => $api->mutate('movements', $out, $admin), 409, 'Produto inativo bloqueado');
    $api->mutate('products/toggle', ['id' => $id], $admin);
    $employeePassword = bin2hex(random_bytes(16));
    $result = $api->mutate('users', ['nome' => 'Funcionário teste', 'email' => 'employee@example.com', 'senha' => $employeePassword, 'perfil' => 'funcionario'], $admin);
    expect(count($result['users']) === 2 && !isset($result['users'][0]['senha']), 'Admin cria usuário sem expor senha');
    $employeeApi = new MobileApi($db, ['token_hours' => 24]);
    $employeeLogin = $employeeApi->login(['email' => 'employee@example.com', 'password' => $employeePassword], '127.0.0.2');
    $employee = $employeeApi->authenticate('Bearer ' . $employeeLogin['token']);
    expect($employeeApi->bootstrap($employee)['users'] === [], 'Funcionário não lê usuários');
    fails(fn() => $employeeApi->mutate('users', [], $employee), 403, 'Funcionário não cria usuários');
    fails(fn() => $employeeApi->mutate('settings', [], $employee), 403, 'Funcionário não edita configurações');
    $result = $employeeApi->mutate('movements', ['produto_id' => $id, 'tipo' => 'entrada', 'quantidade' => 2], $employee);
    expect((int) $result['products'][0]['quantidade_estoque'] === 8, 'Funcionário movimenta estoque');
    fails(fn() => $api->mutate('users/toggle', ['id' => 1], $admin), 409, 'Admin não desativa a própria conta');
    $api->mutate('users/toggle', ['id' => (int) $employee['id']], $admin);
    fails(fn() => $employeeApi->authenticate('Bearer ' . $employeeLogin['token']), 401, 'Desativação revoga token');
    $api->mutate('users/toggle', ['id' => (int) $employee['id']], $admin);
    fails(fn() => $employeeApi->authenticate('Bearer ' . $employeeLogin['token']), 401, 'Reativação não recupera token revogado');
    $result = $api->mutate('settings', ['nome_sistema' => 'Teste', 'nome_empresa' => 'TCC', 'estoque_minimo_padrao' => 7], $admin);
    expect($result['settings']['nome_sistema'] === 'Teste', 'Configurações persistidas');
    $db->exec('ALTER TABLE produtos ADD sku VARCHAR(30) NULL, ADD codigo_barras VARCHAR(50) NULL');
    $db->exec('ALTER TABLE usuarios ADD email_verificado_em DATETIME NULL');
    $db->exec('UPDATE usuarios SET email_verificado_em = NOW() WHERE id = 1');
    $extendedApi = new MobileApi($db, ['token_hours' => 24]);
    $extendedAdmin = $extendedApi->authenticate('Bearer ' . $login['token']);
    $result = $extendedApi->mutate('products', array_replace($productBody, ['nome' => 'Produto extra', 'codigo_barras' => '7891234567890']), $extendedAdmin);
    $extra = array_values(array_filter($result['products'], fn($row) => $row['nome'] === 'Produto extra'))[0];
    expect(str_starts_with($extra['sku'], 'SC-') && $extra['codigo_barras'] === '7891234567890', 'Suporta colunas opcionais do web atual');
    $extendedApi->mutate('products', array_replace($productBody, ['id' => (int) $extra['id'], 'nome' => 'Produto extra editado']), $extendedAdmin);
    $barcode = $db->query('SELECT codigo_barras FROM produtos WHERE id = ' . (int) $extra['id'])->fetchColumn();
    expect($barcode === '7891234567890', 'Edição mobile sem campo mantém código de barras do site');
    fails(fn() => $extendedApi->login(['email' => 'employee@example.com', 'password' => $employeePassword], '127.0.0.2'), 403, 'Não contorna confirmação de email');
    fails(fn() => $extendedApi->mutate('products', array_replace($productBody, ['codigo_barras' => '7891234567890']), $extendedAdmin), 409, 'Código de barras duplicado');
    $extendedApi->mutate('products', array_replace($productBody, ['id' => (int) $extra['id'], 'codigo_barras' => '']), $extendedAdmin);
    $barcode = $db->query('SELECT codigo_barras FROM produtos WHERE id = ' . (int) $extra['id'])->fetchColumn();
    expect($barcode === null, 'Código de barras só é apagado se enviado vazio explicitamente');
    $extendedApi->logout();
    fails(fn() => $extendedApi->authenticate('Bearer ' . $login['token']), 401, 'Logout revoga token');
    fwrite(STDOUT, "$checks verificações de integração passaram em banco temporário isolado.\n");
} finally {
    if ($created && preg_match('/^smartcontrol_mobile_test_[a-f0-9]{12}$/D', $database)) {
        $db->exec("DROP DATABASE `$database`");
    }
}

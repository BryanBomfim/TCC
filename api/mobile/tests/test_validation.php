<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../validation.php';

$checks = 0;
function check($actual, $expected, string $message): void
{
    global $checks;
    $checks++;
    if ($actual !== $expected) { throw new RuntimeException('Falhou: ' . $message); }
}
function rejects(callable $work, int $status, string $message): void
{
    try { $work(); } catch (ApiError $error) { check($error->status, $status, $message); return; }
    throw new RuntimeException('Deveria rejeitar: ' . $message);
}

check(Input::stock(5, 'saida', 5), 0, 'Permite esgotar o saldo');
check(Input::stock(5, 'entrada', 2), 7, 'Soma entrada');
rejects(fn() => Input::stock(5, 'saida', 6), 409, 'Bloqueia estoque negativo');
rejects(fn() => Input::stock(2147483647, 'entrada', 1), 409, 'Bloqueia overflow do INT MySQL');
rejects(fn() => Input::stock(1, 'entrada', 0), 422, 'Rejeita movimento sem quantidade');
rejects(fn() => Input::stock(1, 'desconhecido', 1), 422, 'Rejeita tipo inexistente');
check(Input::integer(['q' => '15'], 'q', 1), 15, 'Aceita string inteira');
foreach ([1.5, -1, '2.0', '2e1', true, [], '2147483648'] as $invalid) {
    rejects(fn() => Input::integer(['q' => $invalid], 'q'), 422, 'Quantidade malformada');
}
check(Input::id(['categoria_id' => null], 'categoria_id', true), null, 'Relação opcional nula');
rejects(fn() => Input::id(['id' => 0]), 422, 'Id positivo obrigatório');
check(Input::money(['preco' => '12.50'], 'preco'), '12.50', 'Decimal seguro');
rejects(fn() => Input::money(['preco' => '12,50'], 'preco'), 422, 'JSON monetário usa ponto');
rejects(fn() => Input::money(['preco' => -1], 'preco'), 422, 'Rejeita preço negativo');
rejects(fn() => Input::money(['preco' => 100000000], 'preco'), 422, 'Limite DECIMAL(10,2)');
rejects(fn() => Input::text(['nome' => '  '], 'nome', 100, true), 422, 'Nome obrigatório');
rejects(fn() => Input::text(['nome' => ['x']], 'nome', 100), 422, 'Rejeita array como texto');
check(Input::email(['email' => ' PESSOA@exemplo.com ']), 'pessoa@exemplo.com', 'Normaliza email');
rejects(fn() => Input::email(['email' => 'email']), 422, 'Rejeita email inválido');
rejects(fn() => Input::password(['senha' => '1234567'], 'senha'), 422, 'Comprimento mínimo');
rejects(fn() => Input::password(['senha' => str_repeat('x', 73)], 'senha'), 422, 'Evita truncamento bcrypt');
check(Input::password([], 'senha', false), '', 'Edição permite manter senha');
rejects(fn() => Input::choice(['perfil' => 'superuser'], 'perfil', ['administrador', 'funcionario']), 422, 'Whitelist de perfil');
Input::admin(['perfil' => 'administrador', 'ativo' => 1]);
rejects(fn() => Input::admin(['perfil' => 'funcionario', 'ativo' => 1]), 403, 'Restringe administrador');
rejects(fn() => Input::admin(['perfil' => 'administrador', 'ativo' => 0]), 403, 'Bloqueia admin inativo');
fwrite(STDOUT, "$checks verificações de validação passaram (nenhum banco utilizado).\n");

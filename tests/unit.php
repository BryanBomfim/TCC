<?php
declare(strict_types=1);
// Suíte sem conexão ao banco. Execute com PHP 8.2+ e mbstring.
require_once __DIR__ . '/../config/environment.php';
require_once __DIR__ . '/../includes/functions.php';
$aprovados = 0;
$falhas = 0;
function caso(string $nome, bool $resultado): void {
    global $aprovados, $falhas;
    $resultado ? $aprovados++ : $falhas++;
    echo ($resultado ? '[OK] ' : '[FALHA] ') . $nome . PHP_EOL;
}
foreach ([['0',0], ['17',17], ['2147483647',2147483647], ['01',1], ['1.5',null], ['-1',null], ['1e3',null], ['12abc',null], ['2147483648',null], ['',null], [[],null]] as [$valor, $esperado]) {
    caso('Quantidade: ' . json_encode($valor), quantidadeInteira($valor) === $esperado);
}
caso('Entrada não aceita zero', quantidadeInteira('0', 1) === null);
caso('Senha de demonstração não pode ser reutilizada', validarNovaSenha('SmartControl@2026!') !== null);
caso('Senha curta recusada', validarNovaSenha('12345') !== null);
caso('Senha longa recusada', validarNovaSenha(str_repeat('a', 73)) !== null);
caso('Senha válida aceita', validarNovaSenha('MinhaSenhaDeTeste-47') === null);
$_SESSION = [];
$token = gerarTokenCSRF();
caso('Token CSRF aleatório de 64 caracteres', strlen($token) === 64);
caso('CSRF correto aceito', validarTokenCSRF($token));
caso('CSRF incorreto rejeitado', !validarTokenCSRF(str_repeat('0', 64)));
caso('CSRF ausente rejeitado', !validarTokenCSRF(null));
caso('XSS neutralizado', limpar('<script>') === '&lt;script&gt;');
$usuario = ['senha'=>'hash-1', 'email'=>'teste@example.invalid', 'perfil'=>'administrador', 'auth_version'=>0];
foreach (['senha'=>'hash-2', 'email'=>'outro@example.invalid', 'perfil'=>'funcionario', 'auth_version'=>1] as $campo=>$novo) {
    caso('Sessão invalida após alteração de ' . $campo, assinaturaAutenticacao($usuario) !== assinaturaAutenticacao(array_merge($usuario, [$campo=>$novo])));
}
$_GET = ['pagina'=>'999'];
$p = calcularPaginacao(51,25);
caso('Paginação limita página à última existente', $p['pagina'] === 3 && $p['offset'] === 50);
$_GET = ['pagina'=>'-1'];
caso('Paginação não aceita offset negativo', calcularPaginacao(0)['offset'] === 0);
echo "Aprovados: {$aprovados}; falhas: {$falhas}" . PHP_EOL;
exit($falhas === 0 ? 0 : 1);

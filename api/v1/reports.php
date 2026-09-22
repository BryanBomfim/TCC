<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiErro('Método não permitido.', 405);
}

$indicadores = [
    'produtos_ativos' => (int) $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1")->fetchColumn(),
    'unidades_em_estoque' => (int) $pdo->query("SELECT COALESCE(SUM(quantidade_estoque), 0) FROM produtos WHERE ativo = 1")->fetchColumn(),
    'estoque_baixo' => (int) $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1 AND quantidade_estoque > 0 AND quantidade_estoque <= estoque_minimo")->fetchColumn(),
    'sem_estoque' => (int) $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1 AND quantidade_estoque = 0")->fetchColumn(),
    'inventarios_abertos' => (int) $pdo->query("SELECT COUNT(*) FROM inventarios WHERE status = 'aberto'")->fetchColumn(),
    'movimentacoes_hoje' => (int) $pdo->query("SELECT COUNT(*) FROM movimentacoes WHERE DATE(data_movimentacao) = CURDATE()")->fetchColumn(),
];

apiResposta($indicadores, 'Indicadores gerados.');

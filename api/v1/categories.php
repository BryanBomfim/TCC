<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiErro('Método não permitido.', 405);
}

$stmt = $pdo->query("
    SELECT id, nome, descricao, ativo, data_cadastro
    FROM categorias
    ORDER BY nome ASC
");

apiResposta($stmt->fetchAll(), 'Categorias encontradas.');

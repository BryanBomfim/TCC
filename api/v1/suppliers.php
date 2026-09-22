<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiErro('Método não permitido.', 405);
}

[$pagina, $limite, $offset] = apiPaginacao();
$busca = trim((string) ($_GET['search'] ?? ''));
$where = ['1 = 1'];
$params = [];

if ($busca !== '') {
    $where[] = '(nome LIKE :busca OR cnpj_cpf LIKE :busca OR email LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}
$whereSql = implode(' AND ', $where);

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM fornecedores WHERE {$whereSql}");
$stmtTotal->execute($params);
$total = (int) $stmtTotal->fetchColumn();

$stmt = $pdo->prepare("
    SELECT id, nome, cnpj_cpf, telefone, email, cep, logradouro, numero,
           complemento, bairro, cidade, uf, endereco, ativo, data_cadastro
    FROM fornecedores
    WHERE {$whereSql}
    ORDER BY nome ASC
    LIMIT {$limite} OFFSET {$offset}
");
$stmt->execute($params);

apiResposta($stmt->fetchAll(), 'Fornecedores encontrados.', 200, [
    'page' => $pagina,
    'limit' => $limite,
    'total' => $total,
    'pages' => max(1, (int) ceil($total / $limite)),
]);

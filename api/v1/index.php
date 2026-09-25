<?php
declare(strict_types=1);

use SmartControl\Http\ApiResponse;
use SmartControl\Services\ApiAuthService;

define('SMARTCONTROL_API', true);
require_once __DIR__ . '/../../config/config.php';
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    ApiResponse::error('Método não permitido. A API v1 é somente leitura.', 405);
}

try {
    $cabecalho = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? null;
    $identidade = (new ApiAuthService($pdo))->autenticar($cabecalho);
} catch (DomainException) {
    ApiResponse::error('Não autorizado.', 401);
} catch (Throwable $e) {
    error_log('Falha de autenticação da API: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
    ApiResponse::error('Não foi possível autenticar a solicitação.', 500);
}

if (!permitirRequisicaoLimitada('api_v1', (string) $identidade['token_hash_requisicao'], 120, 60)) {
    header('Retry-After: 60');
    ApiResponse::error('Limite de requisições excedido. Tente novamente em instantes.', 429);
}

foreach ($_GET as $valor) {
    if (!is_string($valor)) {
        ApiResponse::error('Parâmetros inválidos.', 400);
    }
}
foreach (['page', 'per_page', 'product_id', 'category_id', 'supplier_id'] as $campo) {
    if (isset($_GET[$campo]) && quantidadeInteira($_GET[$campo], 1) === null) {
        ApiResponse::error('Informe parâmetros numéricos inteiros positivos.', 400);
    }
}
$recurso = trim((string) ($_GET['resource'] ?? ''), '/');
$partes = $recurso === '' ? [] : explode('/', $recurso);
$entidade = $partes[0] ?? '';
$id = isset($partes[1]) && ctype_digit($partes[1]) ? (int) $partes[1] : 0;
if (count($partes) > 2 || (isset($partes[1]) && (quantidadeInteira($partes[1], 1) === null || !in_array($entidade, ['products', 'inventory'], true)))) {
    ApiResponse::error('Endpoint não encontrado.', 404);
}
$pagina = min(1000000, max(1, (int) ($_GET['page'] ?? 1)));
$porPagina = min(100, max(1, (int) ($_GET['per_page'] ?? 25)));
$offset = ($pagina - 1) * $porPagina;

/** @return array{0:int,1:int,2:int} */
function metaPaginacao(int $total, int $pagina, int $porPagina): array
{
    return [$total, $pagina, max(1, (int) ceil($total / $porPagina))];
}

try {
    if ($entidade === 'products') {
        if ($id > 0) {
            $stmt = $pdo->prepare("
                SELECT p.id, p.sku, p.codigo_barras, p.nome, p.descricao,
                       p.quantidade_estoque, p.estoque_minimo, p.unidade,
                       p.preco_custo, p.preco_venda, p.ativo, p.data_cadastro,
                       c.id AS categoria_id, c.nome AS categoria,
                       f.id AS fornecedor_id, f.nome AS fornecedor
                FROM produtos p
                LEFT JOIN categorias c ON c.id = p.categoria_id
                LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
                WHERE p.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $produto = $stmt->fetch();
            if (!$produto) {
                ApiResponse::error('Produto não encontrado.', 404);
            }
            ApiResponse::send(true, $produto, 'Produto encontrado.');
        }

        $where = ['1 = 1'];
        $params = [];
        $busca = trim((string) ($_GET['q'] ?? ''));
        if ($busca !== '') {
            $where[] = '(p.nome LIKE :busca OR p.sku = :sku OR p.codigo_barras = :codigo_barras)';
            $params[':busca'] = '%' . mb_substr($busca, 0, 100) . '%';
            $params[':sku'] = mb_substr($busca, 0, 100);
            $params[':codigo_barras'] = mb_substr($busca, 0, 100);
        }
        foreach (['category_id' => 'p.categoria_id', 'supplier_id' => 'p.fornecedor_id'] as $campo => $coluna) {
            $valor = (int) ($_GET[$campo] ?? 0);
            if ($valor > 0) {
                $where[] = $coluna . ' = :' . $campo;
                $params[':' . $campo] = $valor;
            }
        }
        $status = $_GET['stock_status'] ?? '';
        if ($status === 'low') {
            $where[] = 'p.quantidade_estoque <= p.estoque_minimo';
        } elseif ($status === 'out') {
            $where[] = 'p.quantidade_estoque = 0';
        }
        $clausula = implode(' AND ', $where);
        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM produtos p WHERE {$clausula}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT p.id, p.sku, p.codigo_barras, p.nome, p.quantidade_estoque,
                   p.estoque_minimo, p.unidade, p.ativo,
                   c.nome AS categoria, f.nome AS fornecedor
            FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id
            LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
            WHERE {$clausula}
            ORDER BY p.nome
            LIMIT {$porPagina} OFFSET {$offset}
        ");
        $stmt->execute($params);
        [$total, $paginaAtual, $paginas] = metaPaginacao($total, $pagina, $porPagina);
        ApiResponse::send(true, $stmt->fetchAll(), 'Produtos encontrados.', 200, [
            'total' => $total, 'page' => $paginaAtual, 'per_page' => $porPagina, 'pages' => $paginas,
        ]);
    }

    if ($entidade === 'categories') {
        $dados = $pdo->query("
            SELECT c.id, c.nome, c.descricao, c.ativo, COUNT(p.id) AS total_produtos
            FROM categorias c
            LEFT JOIN produtos p ON p.categoria_id = c.id
            GROUP BY c.id
            ORDER BY c.nome
            LIMIT {$porPagina} OFFSET {$offset}
        ")->fetchAll();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
        ApiResponse::send(true, $dados, 'Categorias encontradas.', 200, [
            'total' => $total, 'page' => $pagina, 'per_page' => $porPagina, 'pages' => max(1, (int) ceil($total / $porPagina)),
        ]);
    }

    if ($entidade === 'suppliers') {
        $dados = $pdo->query("
            SELECT f.id, f.nome, f.cnpj_cpf, f.telefone, f.email, f.endereco,
                   f.ativo, COUNT(p.id) AS total_produtos
            FROM fornecedores f
            LEFT JOIN produtos p ON p.fornecedor_id = f.id
            GROUP BY f.id
            ORDER BY f.nome
            LIMIT {$porPagina} OFFSET {$offset}
        ")->fetchAll();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM fornecedores')->fetchColumn();
        ApiResponse::send(true, $dados, 'Fornecedores encontrados.', 200, [
            'total' => $total, 'page' => $pagina, 'per_page' => $porPagina, 'pages' => max(1, (int) ceil($total / $porPagina)),
        ]);
    }

    if ($entidade === 'inventory') {
        if ($id > 0) {
            $stmt = $pdo->prepare("
                SELECT id, titulo, status, observacao, criado_por_nome, criado_em,
                       finalizado_por_nome, finalizado_em
                FROM inventarios WHERE id = :id LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $inventario = $stmt->fetch();
            if (!$inventario) {
                ApiResponse::error('Inventário não encontrado.', 404);
            }
            $stmtItens = $pdo->prepare("
                SELECT p.id AS produto_id, p.sku, p.nome AS produto,
                       ii.estoque_sistema, ii.quantidade_contada, ii.diferenca,
                       ii.contado_por_nome, ii.contado_em, ii.ajustado
                FROM inventario_itens ii
                INNER JOIN produtos p ON p.id = ii.produto_id
                WHERE ii.inventario_id = :id
                ORDER BY p.nome, p.id
                LIMIT {$porPagina} OFFSET {$offset}
            ");
            $stmtItens->execute([':id' => $id]);
            $inventario['itens'] = $stmtItens->fetchAll();
            $stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM inventario_itens WHERE inventario_id = :id');
            $stmtTotal->execute([':id' => $id]);
            $total = (int) $stmtTotal->fetchColumn();
            ApiResponse::send(true, $inventario, 'Inventário encontrado.', 200, [
                'total' => $total, 'page' => $pagina, 'per_page' => $porPagina, 'pages' => max(1, (int) ceil($total / $porPagina)),
            ]);
        }
        $dados = $pdo->query("
            SELECT i.id, i.titulo, i.status, i.criado_por_nome, i.criado_em,
                   i.finalizado_por_nome, i.finalizado_em,
                   COUNT(ii.id) AS total_itens,
                   COALESCE(SUM(ii.quantidade_contada IS NOT NULL), 0) AS itens_contados,
                   COALESCE(SUM(ii.diferenca <> 0), 0) AS divergencias
            FROM inventarios i
            LEFT JOIN inventario_itens ii ON ii.inventario_id = i.id
            GROUP BY i.id
            ORDER BY i.criado_em DESC
            LIMIT {$porPagina} OFFSET {$offset}
        ")->fetchAll();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM inventarios')->fetchColumn();
        ApiResponse::send(true, $dados, 'Inventários encontrados.', 200, [
            'total' => $total, 'page' => $pagina, 'per_page' => $porPagina, 'pages' => max(1, (int) ceil($total / $porPagina)),
        ]);
    }

    if ($entidade === 'movements') {
        $where = ['1 = 1'];
        $params = [];
        $tipo = $_GET['type'] ?? '';
        if (in_array($tipo, ['entrada', 'saida'], true)) {
            $where[] = 'm.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }
        $produtoId = (int) ($_GET['product_id'] ?? 0);
        if ($produtoId > 0) {
            $where[] = 'm.produto_id = :produto_id';
            $params[':produto_id'] = $produtoId;
        }
        $clausula = implode(' AND ', $where);
        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM movimentacoes m WHERE {$clausula}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT m.id, m.tipo, m.quantidade, m.saldo_anterior, m.saldo_posterior,
                   m.valor_unitario, m.motivo, m.origem, m.observacao, m.data_movimentacao,
                   p.id AS produto_id, p.nome AS produto, f.nome AS fornecedor,
                   u.nome AS responsavel
            FROM movimentacoes m
            INNER JOIN produtos p ON p.id = m.produto_id
            INNER JOIN usuarios u ON u.id = m.usuario_id
            LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
            WHERE {$clausula}
            ORDER BY m.data_movimentacao DESC, m.id DESC
            LIMIT {$porPagina} OFFSET {$offset}
        ");
        $stmt->execute($params);
        [$total, $paginaAtual, $paginas] = metaPaginacao($total, $pagina, $porPagina);
        ApiResponse::send(true, $stmt->fetchAll(), 'Movimentações encontradas.', 200, [
            'total' => $total, 'page' => $paginaAtual, 'per_page' => $porPagina, 'pages' => $paginas,
        ]);
    }

    if ($entidade === 'reports') {
        $dados = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM produtos WHERE ativo = 1) AS produtos_ativos,
                (SELECT COALESCE(SUM(quantidade_estoque), 0) FROM produtos WHERE ativo = 1) AS unidades_estoque,
                (SELECT COUNT(*) FROM produtos WHERE ativo = 1 AND quantidade_estoque <= estoque_minimo) AS estoque_baixo,
                (SELECT COUNT(*) FROM produtos WHERE ativo = 1 AND quantidade_estoque = 0) AS sem_estoque,
                (SELECT COUNT(*) FROM fornecedores WHERE ativo = 1) AS fornecedores_ativos,
                (SELECT COUNT(*) FROM movimentacoes WHERE data_movimentacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS movimentos_30_dias,
                (SELECT COUNT(*) FROM inventarios WHERE status = 'aberto') AS inventarios_abertos
        ")->fetch();
        ApiResponse::send(true, $dados, 'Indicadores encontrados.');
    }

    ApiResponse::error('Endpoint não encontrado.', 404);
} catch (Throwable $e) {
    error_log('Erro na API v1 (' . $recurso . '): ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
    ApiResponse::error('Não foi possível processar a solicitação.', 500);
}

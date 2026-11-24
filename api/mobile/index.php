<?php
declare(strict_types=1);
// Nada de sessão/cookie PHP: a API usa somente Authorization: Bearer.
ini_set('display_errors', '0');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

try {
    $config = mobileConfig();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        header('Vary: Origin');
        if (!in_array($origin, $config['allowed_origins'], true)) {
            throw new ApiError('Origem do navegador não autorizada.', 403);
        }
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Max-Age: 600');
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') { respond(['data' => ['ok' => true]]); }
    $resource = $_GET['resource'] ?? '';
    if (!is_string($resource)) { throw new ApiError('Recurso inválido.', 400); }
    $routes = ['health' => 'GET', 'login' => 'POST', 'logout' => 'POST', 'bootstrap' => 'GET',
        'products' => 'POST', 'products/toggle' => 'POST', 'categories' => 'POST', 'categories/toggle' => 'POST',
        'suppliers' => 'POST', 'suppliers/toggle' => 'POST', 'users' => 'POST', 'users/toggle' => 'POST',
        'movements' => 'POST', 'settings' => 'POST', 'notifications/read' => 'POST',
        'notifications/read-all' => 'POST'];
    if (!isset($routes[$resource])) { throw new ApiError('Recurso não encontrado.', 404); }
    if ($method !== $routes[$resource]) {
        header('Allow: ' . $routes[$resource]);
        throw new ApiError('Método não permitido.', 405);
    }
    if ($resource === 'health') {
        respond(['data' => ['status' => 'ok', 'service' => 'SmartControl Mobile API', 'version' => 2]]);
    }
    $body = [];
    if ($method === 'POST') {
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            throw new ApiError('Envie Content-Type: application/json.', 415);
        }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) { throw new ApiError('Corpo muito grande.', 413); }
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if ($raw === false || strlen($raw) > 65536) { throw new ApiError('Corpo muito grande.', 413); }
        try {
            $decoded = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) { throw new ApiError('JSON inválido.', 400); }
        if (!$decoded instanceof stdClass) { throw new ApiError('Envie um objeto JSON.', 400); }
        $body = (array) $decoded;
    }
    $api = new MobileApi(mobileDatabase($config), $config);
    if ($resource === 'login') {
        respond(['data' => $api->login($body, $_SERVER['REMOTE_ADDR'] ?? 'unknown')]);
    }
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $user = $api->authenticate($authorization);
    if ($resource === 'logout') { respond(['data' => $api->logout()]); }
    if ($resource === 'bootstrap') { respond(['data' => $api->bootstrap($user)]); }
    respond(['data' => $api->mutate($resource, $body, $user)]);
} catch (ApiError $error) {
    if ($error->status === 429) { header('Retry-After: 900'); }
    respond(['error' => $error->getMessage()], $error->status);
} catch (PDOException $error) {
    error_log('SmartControl Mobile API database error: ' . $error->getCode());
    if (($error->errorInfo[1] ?? null) === 1062) {
        respond(['error' => 'Um registro com estes dados já existe. Atualize a lista e tente novamente.'], 409);
    }
    respond(['error' => 'Banco indisponível ou API ainda não instalada. Confira a configuração no servidor.'], 503);
} catch (Throwable $error) {
    error_log('SmartControl Mobile API error: ' . get_class($error));
    respond(['error' => 'Não foi possível concluir a solicitação.'], 500);
}

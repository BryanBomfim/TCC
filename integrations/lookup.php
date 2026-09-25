<?php
declare(strict_types=1);

use SmartControl\Services\ExternalLookupService;

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

function responderIntegracao(bool $success, ?array $data, string $message, int $status): never
{
    http_response_code($status);
    echo json_encode(compact('success', 'data', 'message'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!estaLogado()) {
    responderIntegracao(false, null, 'Não autorizado.', 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderIntegracao(false, null, 'Método não permitido.', 405);
}

$entrada = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($entrada) || !validarTokenCSRF($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    responderIntegracao(false, null, 'Solicitação inválida ou sessão expirada.', 419);
}

$acao = $entrada['action'] ?? '';
$valor = (string) ($entrada['value'] ?? '');
if (!in_array($acao, ['cep', 'cnpj'], true)) {
    responderIntegracao(false, null, 'Consulta inválida.', 400);
}
if (!permitirRequisicaoLimitada('integracao_' . $acao, (string) $_SESSION['usuario_id'], 30, 60)) {
    responderIntegracao(false, null, 'Muitas consultas em pouco tempo. Aguarde um minuto.', 429);
}

try {
    $servico = new ExternalLookupService(max(2, smartcontrolEnvInt('EXTERNAL_API_TIMEOUT', 5)));
    $dados = $acao === 'cep' ? $servico->consultarCep($valor) : $servico->consultarCnpj($valor);
    responderIntegracao(true, $dados, 'Dados encontrados em ' . $dados['fonte'] . '.', 200);
} catch (DomainException $e) {
    responderIntegracao(false, null, $e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Erro inesperado na integração: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
    responderIntegracao(false, null, 'A consulta está indisponível. Preencha os dados manualmente.', 503);
}

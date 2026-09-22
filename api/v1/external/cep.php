<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../services/ExternalApiService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') apiErro('Método não permitido.', 405);
$cep = (string) ($_GET['cep'] ?? '');

try {
    $service = new ExternalApiService();
    apiResposta($service->consultarCep($cep), 'CEP consultado com sucesso.');
} catch (InvalidArgumentException $e) {
    apiErro($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Falha na consulta de CEP: ' . $e->getMessage());
    apiErro('Não foi possível consultar o CEP agora. Preencha o endereço manualmente.', 503);
}

<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../services/ExternalApiService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') apiErro('Método não permitido.', 405);
$cnpj = (string) ($_GET['cnpj'] ?? '');

try {
    $service = new ExternalApiService();
    apiResposta($service->consultarCnpj($cnpj), 'CNPJ consultado com sucesso.');
} catch (InvalidArgumentException $e) {
    apiErro($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Falha na consulta de CNPJ: ' . $e->getMessage());
    apiErro('Não foi possível consultar o CNPJ agora. Preencha os dados manualmente.', 503);
}

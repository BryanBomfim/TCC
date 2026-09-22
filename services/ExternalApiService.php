<?php
/**
 * Integrações externas opcionais do SmartControl.
 * Nenhuma delas é necessária para o funcionamento central do sistema.
 */

class ExternalApiService
{
    private int $timeout;

    public function __construct(int $timeout = 5)
    {
        $this->timeout = max(2, min($timeout, 15));
    }

    public function consultarCep(string $cep): array
    {
        $cep = preg_replace('/\D+/', '', $cep) ?? '';
        if (!preg_match('/^\d{8}$/', $cep)) {
            throw new InvalidArgumentException('CEP inválido. Informe 8 dígitos.');
        }

        try {
            $dados = $this->getJson('https://viacep.com.br/ws/' . $cep . '/json/');
            if (!empty($dados['erro'])) {
                throw new RuntimeException('CEP não encontrado.');
            }

            return [
                'cep' => $dados['cep'] ?? $cep,
                'logradouro' => $dados['logradouro'] ?? '',
                'complemento' => $dados['complemento'] ?? '',
                'bairro' => $dados['bairro'] ?? '',
                'cidade' => $dados['localidade'] ?? '',
                'uf' => $dados['uf'] ?? '',
                'fonte' => 'ViaCEP',
            ];
        } catch (Throwable $primeiroErro) {
            error_log('ViaCEP indisponível/falhou: ' . $primeiroErro->getMessage());

            $dados = $this->getJson('https://brasilapi.com.br/api/cep/v2/' . $cep);
            return [
                'cep' => $dados['cep'] ?? $cep,
                'logradouro' => $dados['street'] ?? '',
                'complemento' => '',
                'bairro' => $dados['neighborhood'] ?? '',
                'cidade' => $dados['city'] ?? '',
                'uf' => $dados['state'] ?? '',
                'fonte' => 'BrasilAPI',
            ];
        }
    }

    public function consultarCnpj(string $cnpj): array
    {
        $cnpj = preg_replace('/\D+/', '', $cnpj) ?? '';
        if (!preg_match('/^\d{14}$/', $cnpj)) {
            throw new InvalidArgumentException('CNPJ inválido. Informe 14 dígitos.');
        }

        $dados = $this->getJson('https://brasilapi.com.br/api/cnpj/v1/' . $cnpj);

        return [
            'cnpj' => $dados['cnpj'] ?? $cnpj,
            'razao_social' => $dados['razao_social'] ?? '',
            'nome_fantasia' => $dados['nome_fantasia'] ?? '',
            'telefone' => $dados['ddd_telefone_1'] ?? '',
            'email' => $dados['email'] ?? '',
            'cep' => $dados['cep'] ?? '',
            'logradouro' => $dados['logradouro'] ?? '',
            'numero' => $dados['numero'] ?? '',
            'complemento' => $dados['complemento'] ?? '',
            'bairro' => $dados['bairro'] ?? '',
            'cidade' => $dados['municipio'] ?? '',
            'uf' => $dados['uf'] ?? '',
            'fonte' => 'BrasilAPI',
        ];
    }

    private function getJson(string $url): array
    {
        $body = null;
        $httpCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: SmartControl-TCC/1.0',
                ],
            ]);

            $resultado = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $erroCurl = curl_error($ch);
            curl_close($ch);

            if ($resultado === false) {
                throw new RuntimeException('Falha de comunicação com o serviço externo: ' . $erroCurl);
            }
            $body = $resultado;
        } else {
            $contexto = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nUser-Agent: SmartControl-TCC/1.0\r\n",
                ],
            ]);
            $resultado = @file_get_contents($url, false, $contexto);
            if ($resultado === false) {
                throw new RuntimeException('Não foi possível consultar o serviço externo.');
            }
            $body = $resultado;

            $cabecalho = $http_response_header[0] ?? '';
            if (preg_match('/\s(\d{3})\s/', $cabecalho, $m)) {
                $httpCode = (int) $m[1];
            }
        }

        if ($httpCode >= 400) {
            throw new RuntimeException('Serviço externo respondeu com HTTP ' . $httpCode . '.');
        }

        $dados = json_decode((string) $body, true);
        if (!is_array($dados)) {
            throw new RuntimeException('Resposta inválida do serviço externo.');
        }

        return $dados;
    }
}

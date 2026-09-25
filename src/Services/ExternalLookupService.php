<?php
declare(strict_types=1);

namespace SmartControl\Services;

use DomainException;
use JsonException;
use RuntimeException;

/** Camada de integração com serviços públicos, sempre com fallback manual na UI. */
final class ExternalLookupService
{
    public function __construct(private readonly int $timeoutSegundos = 5)
    {
    }

    /** @return array<string, string> */
    public function consultarCep(string $cepInformado): array
    {
        $cep = preg_replace('/\D/', '', $cepInformado) ?? '';
        if (!preg_match('/^\d{8}$/', $cep)) {
            throw new DomainException('Informe um CEP com 8 dígitos.');
        }

        try {
            $dados = $this->requisitarJson('https://viacep.com.br/ws/' . $cep . '/json/');
            if (($dados['erro'] ?? false) === true) {
                throw new RuntimeException('CEP não encontrado no ViaCEP.');
            }

            return [
                'cep' => $cep,
                'logradouro' => trim((string) ($dados['logradouro'] ?? '')),
                'complemento' => trim((string) ($dados['complemento'] ?? '')),
                'bairro' => trim((string) ($dados['bairro'] ?? '')),
                'cidade' => trim((string) ($dados['localidade'] ?? '')),
                'uf' => strtoupper(trim((string) ($dados['uf'] ?? ''))),
                'fonte' => 'ViaCEP',
            ];
        } catch (RuntimeException $e) {
            error_log('Consulta ViaCEP falhou; usando fallback: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
        }

        try {
            $dados = $this->requisitarJson('https://brasilapi.com.br/api/cep/v2/' . $cep);
            return [
                'cep' => $cep,
                'logradouro' => trim((string) ($dados['street'] ?? '')),
                'complemento' => '',
                'bairro' => trim((string) ($dados['neighborhood'] ?? '')),
                'cidade' => trim((string) ($dados['city'] ?? '')),
                'uf' => strtoupper(trim((string) ($dados['state'] ?? ''))),
                'fonte' => 'BrasilAPI',
            ];
        } catch (RuntimeException $e) {
            error_log('Consulta BrasilAPI CEP falhou: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
            throw new DomainException('Não foi possível consultar o CEP agora. Preencha o endereço manualmente.');
        }
    }

    /** @return array<string, string> */
    public function consultarCnpj(string $cnpjInformado): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpjInformado) ?? '';
        if (!$this->cnpjValido($cnpj)) {
            throw new DomainException('Informe um CNPJ válido com 14 dígitos.');
        }

        try {
            $dados = $this->requisitarJson('https://brasilapi.com.br/api/cnpj/v1/' . $cnpj);
        } catch (RuntimeException $e) {
            error_log('Consulta BrasilAPI CNPJ falhou: ' . get_class($e) . ' código ' . $e->getCode() . ' em ' . $e->getFile() . ':' . $e->getLine());
            throw new DomainException('Não foi possível consultar o CNPJ agora. Preencha os dados manualmente.');
        }

        $telefone = preg_replace('/\D/', '', (string) ($dados['ddd_telefone_1'] ?? '')) ?? '';
        $cep = str_pad((string) ($dados['cep'] ?? ''), 8, '0', STR_PAD_LEFT);
        $tipoLogradouro = trim((string) ($dados['descricao_tipo_de_logradouro'] ?? ''));
        $logradouro = trim((string) ($dados['logradouro'] ?? ''));

        return [
            'cnpj_cpf' => $cnpj,
            'nome' => trim((string) (($dados['nome_fantasia'] ?? '') ?: ($dados['razao_social'] ?? ''))),
            'razao_social' => trim((string) ($dados['razao_social'] ?? '')),
            'email' => strtolower(trim((string) ($dados['email'] ?? ''))),
            'telefone' => $telefone,
            'cep' => preg_match('/^\d{8}$/', $cep) ? $cep : '',
            'logradouro' => trim($tipoLogradouro . ' ' . $logradouro),
            'numero' => trim((string) ($dados['numero'] ?? '')),
            'complemento' => trim((string) ($dados['complemento'] ?? '')),
            'bairro' => trim((string) ($dados['bairro'] ?? '')),
            'cidade' => trim((string) ($dados['municipio'] ?? '')),
            'uf' => strtoupper(trim((string) ($dados['uf'] ?? ''))),
            'fonte' => 'BrasilAPI',
        ];
    }

    /** @return array<string, mixed> */
    private function requisitarJson(string $url): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Não foi possível iniciar o cliente HTTP.');
        }

        $opcoes = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(3, $this->timeoutSegundos),
            CURLOPT_TIMEOUT => $this->timeoutSegundos,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'SmartControl-TCC/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (defined('CURLSSLOPT_NATIVE_CA')) {
            $opcoes[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }

        curl_setopt_array($curl, $opcoes);

        $corpo = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $erro = curl_error($curl);
        curl_close($curl);

        if (!is_string($corpo) || $status !== 200) {
            throw new RuntimeException('Serviço externo respondeu HTTP ' . $status . ($erro !== '' ? ': ' . $erro : '.'));
        }
        if (strlen($corpo) > 1024 * 1024) {
            throw new RuntimeException('Resposta externa excedeu o limite de tamanho.');
        }

        try {
            $dados = json_decode($corpo, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Serviço externo retornou JSON inválido.', 0, $e);
        }

        if (!is_array($dados)) {
            throw new RuntimeException('Serviço externo retornou um formato inesperado.');
        }

        return $dados;
    }

    private function cnpjValido(string $cnpj): bool
    {
        if (!preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        for ($digito = 12; $digito < 14; $digito++) {
            $soma = 0;
            $peso = $digito - 7;
            for ($i = 0; $i < $digito; $i++) {
                $soma += (int) $cnpj[$i] * $peso--;
                if ($peso < 2) {
                    $peso = 9;
                }
            }
            $calculado = $soma % 11 < 2 ? 0 : 11 - ($soma % 11);
            if ((int) $cnpj[$digito] !== $calculado) {
                return false;
            }
        }

        return true;
    }
}

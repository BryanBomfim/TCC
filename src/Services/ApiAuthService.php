<?php
declare(strict_types=1);

namespace SmartControl\Services;

use DomainException;
use PDO;

final class ApiAuthService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function autenticar(?string $cabecalho): array
    {
        if ($cabecalho === null || !preg_match('/^Bearer\s+(sc_[a-f0-9]{64})$/i', trim($cabecalho), $partes)) {
            throw new DomainException('Token de acesso ausente ou inválido.');
        }

        $tokenHash = hash('sha256', $partes[1]);
        $stmt = $this->pdo->prepare("
            SELECT t.id AS token_id, t.nome AS token_nome, t.escopos, t.ultimo_uso_em,
                   u.id AS usuario_id, u.nome AS usuario_nome, u.perfil, u.senha
            FROM api_tokens t
            INNER JOIN usuarios u ON u.id = t.usuario_id
            WHERE t.token_hash = :token_hash
              AND t.revogado_em IS NULL
              AND (t.expira_em IS NULL OR t.expira_em > NOW())
              AND u.ativo = 1
              AND u.email_verificado_em IS NOT NULL
              AND t.escopos = 'leitura'
            LIMIT 1
        ");
        $stmt->execute([':token_hash' => $tokenHash]);
        $identidade = $stmt->fetch();

        if (!$identidade) {
            throw new DomainException('Token de acesso ausente ou inválido.');
        }

        if (password_verify('SmartControl@2026!', $identidade['senha'])) {
            throw new DomainException('Defina uma senha exclusiva antes de utilizar a API.');
        }
        unset($identidade['senha']);

        $ultimoUso = $identidade['ultimo_uso_em'] ? strtotime($identidade['ultimo_uso_em']) : 0;
        if ($ultimoUso < time() - 300) {
            $stmtUso = $this->pdo->prepare('UPDATE api_tokens SET ultimo_uso_em = NOW() WHERE id = :id');
            $stmtUso->execute([':id' => $identidade['token_id']]);
        }

        return $identidade + ['token_hash_requisicao' => $tokenHash];
    }
}

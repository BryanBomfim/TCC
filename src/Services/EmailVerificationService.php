<?php
declare(strict_types=1);

namespace SmartControl\Services;

use PDO;

final class EmailVerificationService
{
    /** @var callable(string, string, string, string, string): bool */
    private $enviarEmail;

    public function __construct(
        private readonly PDO $pdo,
        callable $enviarEmail
    ) {
        $this->enviarEmail = $enviarEmail;
    }

    public function enviar(int $usuarioId, string $nome, string $email): bool
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiraEm = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->pdo->prepare("
            INSERT INTO verificacoes_email
                (usuario_id, token_hash, expira_em)
            VALUES
                (:usuario_id, :token_hash, :expira_em)
        ");
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':token_hash' => $tokenHash,
            ':expira_em' => $expiraEm,
        ]);

        $link = APP_URL . '/auth/verificar_email.php?token=' . urlencode($token);
        $nomeSeguro = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $linkSeguro = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        $html = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333">
                <h2 style="color: #1a4f8b">SmartControl</h2>
                <p>Olá, ' . $nomeSeguro . '.</p>
                <p>Confirme seu endereço de e-mail para continuar utilizando sua conta.</p>
                <p style="margin: 30px 0">
                    <a href="' . $linkSeguro . '" style="background: #1a4f8b; color: #fff; padding: 12px 20px; text-decoration: none; border-radius: 5px">
                        Verificar meu e-mail
                    </a>
                </p>
                <p>O link é válido por 24 horas e pode ser utilizado apenas uma vez.</p>
                <p style="font-size: 12px; color: #777">Mensagem automática do SmartControl.</p>
            </div>
        ';

        $texto = "SmartControl\n\n"
            . "Olá, {$nome}.\n\n"
            . "Confirme seu endereço de e-mail pelo link:\n{$link}\n\n"
            . 'O link é válido por 24 horas e pode ser utilizado apenas uma vez.';

        $enviado = ($this->enviarEmail)(
            $email,
            $nome,
            'Confirme seu e-mail - SmartControl',
            $html,
            $texto
        );

        if ($enviado) {
            $this->invalidarTokensAnteriores($usuarioId, $tokenHash);
        } else {
            $stmtRemover = $this->pdo->prepare("
                DELETE FROM verificacoes_email
                WHERE token_hash = :token_hash
            ");
            $stmtRemover->execute([':token_hash' => $tokenHash]);
        }

        return $enviado;
    }

    private function invalidarTokensAnteriores(int $usuarioId, string $tokenAtualHash): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE verificacoes_email
            SET utilizado_em = NOW()
            WHERE usuario_id = :usuario_id
              AND utilizado_em IS NULL
              AND token_hash <> :token_atual_hash
        ");
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':token_atual_hash' => $tokenAtualHash,
        ]);
    }
}

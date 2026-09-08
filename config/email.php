    <?php
/**
 * config/email.php
 *
 * Configuração do envio de e-mails.
 * As credenciais reais ficam em email.local.php,
 * que NÃO deve ser enviado ao GitHub.
 */

$configLocal = __DIR__ . '/email.local.php';

if (!file_exists($configLocal)) {
    throw new RuntimeException(
        'Arquivo config/email.local.php não encontrado.'
    );
}

return require $configLocal;

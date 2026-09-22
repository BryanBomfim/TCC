<?php
/**
 * Configuração de e-mail.
 * Prioriza variáveis de ambiente e mantém email.local.php como fallback
 * para instalações antigas.
 */

require_once __DIR__ . '/environment.php';

$host = (string) env('SMTP_HOST', '');

if ($host !== '') {
    return [
        'host' => $host,
        'porta' => (int) env('SMTP_PORT', 587),
        'usuario' => (string) env('SMTP_USER', ''),
        'senha' => (string) env('SMTP_PASSWORD', ''),
        'remetente_email' => (string) env('SMTP_FROM_EMAIL', env('SMTP_USER', '')),
        'remetente_nome' => (string) env('SMTP_FROM_NAME', 'SmartControl'),
    ];
}

$configLocal = __DIR__ . '/email.local.php';

if (is_file($configLocal)) {
    return require $configLocal;
}

throw new RuntimeException('SMTP não configurado. Defina as variáveis SMTP_* no .env.');

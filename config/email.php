<?php
/**
 * Configuração de e-mail obtida exclusivamente do ambiente.
 */

if (!function_exists('smartcontrolEnv')) {
    require_once __DIR__ . '/environment.php';
    smartcontrolLoadEnv(__DIR__ . '/../.env');
}

if (!smartcontrolEnvBool('SMTP_ENABLED', false)) {
    throw new RuntimeException('O envio SMTP está desativado no ambiente.');
}

$configuracao = [
    'host' => (string) smartcontrolEnv('SMTP_HOST', ''),
    'porta' => smartcontrolEnvInt('SMTP_PORT', 587),
    'criptografia' => strtolower((string) smartcontrolEnv('SMTP_ENCRYPTION', 'tls')),
    'usuario' => (string) smartcontrolEnv('SMTP_USER', ''),
    'senha' => (string) smartcontrolEnv('SMTP_PASSWORD', ''),
    'remetente_email' => (string) smartcontrolEnv('SMTP_FROM_EMAIL', ''),
    'remetente_nome' => (string) smartcontrolEnv('SMTP_FROM_NAME', 'SmartControl'),
];

foreach (['host', 'usuario', 'senha', 'remetente_email'] as $campo) {
    if ($configuracao[$campo] === '') {
        throw new RuntimeException('Configuração SMTP incompleta no ambiente.');
    }
}

return $configuracao;

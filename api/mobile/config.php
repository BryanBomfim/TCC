<?php
declare(strict_types=1);

define('SMARTCONTROL_API', true);
require_once __DIR__ . '/../../config/config.php';

/** @return array{allowed_origins:list<string>,token_hours:int} */
function mobileConfig(): array
{
    $origens = (string) smartcontrolEnv('MOBILE_ALLOWED_ORIGINS', '');
    $horas = max(1, min(720, smartcontrolEnvInt('MOBILE_TOKEN_HOURS', 24)));

    return [
        'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', $origens)))),
        'token_hours' => $horas,
    ];
}

function mobileDatabase(array $config): PDO
{
    global $pdo;

    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Conexão com o banco indisponível.');
    }

    return $pdo;
}

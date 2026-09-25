<?php
/**
 * Carregamento mínimo de variáveis de ambiente, sem dependência externa.
 * Variáveis definidas pelo servidor têm prioridade sobre o arquivo .env.
 */

function smartcontrolLoadEnv(string $arquivo): void
{
    if (!is_file($arquivo) || !is_readable($arquivo)) {
        return;
    }

    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);

    if ($linhas === false) {
        return;
    }

    foreach ($linhas as $linha) {
        $linha = trim($linha);

        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = array_map('trim', explode('=', $linha, 2));

        if ($chave === '' || !preg_match('/^[A-Z][A-Z0-9_]*$/', $chave)) {
            continue;
        }

        if (getenv($chave) !== false || array_key_exists($chave, $_ENV)) {
            continue;
        }

        if (strlen($valor) >= 2) {
            $primeiro = $valor[0];
            $ultimo = $valor[strlen($valor) - 1];

            if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }

        putenv($chave . '=' . $valor);
        $_ENV[$chave] = $valor;
    }
}

function smartcontrolEnv(string $chave, ?string $padrao = null): ?string
{
    $valor = $_ENV[$chave] ?? getenv($chave);

    if ($valor === false || $valor === null) {
        return $padrao;
    }

    return (string) $valor;
}

function smartcontrolEnvBool(string $chave, bool $padrao = false): bool
{
    $valor = smartcontrolEnv($chave);

    if ($valor === null || $valor === '') {
        return $padrao;
    }

    return filter_var($valor, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $padrao;
}

function smartcontrolEnvInt(string $chave, int $padrao): int
{
    $valor = smartcontrolEnv($chave);

    if ($valor === null || filter_var($valor, FILTER_VALIDATE_INT) === false) {
        return $padrao;
    }

    return (int) $valor;
}


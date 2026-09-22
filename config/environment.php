<?php
/**
 * Carregamento leve de variáveis de ambiente.
 * Mantém o projeto sem framework e permite usar .env localmente.
 */

if (!function_exists('carregarArquivoEnv')) {
    function carregarArquivoEnv(string $arquivo): void
    {
        if (!is_file($arquivo) || !is_readable($arquivo)) {
            return;
        }

        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($linhas === false) {
            return;
        }

        foreach ($linhas as $linha) {
            $linha = trim($linha);

            if ($linha === '' || str_starts_with($linha, '#')) {
                continue;
            }

            $posicao = strpos($linha, '=');
            if ($posicao === false) {
                continue;
            }

            $chave = trim(substr($linha, 0, $posicao));
            $valor = trim(substr($linha, $posicao + 1));

            if ($chave === '' || getenv($chave) !== false || array_key_exists($chave, $_ENV)) {
                continue;
            }

            if (
                strlen($valor) >= 2
                && (($valor[0] === '"' && $valor[-1] === '"') || ($valor[0] === "'" && $valor[-1] === "'"))
            ) {
                $valor = substr($valor, 1, -1);
            }

            $_ENV[$chave] = $valor;
            putenv($chave . '=' . $valor);
        }
    }
}

if (!function_exists('env')) {
    function env(string $chave, mixed $padrao = null): mixed
    {
        $valor = $_ENV[$chave] ?? getenv($chave);

        if ($valor === false || $valor === null || $valor === '') {
            return $padrao;
        }

        $normalizado = strtolower(trim((string) $valor));

        return match ($normalizado) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $valor,
        };
    }
}

carregarArquivoEnv(dirname(__DIR__) . '/.env');

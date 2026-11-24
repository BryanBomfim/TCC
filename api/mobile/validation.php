<?php
declare(strict_types=1);

final class ApiError extends RuntimeException
{
    public int $status;
    public function __construct(string $message, int $status = 422)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

final class Input
{
    public static function text(array $body, string $key, int $max, bool $required = false, string $default = ''): string
    {
        $value = $body[$key] ?? $default;
        if (!is_string($value)) {
            throw new ApiError("O campo $key precisa ser texto.");
        }
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if (($required && $value === '') || $length > $max || str_contains($value, "\0")) {
            throw new ApiError("Preencha $key corretamente (até $max caracteres).");
        }
        return $value;
    }

    public static function integer(array $body, string $key, int $min = 0, int $default = 0): int
    {
        $value = $body[$key] ?? $default;
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]+$/D', (string) $value)
            || (float) $value > 2147483647 || (int) $value < $min) {
            throw new ApiError("O campo $key precisa ser um número inteiro entre $min e 2147483647.");
        }
        return (int) $value;
    }

    public static function id(array $body, string $key = 'id', bool $optional = false): ?int
    {
        if ($optional && (!isset($body[$key]) || $body[$key] === '')) {
            return null;
        }
        return self::integer($body, $key, 1);
    }

    public static function money(array $body, string $key): string
    {
        $value = $body[$key] ?? 0;
        if ((!is_int($value) && !is_float($value) && !is_string($value))
            || !is_numeric($value) || !is_finite((float) $value) || (float) $value < 0
            || (float) $value > 99999999.99) {
            throw new ApiError("O campo $key precisa ser um valor monetário positivo ou zero.");
        }
        return number_format((float) $value, 2, '.', '');
    }

    public static function email(array $body, bool $required = true): string
    {
        $value = strtolower(self::text($body, 'email', 150, $required));
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ApiError('Informe um e-mail válido.');
        }
        return $value;
    }

    public static function password(array $body, string $key, bool $required = true): string
    {
        $password = $body[$key] ?? '';
        if (!is_string($password) || str_contains($password, "\0")
            || strlen($password) > 72 || (($required || $password !== '') && strlen($password) < 10)) {
            throw new ApiError('A senha precisa ter de 10 a 72 bytes (caracteres com acento ocupam mais de um byte).');
        }
        return $password;
    }

    public static function choice(array $body, string $key, array $choices, string $default = ''): string
    {
        $value = $body[$key] ?? $default;
        if (!is_string($value) || !in_array($value, $choices, true)) {
            throw new ApiError("Valor inválido para $key.");
        }
        return $value;
    }

    public static function admin(array $user): void
    {
        if (($user['perfil'] ?? '') !== 'administrador' || (int) ($user['ativo'] ?? 0) !== 1) {
            throw new ApiError('Acesso restrito ao administrador.', 403);
        }
    }

    public static function stock(int $current, string $type, int $quantity): int
    {
        if ($quantity < 1 || !in_array($type, ['entrada', 'saida'], true)) {
            throw new ApiError('Movimentação inválida.');
        }
        $new = $type === 'entrada' ? $current + $quantity : $current - $quantity;
        if ($new < 0) {
            throw new ApiError('Estoque insuficiente para esta saída.', 409);
        }
        if ($new > 2147483647) {
            throw new ApiError('A entrada excede o limite de estoque.', 409);
        }
        return (int) $new;
    }
}

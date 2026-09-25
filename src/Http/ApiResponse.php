<?php
declare(strict_types=1);

namespace SmartControl\Http;

final class ApiResponse
{
    /** @param array<string, mixed>|list<mixed>|null $data @param array<string, mixed> $meta */
    public static function send(
        bool $success,
        array|null $data,
        string $message,
        int $status = 200,
        array $meta = []
    ): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');

        $payload = [
            'success' => $success,
            'data' => $data,
            'message' => $message,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function error(string $message, int $status): never
    {
        self::send(false, null, $message, $status);
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * HTTP Response Helper für HTML-Fragmente und JSON-Antworten
 */
class Response
{
    public static function html(string $content, int $status = 200): void
    {
        self::sendCompressed($content, 'text/html; charset=utf-8', $status);
    }

    public static function json(array $data, int $status = 200): void
    {
        $json = (string)json_encode($data, JSON_UNESCAPED_UNICODE);
        self::sendCompressed($json, 'application/json; charset=utf-8', $status);
    }

    private static function sendCompressed(string $payload, string $contentType, int $status): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: ' . $contentType);

            $acceptEncoding = (string)($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
            $zlibEnabled    = (bool)ini_get('zlib.output_compression');

            if (
                !$zlibEnabled
                && strlen($payload) > 2048
                && stripos($acceptEncoding, 'gzip') !== false
                && function_exists('gzencode')
            ) {
                $compressed = @gzencode($payload, 6);
                if ($compressed !== false) {
                    header('Content-Encoding: gzip');
                    header('Vary: Accept-Encoding');
                    header('Content-Length: ' . strlen($compressed));
                    echo $compressed;
                    exit;
                }
            }
        }
        echo $payload;
        exit;
    }

    public static function success(array $data = [], int $status = 200): void
    {
        self::json(array_merge(['status' => 'ok'], $data), $status);
    }

    public static function error(string $message, int $status = 400): void
    {
        self::json([
            'status'  => 'error',
            'message' => $message,
        ], $status);
    }
}

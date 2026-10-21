<?php
declare(strict_types=1);

namespace App\Core;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * HTTP Request Wrapper (PHP 8.0 - 8.4 kompatibel)
 */
class Request
{
    public function method(): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function route(): string
    {
        $route = trim((string)($_GET['route'] ?? $_POST['route'] ?? 'startseite'));
        $route = trim($route, '/');
        return $route !== '' ? $route : 'startseite';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }
}

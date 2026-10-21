<?php
declare(strict_types=1);

namespace App\Core;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * CSRF-Token-Generator & Prüfer
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['mcp_csrf_token'])) {
            $_SESSION['mcp_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['mcp_csrf_token'];
    }
}

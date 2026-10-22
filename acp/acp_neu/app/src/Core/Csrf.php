<?php
declare(strict_types=1);

namespace App\Core;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * CSRF-Token-Generator & Prüfer für das Admin-Control-Panel (ACP)
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['acp_csrf_token'])) {
            $_SESSION['acp_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['acp_csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '' || empty($_SESSION['acp_csrf_token'])) {
            return false;
        }
        return hash_equals((string)$_SESSION['acp_csrf_token'], $token);
    }
}

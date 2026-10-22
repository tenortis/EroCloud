<?php
declare(strict_types=1);

namespace App\Core;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * PSR-4 Autoloader für den Namespace App\
 */
class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix = 'App\\';
            $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}

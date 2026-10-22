<?php
declare(strict_types=1);

namespace App\Core;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * Template View Renderer & XSS-Escaping Helper
 */
class View
{
    public static function render(string $module, string $template, array $data = []): string
    {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Modules'
            . DIRECTORY_SEPARATOR . $module
            . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . $template . '.php';

        if (!file_exists($file)) {
            return '<div class="alert alert-danger">Ansicht nicht gefunden: ' . self::e($template) . '</div>';
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }

    /**
     * Rendert das bereichsübergreifende Fullscreen Cover-Studio Modal (für Filme, Foto-Alben usw.).
     *
     * @param array<string, mixed> $config
     */
    public static function renderCoverStudioModal(array $config = []): string
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'cover_studio_modal.php';
        if (!file_exists($file)) {
            return '';
        }

        extract($config, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

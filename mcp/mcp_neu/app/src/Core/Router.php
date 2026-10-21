<?php
declare(strict_types=1);

namespace App\Core;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud MCP Router & Dispatcher für SPA-Fragmente (api.php)
 */
class Router
{
    private static array $routes = [];

    public static function register(string $route, string $controllerClass, string $method): void
    {
        self::$routes[$route] = [$controllerClass, $method];
    }

    public static function dispatch(Request $request): void
    {
        $route = $request->route();

        if (empty(self::$routes)) {
            self::registerDefaults();
        }

        if (!isset(self::$routes[$route])) {
            Response::error("Die angeforderte Ansicht '{$route}' wurde nicht gefunden.", 404);
            return;
        }

        [$controllerClass, $action] = self::$routes[$route];

        if (!class_exists($controllerClass)) {
            Response::error("Controller '{$controllerClass}' existiert nicht.", 500);
            return;
        }

        try {
            $controller = new $controllerClass($request);
            if (!method_exists($controller, $action)) {
                Response::error("Aktion '{$action}' wurde im Controller nicht gefunden.", 500);
                return;
            }

            $result = $controller->$action();
            if (is_string($result)) {
                Response::html($result);
            }
        } catch (\Throwable $e) {
            Response::error("Interner Fehler bei Ausführung von '{$route}': " . $e->getMessage(), 500);
        }
    }

    private static function registerDefaults(): void
    {
        $ctrl = 'App\\Modules\\Preview\\PreviewController';

        // 1. Startseite (Eingeloggt & Ausgeloggte Gast-Vorschau)
        self::register('startseite', $ctrl, 'startseite');
        self::register('home', $ctrl, 'startseite');
        self::register('guest', $ctrl, 'guest');

        // 2. Content hochladen
        self::register('movie-upload', $ctrl, 'movieUpload');
        self::register('photo-album-upload', $ctrl, 'photoAlbumUpload');

        // 3. Content in der Cloud
        self::register('movies', $ctrl, 'movies');
        self::register('movie', $ctrl, 'movieDetail');
        self::register('photo-albums', $ctrl, 'photoAlbums');
        self::register('photo-album', $ctrl, 'photoAlbumDetail');

        // 4. Profile (Darsteller)
        self::register('actors', $ctrl, 'actors');
        self::register('new-actor', $ctrl, 'newActor');
        self::register('actor', $ctrl, 'actorDetail');

        // 5. Partnerprogramm (Webmaster)
        self::register('webmaster-ads', $ctrl, 'webmasterAds');
        self::register('webmaster-newads', $ctrl, 'webmasterNewAds');
        self::register('webmaster-stats', $ctrl, 'webmasterStats');
        self::register('webmaster-advertise-partner', $ctrl, 'webmasterAdvertisePartner');

        // 6. FAQ
        self::register('faq', $ctrl, 'faq');
    }
}

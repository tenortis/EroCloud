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
        // Authentifizierungs-Prüfung für alle API-Aufrufe
        if (!function_exists('is_logged_in') || is_logged_in('mcp') !== true) {
            Response::error('Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut über Pay4Coins an.', 401);
            return;
        }

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
            if (function_exists('p4c_errorlog')) {
                p4c_errorlog($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            Response::error("Interner Fehler bei Ausführung von '{$route}': " . $e->getMessage(), 500);
        }
    }

    private static function registerDefaults(): void
    {
        $homeCtrl    = 'App\\Modules\\Home\\HomeController';
        $actorsCtrl  = 'App\\Modules\\Actors\\ActorsController';
        $previewCtrl = 'App\\Modules\\Preview\\PreviewController';

        // 1. Startseite (Echtes Partner-Dashboard & Richtlinien-Bestätigung)
        self::register('startseite', $homeCtrl, 'view');
        self::register('home', $homeCtrl, 'view');
        self::register('home/accept-rules', $homeCtrl, 'acceptRules');

        // 2. Content hochladen
        self::register('movie-upload', $previewCtrl, 'movieUpload');
        self::register('photo-album-upload', $previewCtrl, 'photoAlbumUpload');

        // 3. Content in der Cloud
        self::register('movies', $previewCtrl, 'movies');
        self::register('movie', $previewCtrl, 'movieDetail');
        self::register('photo-albums', $previewCtrl, 'photoAlbums');
        self::register('photo-album', $previewCtrl, 'photoAlbumDetail');

        // 4. Profile (Echte Darsteller-Verwaltung & FSK16/FSK18 Bild-Upload)
        self::register('actors', $actorsCtrl, 'list');
        self::register('new-actor', $actorsCtrl, 'newActor');
        self::register('actor', $actorsCtrl, 'detail');
        self::register('actors/check-username', $actorsCtrl, 'checkUsername');
        self::register('actors/create', $actorsCtrl, 'create');
        self::register('actors/update', $actorsCtrl, 'update');
        self::register('actors/upload-image', $actorsCtrl, 'uploadImage');

        // 5. Partnerprogramm (Webmaster)
        self::register('webmaster-ads', $previewCtrl, 'webmasterAds');
        self::register('webmaster-newads', $previewCtrl, 'webmasterNewAds');
        self::register('webmaster-stats', $previewCtrl, 'webmasterStats');
        self::register('webmaster-advertise-partner', $previewCtrl, 'webmasterAdvertisePartner');

        // 6. FAQ
        self::register('faq', $previewCtrl, 'faq');
    }
}

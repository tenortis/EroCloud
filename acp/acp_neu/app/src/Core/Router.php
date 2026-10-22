<?php
declare(strict_types=1);

namespace App\Core;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

/**
 * EroCloud ACP Router & Dispatcher für SPA-Fragmente (api.php)
 */
class Router
{
    /** @var array<string, array{0: string, 1: string, 2: string}> */
    private static array $routes = [];

    public static function register(string $route, string $controllerClass, string $method, string $requiredAccess = ''): void
    {
        self::$routes[$route] = [$controllerClass, $method, $requiredAccess];
    }

    public static function dispatch(Request $request): void
    {
        $route = $request->route();

        // Authentifizierungs-Prüfung für alle ACP API-Aufrufe
        if (!function_exists('is_logged_in') || is_logged_in('acp') !== true || empty($_SESSION['employee_id'])) {
            Response::error('Deine ACP-Sitzung ist abgelaufen. Bitte melde dich erneut an.', 401);
            return;
        }

        // Leichtgewichtiger Session-Keep-Alive Heartbeat
        if ($route === 'ping') {
            if (function_exists('acp_neu_sync_auth_cookie') && !empty($_SESSION['employee_id'])) {
                acp_neu_sync_auth_cookie(
                    abs((int)$_SESSION['employee_id']),
                    (string)($_SESSION['employee_username'] ?? '')
                );
            }
            Response::json([
                'status'            => 'ok',
                'ts'                => time(),
                'remaining_seconds' => 1800,
            ]);
            return;
        }

        if (empty(self::$routes)) {
            self::registerDefaults();
        }

        if (!isset(self::$routes[$route])) {
            Response::error("Die angeforderte ACP-Ansicht '{$route}' wurde nicht gefunden.", 404);
            return;
        }

        [$controllerClass, $action, $requiredAccess] = self::$routes[$route];

        // Berechtigungs-Prüfung anhand von $_SESSION['employee_access_area']
        if ($requiredAccess !== '' && function_exists('acp_has_access') && !acp_has_access($requiredAccess)) {
            Response::html(
                '<div class="alert alert-warning m-4 d-flex align-items-center gap-2">'
                . '<i class="bi bi-shield-lock-fill fs-5"></i>'
                . '<div><strong>Keine Berechtigung:</strong> Dein Admin-Account verfügt nicht über die Berechtigung für diesen Bereich (<code>'
                . View::e($requiredAccess) . '</code>).</div>'
                . '</div>'
            );
            return;
        }

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
        $homeCtrl           = 'App\\Modules\\Home\\HomeController';
        $moviesCheckingCtrl = 'App\\Modules\\MoviesChecking\\MoviesCheckingController';
        $previewCtrl        = 'App\\Modules\\Preview\\PreviewController';

        // 1. Startseite (Echtes Admin-Dashboard, Globale Schnellsuche, Notizen & Live-Counter)
        self::register('startseite', $homeCtrl, 'view', '');
        self::register('home', $homeCtrl, 'view', '');
        self::register('home/global-search', $homeCtrl, 'globalSearch', '');
        self::register('home/admin-notes', $homeCtrl, 'handleAdminNotes', '');
        self::register('home/employee-notes', $homeCtrl, 'saveEmployeeNotes', '');
        self::register('home/sidebar-counts', $homeCtrl, 'sidebarCounts', '');

        // 2. Content prüfen (content_check)
        self::register('movies-checking', $moviesCheckingCtrl, 'list', 'content_check');
        self::register('movie-checking', $moviesCheckingCtrl, 'detail', 'content_check');
        self::register('movies-checking/approve', $moviesCheckingCtrl, 'approve', 'content_check');
        self::register('movies-checking/reject', $moviesCheckingCtrl, 'reject', 'content_check');
        self::register('movies-checking/co-actor-action', $moviesCheckingCtrl, 'coActorAction', 'content_check');
        self::register('movies-checking/ai-action', $moviesCheckingCtrl, 'aiAction', 'content_check');
        self::register('movies-checking/file-meta', $moviesCheckingCtrl, 'fileMeta', 'content_check');

        self::register('photo-albums-checking', $previewCtrl, 'photoAlbumsChecking', 'content_check');
        self::register('photo-album-checking', $previewCtrl, 'photoAlbumChecking', 'content_check');
        self::register('co-actors-checking', $previewCtrl, 'coActorsChecking', 'content_check');
        self::register('movies-blocked', $previewCtrl, 'moviesBlocked', 'content_check');
        self::register('ai-compliance', $previewCtrl, 'aiCompliance', 'content_check');

        // 3. Content Online (content_online)
        self::register('movies-online', $previewCtrl, 'moviesOnline', 'content_online');
        self::register('movie-edit', $previewCtrl, 'movieEdit', 'content_online');
        self::register('movies-drafts', $previewCtrl, 'moviesDrafts', 'content_online');
        self::register('photo-albums-online', $previewCtrl, 'photoAlbumsOnline', 'content_online');
        self::register('photo-album-edit', $previewCtrl, 'photoAlbumEdit', 'content_online');
        self::register('content-cleanup', $previewCtrl, 'contentCleanup', 'content_online');
        self::register('storage-analytics', $previewCtrl, 'storageAnalytics', 'content_online');

        // 4. Profile (actors)
        self::register('actors', $previewCtrl, 'actors', 'actors');
        self::register('actor', $previewCtrl, 'actor', 'actors');

        // 5. Webseiten (websites)
        self::register('sites', $previewCtrl, 'sites', 'websites');
        self::register('site', $previewCtrl, 'site', 'websites');

        // 6. Partnerprogramm (affiliate_program)
        self::register('webmaster-partner', $previewCtrl, 'webmasterPartner', 'affiliate_program');
        self::register('banners', $previewCtrl, 'banners', 'affiliate_program');
        self::register('banners-new', $previewCtrl, 'bannersNew', 'affiliate_program');

        // 7. Webcams (webcams)
        self::register('webcams', $previewCtrl, 'webcams', 'webcams');

        // 8. P4C-Merchants (merchants)
        self::register('merchants', $previewCtrl, 'merchants', 'merchants');
        self::register('merchant', $previewCtrl, 'merchant', 'merchants');

        // 9. Cloud-User (users)
        self::register('users', $previewCtrl, 'users', 'users');
        self::register('user', $previewCtrl, 'user', 'users');

        // 10. Punktesystem (point_system)
        self::register('pointssystem', $previewCtrl, 'pointssystem', 'point_system');

        // 11. System & Technik (error_log)
        self::register('system-settings', $previewCtrl, 'systemSettings', 'error_log');
        self::register('errors', $previewCtrl, 'errors', 'error_log');
    }
}

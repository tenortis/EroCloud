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
        $homeCtrl                = 'App\\Modules\\Home\\HomeController';
        $moviesCheckingCtrl      = 'App\\Modules\\MoviesChecking\\MoviesCheckingController';
        $moviesOnlineCtrl        = 'App\\Modules\\MoviesOnline\\MoviesOnlineController';
        $photoAlbumsCheckingCtrl = 'App\\Modules\\PhotoAlbumsChecking\\PhotoAlbumsCheckingController';
        $photoAlbumsOnlineCtrl   = 'App\\Modules\\PhotoAlbumsOnline\\PhotoAlbumsOnlineController';
        $coActorsCheckingCtrl    = 'App\\Modules\\CoActorsChecking\\CoActorsCheckingController';
        $aiComplianceCtrl        = 'App\\Modules\\AiCompliance\\AiComplianceController';
        $contentCleanupCtrl      = 'App\\Modules\\ContentCleanup\\ContentCleanupController';
        $storageAnalyticsCtrl    = 'App\\Modules\\StorageAnalytics\\StorageAnalyticsController';
        $actorsCtrl              = 'App\\Modules\\Actors\\ActorsController';
        $websitesCtrl            = 'App\\Modules\\Websites\\WebsitesController';
        $merchantsCtrl           = 'App\\Modules\\Merchants\\MerchantsController';
        $usersCtrl               = 'App\\Modules\\Users\\UsersController';
        $systemTechCtrl          = 'App\\Modules\\SystemTech\\SystemTechController';
        $previewCtrl             = 'App\\Modules\\Preview\\PreviewController';

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
        self::register('movies-checking/co-actor-action', $moviesCheckingCtrl, 'coActorAction', 'content_check,content_online');
        self::register('movies-checking/ai-action', $moviesCheckingCtrl, 'aiAction', 'content_check,content_online');
        self::register('movies-checking/file-meta', $moviesCheckingCtrl, 'fileMeta', 'content_check,content_online');

        self::register('photo-albums-checking', $photoAlbumsCheckingCtrl, 'list', 'content_check');
        self::register('photo-album-checking', $photoAlbumsCheckingCtrl, 'detail', 'content_check');
        self::register('photo-albums-checking/toggle-photo', $photoAlbumsCheckingCtrl, 'togglePhoto', 'content_check,content_online');
        self::register('photo-albums-checking/approve', $photoAlbumsCheckingCtrl, 'approve', 'content_check');
        self::register('photo-albums-checking/reject', $photoAlbumsCheckingCtrl, 'reject', 'content_check');
        self::register('photo-albums-checking/co-actor-action', $photoAlbumsCheckingCtrl, 'coActorAction', 'content_check,content_online');

        self::register('co-actors-checking', $coActorsCheckingCtrl, 'list', 'content_check');
        self::register('co-actors-checking/action', $coActorsCheckingCtrl, 'action', 'content_check,content_online');
        self::register('co-actors-checking/update-data', $coActorsCheckingCtrl, 'updateData', 'content_check,content_online');
        self::register('movies-blocked', $moviesOnlineCtrl, 'blocked', 'content_check,content_online');
        self::register('ai-compliance', $aiComplianceCtrl, 'index', 'content_check,content_online');
        self::register('ai-compliance/ajax', $aiComplianceCtrl, 'ajax', 'content_check,content_online');

        // 3. Content Online (content_online)
        self::register('movies-online', $moviesOnlineCtrl, 'list', 'content_online');
        self::register('movies-online/recalc-size', $moviesOnlineCtrl, 'recalcSize', 'content_online');
        self::register('movie-edit', $moviesOnlineCtrl, 'edit', 'content_online');
        self::register('movies-online/save', $moviesOnlineCtrl, 'save', 'content_online');
        self::register('movies-drafts', $moviesOnlineCtrl, 'drafts', 'content_online');
        self::register('photo-albums-online', $photoAlbumsOnlineCtrl, 'list', 'content_online');
        self::register('photo-albums-online/recalc-size', $photoAlbumsOnlineCtrl, 'recalcSize', 'content_online');
        self::register('photo-album-edit', $photoAlbumsOnlineCtrl, 'edit', 'content_online');
        self::register('photo-albums-online/save', $photoAlbumsOnlineCtrl, 'save', 'content_online');
        self::register('content-cleanup', $contentCleanupCtrl, 'index', 'content_online');
        self::register('content-cleanup/orphan-scan', $contentCleanupCtrl, 'orphanScan', 'content_online');
        self::register('storage-analytics', $storageAnalyticsCtrl, 'index', 'content_online');
        self::register('storage-analytics/scan', $storageAnalyticsCtrl, 'scan', 'content_online');
        self::register('storage-analytics/legacy-cleanup', $storageAnalyticsCtrl, 'legacyCleanup', 'content_online');

        // 4. Profile (actors)
        self::register('actors', $actorsCtrl, 'list', 'actors');
        self::register('actor', $actorsCtrl, 'detail', 'actors');
        self::register('actors/save', $actorsCtrl, 'save', 'actors');
        self::register('actors/delete-image', $actorsCtrl, 'deleteImage', 'actors');
        self::register('actors/reassign-merchant', $actorsCtrl, 'reassignMerchant', 'actors');

        // 5. Webseiten (websites)
        self::register('sites', $websitesCtrl, 'list', 'websites');
        self::register('site', $websitesCtrl, 'detail', 'websites');
        self::register('sites/sync-p4c', $websitesCtrl, 'syncFromP4c', 'websites');

        // 6. Partnerprogramm (affiliate_program)
        self::register('webmaster-partner', $previewCtrl, 'webmasterPartner', 'affiliate_program');
        self::register('banners', $previewCtrl, 'banners', 'affiliate_program');
        self::register('banners-new', $previewCtrl, 'bannersNew', 'affiliate_program');

        // 7. Webcams (webcams)
        self::register('webcams', $previewCtrl, 'webcams', 'webcams');

        // 8. P4C-Merchants (merchants)
        self::register('merchants', $merchantsCtrl, 'list', 'merchants');
        self::register('merchant', $merchantsCtrl, 'detail', 'merchants');
        self::register('merchants/save-settings', $merchantsCtrl, 'saveSettings', 'merchants');

        // 9. Cloud-User (users)
        self::register('users', $usersCtrl, 'list', 'users');
        self::register('user', $usersCtrl, 'detail', 'users');
        self::register('users/toggle-lock', $usersCtrl, 'toggleLock', 'users');

        // 10. Punktesystem (point_system)
        self::register('pointssystem', $previewCtrl, 'pointssystem', 'point_system');

        // 11. System & Technik (error_log)
        self::register('system-settings', $systemTechCtrl, 'settings', 'error_log');
        self::register('system-settings/save-imprint', $systemTechCtrl, 'saveImprint', 'error_log');
        self::register('system-settings/send-test-mail', $systemTechCtrl, 'sendTestMail', 'error_log');
        self::register('errors', $systemTechCtrl, 'errors', 'error_log');
        self::register('errors/action', $systemTechCtrl, 'errorLogAction', 'error_log');
    }
}

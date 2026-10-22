<?php
declare(strict_types=1);

namespace App\Modules\Home;

if (!defined('SAFE_INC')) {
    die("Hacking attempt...");
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) - HomeController
 * Lädt alle echten Admin-KPIs, Online-Listen, Notizen & Mitarbeiter-Historie für #/startseite.
 */
class HomeController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Rendert das eingeloggte Admin-Dashboard (#/startseite) mit echten DB-Daten
     */
    public function view(): string
    {
        global $settings;

        $employeeId = isset($_SESSION['employee_id']) ? abs((int)$_SESSION['employee_id']) : 0;
        if ($employeeId <= 0) {
            return '<div class="alert alert-warning m-4">Deine ACP-Sitzung ist abgelaufen. Bitte melde dich erneut an.</div>';
        }
        $employeeUsername = (string)($_SESSION['employee_username'] ?? 'Admin');
        $employeeRule     = (string)($_SESSION['employee_rule'] ?? 'Administrator');

        // 1. Live-KPIs für Content-Prüfung & Online-Bestand laden
        $counts = $this->fetchSystemCounts();

        // 2. Cloud-User in den letzten 10 Minuten online
        $onlineUsers = [];
        $tenMinAgoDate = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        $rsMembers = p4c_query(
            "SELECT `id`, `username`, `lastonline`, `amount_coins`, `domain`
             FROM `members`
             WHERE `lastonline` >= '" . p4c_escape_string($tenMinAgoDate) . "'
             ORDER BY `lastonline` DESC
             LIMIT 100;",
            __FILE__,
            __LINE__
        );
        if ($rsMembers && p4c_num_rows($rsMembers) > 0) {
            while ($m = p4c_fetch_object($rsMembers)) {
                $onlineUsers[] = [
                    'id'           => abs((int)$m->id),
                    'username'     => self::decodeLegacyText((string)($m->username ?? '')),
                    'lastonline'   => (string)($m->lastonline ?? ''),
                    'amount_coins' => abs((int)($m->amount_coins ?? 0)),
                    'domain'       => self::decodeLegacyText((string)($m->domain ?? '')),
                ];
            }
        }

        // 3. Darsteller in den letzten 10 Minuten online
        $onlineActors = [];
        $tenMinAgoTs = strtotime('-10 minutes');
        $rsActorsOnline = p4c_query(
            "SELECT `id`, `username`, `lastonline`, `merchant_id`
             FROM `actors`
             WHERE `lastonline` >= '" . abs((int)$tenMinAgoTs) . "'
             ORDER BY `lastonline` DESC
             LIMIT 100;",
            __FILE__,
            __LINE__
        );
        if ($rsActorsOnline && p4c_num_rows($rsActorsOnline) > 0) {
            while ($a = p4c_fetch_object($rsActorsOnline)) {
                $ts = abs((int)($a->lastonline ?? 0));
                $onlineActors[] = [
                    'id'          => abs((int)$a->id),
                    'username'    => self::decodeLegacyText((string)($a->username ?? '')),
                    'lastonline'  => $ts > 0 ? date('Y-m-d H:i:s', $ts) : '-',
                    'merchant_id' => abs((int)($a->merchant_id ?? 0)),
                ];
            }
        }

        // 4. Gemeinsame Admin-Notizen & Sperr-Status laden
        $adminNotesRaw  = is_object($settings) ? (string)$settings->get_var('admin_notes') : '';
        $adminNotesEdit = is_object($settings) ? abs((int)$settings->get_var('admin_notes_edit')) : 0;
        $adminNotes     = self::decodeLegacyText($adminNotesRaw);

        $adminNotesEditorName = '';
        if ($adminNotesEdit > 0) {
            $rsEditor = p4c_query("SELECT `username` FROM `employee` WHERE `id` = '" . $adminNotesEdit . "' LIMIT 1;", __FILE__, __LINE__);
            if ($rsEditor && p4c_num_rows($rsEditor) > 0) {
                $edObj = p4c_fetch_object($rsEditor);
                $adminNotesEditorName = (string)($edObj->username ?? ('Admin #' . $adminNotesEdit));
            }
        }

        // 5. Eigene Mitarbeiter-Notizen laden
        $employeeNotes = '';
        $rsEmp = p4c_query("SELECT `notes` FROM `employee` WHERE `id` = '" . $employeeId . "' LIMIT 1;", __FILE__, __LINE__);
        if ($rsEmp && p4c_num_rows($rsEmp) > 0) {
            $empObj = p4c_fetch_object($rsEmp);
            $employeeNotes = self::decodeLegacyText((string)($empObj->notes ?? ''));
        }

        // 6. Letzte 200 Aktionen aus employee_history laden
        $historyItems = [];
        $rsHistory = p4c_query(
            "SELECT h.`id`, h.`employee_id`, h.`description`, h.`datetime`, h.`ip`, e.`username`
             FROM `employee_history` h
             INNER JOIN `employee` e ON h.`employee_id` = e.`id`
             ORDER BY h.`id` DESC
             LIMIT 200;",
            __FILE__,
            __LINE__
        );
        if ($rsHistory && p4c_num_rows($rsHistory) > 0) {
            while ($h = p4c_fetch_object($rsHistory)) {
                $historyItems[] = [
                    'id'          => abs((int)$h->id),
                    'employee_id' => abs((int)$h->employee_id),
                    'username'    => self::decodeLegacyText((string)($h->username ?? '')),
                    'datetime'    => (string)($h->datetime ?? ''),
                    'ip'          => (string)($h->ip ?? ''),
                    'description' => self::decodeLegacyText((string)($h->description ?? '')),
                ];
            }
        }

        return View::render('Home', 'dashboard', [
            'employeeId'           => $employeeId,
            'employeeUsername'     => $employeeUsername,
            'employeeRule'         => $employeeRule,
            'counts'               => $counts,
            'onlineUsers'          => $onlineUsers,
            'onlineActors'         => $onlineActors,
            'adminNotes'           => $adminNotes,
            'adminNotesEdit'       => $adminNotesEdit,
            'adminNotesEditorName' => $adminNotesEditorName,
            'employeeNotes'        => $employeeNotes,
            'historyItems'         => $historyItems,
        ]);
    }

    /**
     * AJAX-Endpunkt zum Sperren, Speichern oder Freigeben der gemeinsamen Admin-Notizen
     */
    public function handleAdminNotes(): void
    {
        global $settings;

        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrf = (string)$this->request->post('_csrf', $this->request->header('X-CSRF-Token'));
        if (!Csrf::validate($csrf)) {
            Response::error('Dein Sicherheitstoken ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $employeeId = isset($_SESSION['employee_id']) ? abs((int)$_SESSION['employee_id']) : 0;
        if ($employeeId <= 0) {
            Response::error('Nicht eingeloggt.', 401);
            return;
        }

        $action      = trim((string)$this->request->post('action', ''));
        $currentEdit = is_object($settings) ? abs((int)$settings->get_var('admin_notes_edit')) : 0;

        if ($action === 'lock') {
            if ($currentEdit !== 0 && $currentEdit !== $employeeId) {
                Response::error('Die gemeinsamen Notizen werden aktuell bereits von einem anderen Admin bearbeitet.', 409);
                return;
            }
            p4c_query("UPDATE `settings` SET `value` = '" . $employeeId . "' WHERE `variable` = 'admin_notes_edit' LIMIT 1;", __FILE__, __LINE__);
            Response::json([
                'status'           => 'ok',
                'message'          => 'Bearbeitungsmodus für gemeinsame Notizen aktiviert.',
                'edit_employee_id' => $employeeId,
            ]);
            return;
        }

        if ($action === 'cancel') {
            if ($currentEdit === $employeeId) {
                p4c_query("UPDATE `settings` SET `value` = '0' WHERE `variable` = 'admin_notes_edit' LIMIT 1;", __FILE__, __LINE__);
            }
            $rsNotes = p4c_query("SELECT `value` FROM `settings` WHERE `variable` = 'admin_notes' LIMIT 1;", __FILE__, __LINE__);
            $notesVal = ($rsNotes && p4c_num_rows($rsNotes) > 0) ? self::decodeLegacyText((string)(p4c_fetch_object($rsNotes)->value ?? '')) : '';

            Response::json([
                'status'           => 'ok',
                'message'          => 'Bearbeitung abgebrochen.',
                'edit_employee_id' => 0,
                'admin_notes'      => $notesVal,
            ]);
            return;
        }

        if ($action === 'save') {
            if ($currentEdit !== $employeeId && $currentEdit !== 0) {
                Response::error('Du besitzt aktuell nicht die Bearbeitungssperre für die gemeinsamen Notizen.', 409);
                return;
            }
            $newNotes = (string)$this->request->post('admin_notes', '');
            p4c_query("UPDATE `settings` SET `value` = '" . p4c_escape_string($newNotes) . "' WHERE `variable` = 'admin_notes' LIMIT 1;", __FILE__, __LINE__);
            p4c_query("UPDATE `settings` SET `value` = '0' WHERE `variable` = 'admin_notes_edit' LIMIT 1;", __FILE__, __LINE__);
            log_action('Gemeinsame Admin-Notizen aktualisiert.');

            Response::json([
                'status'           => 'ok',
                'message'          => 'Gemeinsame Notizen wurden erfolgreich gespeichert.',
                'edit_employee_id' => 0,
                'admin_notes'      => $newNotes,
            ]);
            return;
        }

        Response::error('Unbekannte Aktion.', 400);
    }

    /**
     * AJAX-Endpunkt zum Speichern der persönlichen Mitarbeiter-Notizen
     */
    public function saveEmployeeNotes(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        $csrf = (string)$this->request->post('_csrf', $this->request->header('X-CSRF-Token'));
        if (!Csrf::validate($csrf)) {
            Response::error('Dein Sicherheitstoken ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $employeeId = isset($_SESSION['employee_id']) ? abs((int)$_SESSION['employee_id']) : 0;
        if ($employeeId <= 0) {
            Response::error('Nicht eingeloggt.', 401);
            return;
        }

        $notes = (string)$this->request->post('employee_notes', '');
        p4c_query(
            "UPDATE `employee` SET `notes` = '" . p4c_escape_string($notes) . "' WHERE `id` = '" . $employeeId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );

        Response::json([
            'status'  => 'ok',
            'message' => 'Deine persönlichen Notizen wurden gespeichert.',
        ]);
    }

    /**
     * Globale Admin-Schnellsuche über Darsteller (actors), P4C-Merchants (merchants), Filme (movies) und Webseiten (sites)
     */
    public function globalSearch(): void
    {
        $q       = trim((string)$this->request->get('q', ''));
        $rawNum  = ltrim($q, '#');
        $isNumId = ($rawNum !== '' && ctype_digit($rawNum));
        if (mb_strlen($q) < 2 && !$isNumId) {
            Response::json(['status' => 'ok', 'items' => []]);
            return;
        }

        $cleanId = $isNumId ? abs((int)$rawNum) : 0;
        $escQ    = p4c_escape_string($isNumId ? $rawNum : $q);
        $items   = [];

        // 1. Darsteller-Profile (actors)
        if (function_exists('acp_has_access') && acp_has_access('actors')) {
            $whereActor = "`username` LIKE '%" . $escQ . "%'";
            if ($cleanId > 0) {
                $whereActor = "(`id` = '" . $cleanId . "' OR " . $whereActor . ")";
            }
            $rsActors = p4c_query(
                "SELECT `id`, `username`, `status`, `merchant_id`
                 FROM `actors`
                 WHERE `status` != 'deleted' AND " . $whereActor . "
                 ORDER BY `id` DESC
                 LIMIT 6;",
                __FILE__,
                __LINE__
            );
            if ($rsActors && p4c_num_rows($rsActors) > 0) {
                while ($a = p4c_fetch_object($rsActors)) {
                    $aid = abs((int)$a->id);
                    $items[] = [
                        'type'         => 'actor',
                        'type_label'   => 'Profil',
                        'icon'         => 'bi-person-bounding-box',
                        'id'           => $aid,
                        'title'        => self::decodeLegacyText((string)$a->username),
                        'subtitle'     => 'Merchant #' . abs((int)$a->merchant_id),
                        'status_label' => (string)$a->status,
                        'route'        => '#/actor?id=' . $aid,
                    ];
                }
            }
        }

        // 2. P4C-Merchants (merchants)
        if (function_exists('acp_has_access') && acp_has_access('merchants')) {
            $whereMerchant = "`partner_id` LIKE '%" . $escQ . "%'";
            if ($cleanId > 0) {
                $whereMerchant = "(`id` = '" . $cleanId . "' OR " . $whereMerchant . ")";
            }
            if (defined('AES_KEY')) {
                $whereMerchant .= " OR CONVERT(AES_DECRYPT(`username`, '" . p4c_escape_string((string)AES_KEY) . "') USING utf8mb4) LIKE '%" . $escQ . "%'";
            }
            $rsMerchants = p4c_query(
                "SELECT `id`, `partner_id`,
                        CONVERT(AES_DECRYPT(`username`, '" . p4c_escape_string((string)AES_KEY) . "') USING utf8mb4) AS `dec_username`
                 FROM `merchants`
                 WHERE " . $whereMerchant . "
                 ORDER BY `id` DESC
                 LIMIT 5;",
                __FILE__,
                __LINE__
            );
            if ($rsMerchants && p4c_num_rows($rsMerchants) > 0) {
                while ($m = p4c_fetch_object($rsMerchants)) {
                    $mid = abs((int)$m->id);
                    $decUser = trim((string)($m->dec_username ?? ''));
                    $items[] = [
                        'type'         => 'merchant',
                        'type_label'   => 'Merchant',
                        'icon'         => 'bi-briefcase-fill',
                        'id'           => $mid,
                        'title'        => $decUser !== '' ? $decUser : ('Merchant #' . $mid),
                        'subtitle'     => 'P4C-ID: ' . (string)($m->partner_id ?? '-'),
                        'status_label' => 'Aktiv',
                        'route'        => '#/merchant?id=' . $mid,
                    ];
                }
            }
        }

        // 3. Filme (movies)
        if (function_exists('acp_has_access') && (acp_has_access('content_online') || acp_has_access('content_check'))) {
            $whereMovie = "(`title` LIKE '%" . $escQ . "%' OR `file_id` LIKE '%" . $escQ . "%')";
            if ($cleanId > 0) {
                $whereMovie = "(`id` = '" . $cleanId . "' OR " . $whereMovie . ")";
            }
            $rsMovies = p4c_query(
                "SELECT `id`, `title`, `file_id`, `movie_checked`, `status`
                 FROM `movies`
                 WHERE `status` != 'deleted' AND " . $whereMovie . "
                 ORDER BY `id` DESC
                 LIMIT 6;",
                __FILE__,
                __LINE__
            );
            if ($rsMovies && p4c_num_rows($rsMovies) > 0) {
                while ($mv = p4c_fetch_object($rsMovies)) {
                    $mvid      = abs((int)$mv->id);
                    $unchecked = ((string)($mv->movie_checked ?? '') === '0000-00-00 00:00:00' || (string)($mv->movie_checked ?? '') === '000-00-00 00:00:00');
                    $items[] = [
                        'type'         => 'movie',
                        'type_label'   => 'Film',
                        'icon'         => 'bi-film',
                        'id'           => $mvid,
                        'title'        => self::decodeLegacyText((string)($mv->title ?? ('Film #' . $mvid))),
                        'subtitle'     => 'File-ID: ' . (string)($mv->file_id ?? '-'),
                        'status_label' => $unchecked ? 'In Prüfung' : (string)($mv->status ?? 'online'),
                        'route'        => $unchecked ? ('#/movie-checking?id=' . $mvid) : ('#/movie-edit?id=' . $mvid),
                    ];
                }
            }
        }

        // 4. Webseiten (sites)
        if (function_exists('acp_has_access') && acp_has_access('websites')) {
            $whereSite = "`domain` LIKE '%" . $escQ . "%'";
            if ($cleanId > 0) {
                $whereSite = "(`id` = '" . $cleanId . "' OR `p4c_shop_id` = '" . $cleanId . "' OR `partner_id` = '" . $cleanId . "' OR " . $whereSite . ")";
            }
            $rsSites = p4c_query(
                "SELECT `id`, `domain`, `partner_id`, `p4c_shop_id`, `status`
                 FROM `sites`
                 WHERE " . $whereSite . "
                 ORDER BY `id` DESC
                 LIMIT 5;",
                __FILE__,
                __LINE__
            );
            if ($rsSites && p4c_num_rows($rsSites) > 0) {
                while ($s = p4c_fetch_object($rsSites)) {
                    $sid = abs((int)$s->id);
                    $items[] = [
                        'type'         => 'site',
                        'type_label'   => 'Webseite',
                        'icon'         => 'bi-globe2',
                        'id'           => $sid,
                        'title'        => self::decodeLegacyText((string)($s->domain ?? ('Site #' . $sid))),
                        'subtitle'     => 'Shop #' . abs((int)($s->p4c_shop_id ?? 0)) . ' • Partner-ID: ' . (string)($s->partner_id ?: '-'),
                        'status_label' => ((int)($s->status ?? 0) === 1 ? 'Aktiv' : 'Inaktiv'),
                        'route'        => '#/site?id=' . $sid,
                    ];
                }
            }
        }

        Response::json([
            'status' => 'ok',
            'items'  => $items,
        ]);
    }

    /**
     * Liefert aktuelle Sidebar-Zähler als JSON
     */
    public function sidebarCounts(): void
    {
        Response::json([
            'status' => 'ok',
            'counts' => $this->fetchSystemCounts(),
        ]);
    }

    /**
     * Berechnet die aktuellen System-Zähler für Dashboard & Sidebar
     *
     * @return array<string, int>
     */
    private function fetchSystemCounts(): array
    {
        $rsMoviesChecking = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `movie_checked`='0000-00-00 00:00:00' AND `released`='1' AND `convert_status` > '1';", __FILE__, __LINE__);
        $moviesChecking = $rsMoviesChecking ? abs((int)(p4c_fetch_object($rsMoviesChecking)->cnt ?? 0)) : 0;

        $rsMoviesBlocked = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `convert_status` > '1' AND (`released`='2' OR `status`!='active');", __FILE__, __LINE__);
        $moviesBlocked = $rsMoviesBlocked ? abs((int)(p4c_fetch_object($rsMoviesBlocked)->cnt ?? 0)) : 0;

        $rsPhotoAlbumsChecking = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE `album_checked`='0000-00-00 00:00:00' AND `released`='1';", __FILE__, __LINE__);
        $photoAlbumsChecking = $rsPhotoAlbumsChecking ? abs((int)(p4c_fetch_object($rsPhotoAlbumsChecking)->cnt ?? 0)) : 0;

        $coActorsChecking = 0;
        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
            $rsCoActors = p4c_query("SELECT COUNT(*) AS `cnt` FROM `co_actors` WHERE `status` != 'deleted' AND `checked_status`='under_review';", __FILE__, __LINE__);
            $coActorsChecking = $rsCoActors ? abs((int)(p4c_fetch_object($rsCoActors)->cnt ?? 0)) : 0;
        }

        $rsMoviesOnline = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies_online`;", __FILE__, __LINE__);
        $moviesOnline = $rsMoviesOnline ? abs((int)(p4c_fetch_object($rsMoviesOnline)->cnt ?? 0)) : 0;

        $rsMoviesDrafts = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `released`='0' AND `status`!='deleted';", __FILE__, __LINE__);
        $moviesDrafts = $rsMoviesDrafts ? abs((int)(p4c_fetch_object($rsMoviesDrafts)->cnt ?? 0)) : 0;

        $rsPhotoAlbumsOnline = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_online`;", __FILE__, __LINE__);
        $photoAlbumsOnline = $rsPhotoAlbumsOnline ? abs((int)(p4c_fetch_object($rsPhotoAlbumsOnline)->cnt ?? 0)) : 0;

        $rsNewBanners = p4c_query("SELECT COUNT(*) AS `cnt` FROM `ads_media` WHERE `new_filename`!='' AND `rejected`='0';", __FILE__, __LINE__);
        $newBanners = $rsNewBanners ? abs((int)(p4c_fetch_object($rsNewBanners)->cnt ?? 0)) : 0;

        $rsActorCams = p4c_query("SELECT COUNT(*) AS `cnt` FROM `actor_cams` WHERE `datetime` >= '" . date('Y-m-d H:i:s', strtotime('-80 seconds')) . "';", __FILE__, __LINE__);
        $activeWebcams = $rsActorCams ? abs((int)(p4c_fetch_object($rsActorCams)->cnt ?? 0)) : 0;

        $rsNewActors = p4c_query("SELECT COUNT(*) AS `cnt` FROM `actors` WHERE `created_datetime` >= '" . date('Y-m-d H:i:s', strtotime('-5 days')) . "' AND `status`='inactive';", __FILE__, __LINE__);
        $newActors = $rsNewActors ? abs((int)(p4c_fetch_object($rsNewActors)->cnt ?? 0)) : 0;

        return [
            'movies_checking'       => $moviesChecking,
            'photo_albums_checking' => $photoAlbumsChecking,
            'co_actors_checking'    => $coActorsChecking,
            'movies_blocked'        => $moviesBlocked,
            'content_checking'      => $moviesChecking + $photoAlbumsChecking + $coActorsChecking,
            'movies_online'         => $moviesOnline,
            'movies_drafts'         => $moviesDrafts,
            'photo_albums_online'   => $photoAlbumsOnline,
            'content_online'        => $moviesOnline + $photoAlbumsOnline,
            'new_banners'           => $newBanners,
            'active_webcams'        => $activeWebcams,
            'new_actors'            => $newActors,
        ];
    }

    /**
     * Konvertiert eventuelle doppelt-kodierte oder ISO-8859-1 Alt-Strings sauber nach UTF-8 (PHP 8.4 kompatibel)
     */
    public static function decodeLegacyText(string $text): string
    {
        if ($text === '') {
            return '';
        }
        // Falls durch früheres utf8_encode() im ISO-Kontext typische Artefakte (Ã¤, Ã¶, Ã¼, ÃŸ) vorliegen:
        if (preg_match('/Ã[¤¶¼„–œŸ]/u', $text) === 1 && function_exists('mb_convert_encoding')) {
            $decoded = @mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
            if (is_string($decoded) && $decoded !== '' && mb_check_encoding($decoded, 'UTF-8')) {
                return $decoded;
            }
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
            return (string)@mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        return $text;
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\Employees;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * EroCloud ACP (v2.0) – Modul: Mitarbeiter, Administratoren & Moderatoren (#/employees & #/employee?id=...)
 *
 * Funktionen:
 *  - Übersicht aller ACP-Team-Mitglieder (employee) mit 4 KPI-Kacheln, Rollen-/Bereichsfilterung
 *    und gebündelter Vorab-Aggregation aller Freigaben & Audit-Log-Aktivitäten (0 N+1 Queries).
 *  - Neuanlage (#/employee?id=0) & Bearbeitung (#/employee?id=...) inkl. Passwort-Hashing (hash_password),
 *    System-Rolle (write = Administrator / read = Moderator), 1-Klick-Rollen-Vorlagen (Presets)
 *    und granularer ACP-Bereichs-Matrix (access_area).
 *  - Vollständige Moderations-Bilanz (geprüfte Filme, Fotoalben, 18+ Ausweise) & Login-/Aktivitäts-Chronik (employee_history).
 *  - Selbstschutz-Logik (eigener Account kann nicht gelöscht oder von Admin-Rechten ausgesperrt werden;
 *    Live-Session- & Cookie-Synchronisation bei Änderung des eigenen Accounts).
 */
final class EmployeesController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Definition aller verfügbaren ACP-Bereiche (access_area) inkl. Beschreibung & Icons
     *
     * @return array<string, array{key: string, title: string, icon: string, badge_color: string, description: string, sub_items: string}>
     */
    public static function getAvailableAccessAreas(): array
    {
        return [
            'content_check' => [
                'key'         => 'content_check',
                'title'       => 'Content prüfen',
                'icon'        => 'bi-shield-check',
                'badge_color' => 'primary',
                'description' => 'Warteschlangen für neue Uploads, 18+ Ausweise & Compliance-Prüfung',
                'sub_items'   => 'Filme prüfen, Fotoalben prüfen, Drehpartner & ID-Check, Filme abgelehnt (KI-Analyse & BRAM nur für Admins)',
            ],
            'content_online' => [
                'key'         => 'content_online',
                'title'       => 'Content Online',
                'icon'        => 'bi-cloud-check-fill',
                'badge_color' => 'success',
                'description' => 'Veröffentlichter Katalog & geplante Entwürfe',
                'sub_items'   => 'Filme online, Filme in Planung, Fotoalben online (Content-Bereinigung & Speicher/Traffic nur für Admins)',
            ],
            'actors' => [
                'key'         => 'actors',
                'title'       => 'Darsteller-Profile',
                'icon'        => 'bi-person-bounding-box',
                'badge_color' => 'info',
                'description' => 'Verwaltung aller Creator-/Darsteller-Profile, Steckbriefe & Provisionen',
                'sub_items'   => 'Profile-Übersicht (#/actors), Profil-Bearbeitung (#/actor), Profilbild-Moderation',
            ],
            'merchants' => [
                'key'         => 'merchants',
                'title'       => 'P4C-Merchants (Händler)',
                'icon'        => 'bi-briefcase-fill',
                'badge_color' => 'primary',
                'description' => 'Pay4Coins-Händlerkonten, Richtlinien-Status & Profil-Limits',
                'sub_items'   => 'Händler-Übersicht (#/merchants), Händler-Stammdaten & Login-Historie (#/merchant)',
            ],
            'users' => [
                'key'         => 'users',
                'title'       => 'Cloud-User (Mitglieder)',
                'icon'        => 'bi-people-fill',
                'badge_color' => 'info',
                'description' => 'Endkunden-Accounts, Coin-Guthaben, Content-Käufe & Konto-Sperren',
                'sub_items'   => 'Cloud-User-Liste (#/users), Mitglieder-Details & Kaufhistorie (#/user)',
            ],
            'websites' => [
                'key'         => 'websites',
                'title'       => 'Webseiten & Partner-Shops',
                'icon'        => 'bi-globe2',
                'badge_color' => 'secondary',
                'description' => 'Angeschlossene EroCMS-/Partner-Domains & Pay4Coins-Synchronisation',
                'sub_items'   => 'Webseiten-Übersicht (#/sites), Shop-Details & Verkäufe (#/site)',
            ],
            'affiliate_program' => [
                'key'         => 'affiliate_program',
                'title'       => 'Partnerprogramm / EroAds',
                'icon'        => 'bi-megaphone-fill',
                'badge_color' => 'warning',
                'description' => 'Webmaster-Umsätze, Werbebanner-Verwaltung & Banner-Freigabe',
                'sub_items'   => 'Aktive Webmaster (#/webmaster-partner), Aktive Banner (#/banners), Neue Banner (#/banners-new)',
            ],
            'webcams' => [
                'key'         => 'webcams',
                'title'       => 'Webcams',
                'icon'        => 'bi-camera-video-fill',
                'badge_color' => 'danger',
                'description' => 'Überwachung aktiver Live-Cam-Streams',
                'sub_items'   => 'Webcam-Übersicht (#/webcams)',
            ],
            'point_system' => [
                'key'         => 'point_system',
                'title'       => 'Punktesystem',
                'icon'        => 'bi-star-fill',
                'badge_color' => 'warning',
                'description' => 'Creator-Belohnungspunkte & Aktivitäts-Prämien',
                'sub_items'   => 'Punktesystem (#/pointssystem)',
            ],
            'error_log' => [
                'key'         => 'error_log',
                'title'       => 'System & Technik',
                'icon'        => 'bi-sliders',
                'badge_color' => 'danger',
                'description' => 'Impressum, SMTP-E-Mail-Labor & zentrales PHP-Error-Log',
                'sub_items'   => 'Impressum & E-Mail (#/system-settings), Error-Log (#/errors)',
            ],
            'employees' => [
                'key'         => 'employees',
                'title'       => 'Mitarbeiter & Admins',
                'icon'        => 'bi-person-gear',
                'badge_color' => 'dark',
                'description' => 'Anlegen und Bearbeiten von Administratoren, Moderatoren & Zugriffsrechten',
                'sub_items'   => 'Team-Übersicht (#/employees), Mitarbeiter anlegen & bearbeiten (#/employee)',
            ],
        ];
    }

    /**
     * Vorkonfigurierte Rollen-Vorlagen (Presets) für die 1-Klick-Zuweisung im Formular
     *
     * @return array<string, array{label: string, badge: string, icon: string, rule: string, is_all: bool, areas: array<int, string>, desc: string}>
     */
    public static function getRolePresets(): array
    {
        return [
            'super_admin' => [
                'label' => 'Super-Admin (Vollzugriff)',
                'badge' => 'primary',
                'icon'  => 'bi-shield-fill-check',
                'rule'  => 'write',
                'is_all'=> true,
                'areas' => ['all'],
                'desc'  => 'Uneingeschränkter Vollzugriff auf alle ACP-Module, Systemeinstellungen, Löschfunktionen und Mitarbeiterverwaltung.',
            ],
            'content_lead' => [
                'label' => 'Senior-Moderator (Prüfung & Löschrecht)',
                'badge' => 'success',
                'icon'  => 'bi-check2-circle',
                'rule'  => 'write',
                'is_all'=> false,
                'areas' => ['content_check', 'content_online', 'actors', 'merchants'],
                'desc'  => 'Prüft und bearbeitet Filme, Fotoalben, 18+ Ausweise, Profile & Händler inkl. Berechtigung zum endgültigen Löschen.',
            ],
            'content_moderator' => [
                'label' => 'Content-Moderator / Prüfer (Standard)',
                'badge' => 'info',
                'icon'  => 'bi-film',
                'rule'  => 'read',
                'is_all'=> false,
                'areas' => ['content_check', 'content_online'],
                'desc'  => 'Fokus auf tägliche Freigabe & Prüfung von Filmen, Fotoalben und 18+ Drehpartner-Ausweisen (ohne endgültiges Löschen).',
            ],
            'support_manager' => [
                'label' => 'Support, Profile & User-Betreuung',
                'badge' => 'secondary',
                'icon'  => 'bi-headset',
                'rule'  => 'read',
                'is_all'=> false,
                'areas' => ['actors', 'merchants', 'users', 'websites', 'affiliate_program'],
                'desc'  => 'Betreuung von Darsteller-Profilen, Händlern, Cloud-Usern, Partner-Shops und Werbebannern.',
            ],
        ];
    }

    /**
     * Konvertiert potenziell ISO-8859-1-codierte DB-Strings sicher nach UTF-8
     */
    private function toUtf8(?string $str): string
    {
        $val = (string)($str ?? '');
        if ($val === '') {
            return '';
        }
        if (preg_match('/Ã[¤¶¼„–œŸ]/u', $val) === 1 && function_exists('mb_convert_encoding')) {
            $decoded = @mb_convert_encoding($val, 'ISO-8859-1', 'UTF-8');
            if (is_string($decoded) && $decoded !== '' && mb_check_encoding($decoded, 'UTF-8')) {
                return $decoded;
            }
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($val, 'UTF-8')) {
            return (string)@mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
        }
        return $val;
    }

    /**
     * Formatiert ein MySQL-Datetime ins deutsche Format
     */
    private function formatDateTimeDe(?string $dt, bool $withSeconds = false): string
    {
        $raw = trim((string)($dt ?? ''));
        if ($raw === '' || str_starts_with($raw, '0000-00-00') || str_starts_with($raw, '000-00-00')) {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts === false || $ts <= 0) {
            return $raw;
        }
        return date($withSeconds ? 'd.m.Y H:i:s' : 'd.m.Y H:i', $ts);
    }

    /**
     * Parst die Spalte access_area in ein sauberes Array
     *
     * @return array<int, string>
     */
    private function parseAccessAreas(string $rawAccessArea): array
    {
        $parts = array_filter(array_map('trim', explode(',', $rawAccessArea)), static fn(string $v): bool => $v !== '');
        return array_values(array_unique($parts));
    }

    // =========================================================================
    // 1. Gesamtübersicht aller Mitarbeiter, Admins & Moderatoren (GET ?route=employees)
    // =========================================================================
    public function list(): string
    {
        $ruleFilter   = trim((string)$this->request->get('rule', 'all'));
        $accessFilter = trim((string)$this->request->get('access', 'all'));
        $searchQuery  = trim((string)$this->request->get('q', ''));
        $sortBy       = trim((string)$this->request->get('sort', 'id_asc'));

        $currentEmployeeId   = abs((int)($_SESSION['employee_id'] ?? 0));
        $currentEmployeeRule = strtolower(trim((string)($_SESSION['employee_rule'] ?? 'write')));
        $impersonator        = isset($_SESSION['impersonator_employee']) && is_array($_SESSION['impersonator_employee'])
            ? $_SESSION['impersonator_employee']
            : null;
        $canImpersonate      = ($currentEmployeeRule === 'write')
            || (is_array($impersonator) && strtolower((string)($impersonator['rule'] ?? '')) === 'write');
        $availableAreas      = self::getAvailableAccessAreas();

        // 1. Gebündelte Vorab-Aggregation der Aktivitäts- & Moderations-Kennzahlen (0 N+1 Queries!)
        $historyStats = [];
        $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

        $rsHist = p4c_query(
            "SELECT
                `employee_id`,
                COUNT(*) AS `total_actions`,
                SUM(CASE WHEN `datetime` >= '" . p4c_escape_string($thirtyDaysAgo) . "' THEN 1 ELSE 0 END) AS `actions_30d`,
                SUM(CASE WHEN `datetime` >= '" . p4c_escape_string($sevenDaysAgo) . "' THEN 1 ELSE 0 END) AS `actions_7d`,
                SUM(CASE WHEN `description` = 'Am System angemeldet.' THEN 1 ELSE 0 END) AS `login_count`,
                MAX(`datetime`) AS `last_action_dt`,
                MAX(CASE WHEN `description` = 'Am System angemeldet.' THEN `datetime` ELSE NULL END) AS `last_login_dt`
             FROM `employee_history`
             WHERE `employee_id` > 0
             GROUP BY `employee_id`;",
            __FILE__,
            __LINE__
        );
        if ($rsHist) {
            while ($hRow = p4c_fetch_object($rsHist)) {
                $eid = abs((int)$hRow->employee_id);
                $historyStats[$eid] = [
                    'total_actions'  => (int)($hRow->total_actions ?? 0),
                    'actions_30d'    => (int)($hRow->actions_30d ?? 0),
                    'actions_7d'     => (int)($hRow->actions_7d ?? 0),
                    'login_count'    => (int)($hRow->login_count ?? 0),
                    'last_action_dt' => (string)($hRow->last_action_dt ?? ''),
                    'last_login_dt'  => (string)($hRow->last_login_dt ?? ''),
                    'last_ip'        => '',
                    'last_desc'      => '',
                ];
            }
        }

        // Letzten Eintrag pro Mitarbeiter (für IP & Beschreibung) in 1 Query laden
        $rsLastHist = p4c_query(
            "SELECT h.`employee_id`, h.`ip`, h.`description`, h.`datetime`
             FROM `employee_history` h
             INNER JOIN (
                 SELECT `employee_id`, MAX(`id`) AS `max_id`
                 FROM `employee_history`
                 WHERE `employee_id` > 0
                 GROUP BY `employee_id`
             ) latest ON latest.`max_id` = h.`id`;",
            __FILE__,
            __LINE__
        );
        if ($rsLastHist) {
            while ($lRow = p4c_fetch_object($rsLastHist)) {
                $eid = abs((int)$lRow->employee_id);
                if (isset($historyStats[$eid])) {
                    $historyStats[$eid]['last_ip']   = (string)($lRow->ip ?? '');
                    $historyStats[$eid]['last_desc'] = $this->toUtf8((string)($lRow->description ?? ''));
                }
            }
        }

        // 2. Freigegebene Filme pro Mitarbeiter (movies.released_from)
        $moviesReleasedByEmp = [];
        $rsMovRel = p4c_query(
            "SELECT `released_from` AS `emp_id`, COUNT(*) AS `cnt`
             FROM `movies`
             WHERE `released_from` > 0
             GROUP BY `released_from`;",
            __FILE__,
            __LINE__
        );
        if ($rsMovRel) {
            while ($mRow = p4c_fetch_object($rsMovRel)) {
                $moviesReleasedByEmp[abs((int)$mRow->emp_id)] = (int)($mRow->cnt ?? 0);
            }
        }

        // 3. Freigegebene Fotoalben pro Mitarbeiter (photo_albums.released_from)
        $albumsReleasedByEmp = [];
        $rsAlbRel = p4c_query(
            "SELECT `released_from` AS `emp_id`, COUNT(*) AS `cnt`
             FROM `photo_albums`
             WHERE `released_from` > 0
             GROUP BY `released_from`;",
            __FILE__,
            __LINE__
        );
        if ($rsAlbRel) {
            while ($aRow = p4c_fetch_object($rsAlbRel)) {
                $albumsReleasedByEmp[abs((int)$aRow->emp_id)] = (int)($aRow->cnt ?? 0);
            }
        }

        // 4. Geprüfte 18+ Drehpartner / Ausweise pro Mitarbeiter (co_actors.checked_by_employee)
        $coActorsCheckedByEmp = [];
        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
        }
        $rsCoAct = @p4c_query(
            "SELECT `checked_by_employee` AS `emp_id`, COUNT(*) AS `cnt`
             FROM `co_actors`
             WHERE `checked_by_employee` > 0
             GROUP BY `checked_by_employee`;",
            __FILE__,
            __LINE__
        );
        if ($rsCoAct) {
            while ($cRow = p4c_fetch_object($rsCoAct)) {
                $coActorsCheckedByEmp[abs((int)$cRow->emp_id)] = (int)($cRow->cnt ?? 0);
            }
        }

        // 5. Alle Mitarbeiter aus `employee` laden
        $rsEmployees = p4c_query(
            "SELECT `id`, `username`, `notes`, `rule`, `access_area`
             FROM `employee`
             ORDER BY `id` ASC;",
            __FILE__,
            __LINE__
        );

        $allEmployees = [];
        $kpi = [
            'total_employees'     => 0,
            'admin_write_count'   => 0,
            'mod_read_count'      => 0,
            'full_access_count'   => 0,
            'custom_access_count' => 0,
            'total_reviews'       => array_sum($moviesReleasedByEmp) + array_sum($albumsReleasedByEmp) + array_sum($coActorsCheckedByEmp),
            'movies_reviewed'     => array_sum($moviesReleasedByEmp),
            'albums_reviewed'     => array_sum($albumsReleasedByEmp),
            'coactors_reviewed'   => array_sum($coActorsCheckedByEmp),
            'total_history_logs'  => 0,
            'actions_last_7d'     => 0,
        ];

        foreach ($historyStats as $hs) {
            $kpi['total_history_logs'] += $hs['total_actions'];
            $kpi['actions_last_7d']    += $hs['actions_7d'];
        }

        if ($rsEmployees) {
            while ($row = p4c_fetch_object($rsEmployees)) {
                $eid       = abs((int)$row->id);
                $username  = $this->toUtf8((string)($row->username ?? ''));
                $rule      = strtolower(trim((string)($row->rule ?? 'read'))) === 'write' ? 'write' : 'read';
                $rawAccess = trim((string)($row->access_area ?? ''));
                $areas     = $this->parseAccessAreas($rawAccess);
                $isAll     = in_array('all', $areas, true);
                $notes     = $this->toUtf8((string)($row->notes ?? ''));

                $kpi['total_employees']++;
                if ($rule === 'write') {
                    $kpi['admin_write_count']++;
                } else {
                    $kpi['mod_read_count']++;
                }
                if ($isAll) {
                    $kpi['full_access_count']++;
                } else {
                    $kpi['custom_access_count']++;
                }

                $hStat       = $historyStats[$eid] ?? null;
                $movCnt      = $moviesReleasedByEmp[$eid] ?? 0;
                $albCnt      = $albumsReleasedByEmp[$eid] ?? 0;
                $coActCnt    = $coActorsCheckedByEmp[$eid] ?? 0;
                $totalChecks = $movCnt + $albCnt + $coActCnt;

                $lastActRaw  = (string)($hStat['last_action_dt'] ?? '');
                $lastLogRaw  = (string)($hStat['last_login_dt'] ?? '');

                $areaBadges = [];
                if ($isAll) {
                    $areaBadges[] = [
                        'key'   => 'all',
                        'title' => 'Vollzugriff (Alle Bereiche)',
                        'icon'  => 'bi-shield-fill-check',
                        'color' => 'primary',
                    ];
                } else {
                    foreach ($areas as $aKey) {
                        if (isset($availableAreas[$aKey])) {
                            $areaBadges[] = [
                                'key'   => $aKey,
                                'title' => $availableAreas[$aKey]['title'],
                                'icon'  => $availableAreas[$aKey]['icon'],
                                'color' => $availableAreas[$aKey]['badge_color'],
                            ];
                        } else {
                            $areaBadges[] = [
                                'key'   => $aKey,
                                'title' => $aKey,
                                'icon'  => 'bi-key',
                                'color' => 'secondary',
                            ];
                        }
                    }
                }

                $initials = mb_strtoupper(mb_substr($username !== '' ? $username : 'AD', 0, 2));

                $allEmployees[] = [
                    'id'              => $eid,
                    'username'        => $username,
                    'initials'        => $initials,
                    'is_self'         => ($eid === $currentEmployeeId),
                    'rule'            => $rule,
                    'rule_label'      => $rule === 'write' ? 'Administrator (Schreib-/Löschrecht)' : 'Moderator / Prüfer (Standard)',
                    'is_all_access'   => $isAll,
                    'access_raw'      => $rawAccess,
                    'access_areas'    => $areas,
                    'area_badges'     => $areaBadges,
                    'notes'           => $notes,
                    'movies_cnt'      => $movCnt,
                    'albums_cnt'      => $albCnt,
                    'coactors_cnt'    => $coActCnt,
                    'total_checks'    => $totalChecks,
                    'total_actions'   => (int)($hStat['total_actions'] ?? 0),
                    'actions_30d'     => (int)($hStat['actions_30d'] ?? 0),
                    'login_count'     => (int)($hStat['login_count'] ?? 0),
                    'last_action_raw' => $lastActRaw,
                    'last_action_de'  => $this->formatDateTimeDe($lastActRaw),
                    'last_login_de'   => $this->formatDateTimeDe($lastLogRaw),
                    'last_ip'         => (string)($hStat['last_ip'] ?? ''),
                    'last_desc'       => (string)($hStat['last_desc'] ?? ''),
                ];
            }
        }

        // 6. Filterung anwenden
        $filtered = array_filter($allEmployees, static function (array $emp) use ($ruleFilter, $accessFilter, $searchQuery): bool {
            if ($ruleFilter === 'write' && $emp['rule'] !== 'write') {
                return false;
            }
            if ($ruleFilter === 'read' && $emp['rule'] !== 'read') {
                return false;
            }
            if ($accessFilter === 'full' && !$emp['is_all_access']) {
                return false;
            }
            if ($accessFilter === 'custom' && $emp['is_all_access']) {
                return false;
            }
            if ($accessFilter !== 'all' && $accessFilter !== 'full' && $accessFilter !== 'custom') {
                if (!$emp['is_all_access'] && !in_array($accessFilter, $emp['access_areas'], true)) {
                    return false;
                }
            }
            if ($searchQuery !== '') {
                $qLower = mb_strtolower($searchQuery);
                $hay = mb_strtolower(
                    '#' . $emp['id'] . ' ' .
                    $emp['username'] . ' ' .
                    $emp['rule_label'] . ' ' .
                    $emp['access_raw'] . ' ' .
                    $emp['notes'] . ' ' .
                    $emp['last_ip']
                );
                if (!str_contains($hay, $qLower)) {
                    return false;
                }
            }
            return true;
        });

        $employees = array_values($filtered);

        // 7. Sortierung
        usort($employees, static function (array $a, array $b) use ($sortBy): int {
            return match ($sortBy) {
                'id_desc'       => $b['id'] <=> $a['id'],
                'username_asc'  => strcasecmp($a['username'], $b['username']),
                'activity_desc' => strcmp($b['last_action_raw'], $a['last_action_raw']),
                'checks_desc'   => $b['total_checks'] <=> $a['total_checks'],
                default         => $a['id'] <=> $b['id'],
            };
        });

        return View::render('Employees', 'list', [
            'kpi'                 => $kpi,
            'employees'           => $employees,
            'availableAreas'      => $availableAreas,
            'rolePresets'         => self::getRolePresets(),
            'currentEmployeeId'   => $currentEmployeeId,
            'currentEmployeeRule' => $currentEmployeeRule,
            'impersonator'        => $impersonator,
            'canImpersonate'      => $canImpersonate,
            'filters'             => [
                'rule'   => $ruleFilter,
                'access' => $accessFilter,
                'q'      => $searchQuery,
                'sort'   => $sortBy,
            ],
        ]);
    }

    // =========================================================================
    // 2. Detail-, Anlage- & Bearbeitungs-Cockpit (GET ?route=employee&id=...)
    // =========================================================================
    public function detail(): string
    {
        $rawId   = trim((string)$this->request->get('id', '0'));
        $isNew   = ($rawId === 'new' || $rawId === '0' || (int)$this->request->get('new', 0) === 1);
        $empId   = $isNew ? 0 : abs((int)$rawId);

        $currentEmployeeId   = abs((int)($_SESSION['employee_id'] ?? 0));
        $currentEmployeeRule = strtolower(trim((string)($_SESSION['employee_rule'] ?? 'write')));
        $impersonator        = isset($_SESSION['impersonator_employee']) && is_array($_SESSION['impersonator_employee'])
            ? $_SESSION['impersonator_employee']
            : null;
        $canImpersonate      = ($currentEmployeeRule === 'write')
            || (is_array($impersonator) && strtolower((string)($impersonator['rule'] ?? '')) === 'write');
        $availableAreas      = self::getAvailableAccessAreas();
        $rolePresets         = self::getRolePresets();

        if ($isNew) {
            $employee = [
                'id'            => 0,
                'is_new'        => true,
                'is_self'       => false,
                'username'      => '',
                'initials'      => 'NE',
                'rule'          => 'read',
                'is_all_access' => false,
                'access_areas'  => ['content_check', 'content_online'],
                'notes'         => '',
                'movies_cnt'    => 0,
                'albums_cnt'    => 0,
                'coactors_cnt'  => 0,
                'total_actions' => 0,
                'login_count'   => 0,
                'last_action_de'=> '-',
                'last_login_de' => '-',
                'last_ip'       => '-',
            ];

            return View::render('Employees', 'detail', [
                'employee'            => $employee,
                'prevId'              => 0,
                'nextId'              => 0,
                'availableAreas'      => $availableAreas,
                'rolePresets'         => $rolePresets,
                'recentMovies'        => [],
                'recentAlbums'        => [],
                'recentCoActors'      => [],
                'historyEntries'      => [],
                'currentEmployeeId'   => $currentEmployeeId,
                'currentEmployeeRule' => $currentEmployeeRule,
                'impersonator'        => $impersonator,
                'canImpersonate'      => $canImpersonate,
            ]);
        }

        // Bestehenden Mitarbeiter laden
        $rsEmp = p4c_query(
            "SELECT `id`, `username`, `notes`, `rule`, `access_area`
             FROM `employee`
             WHERE `id` = '" . $empId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsEmp || p4c_num_rows($rsEmp) === 0) {
            return '<div class="alert alert-danger m-4"><i class="bi bi-x-octagon me-2"></i>Der Mitarbeiter-Account <strong>#' . $empId . '</strong> existiert nicht. <a href="#/employees" class="alert-link">Zurück zur Team-Übersicht</a></div>';
        }

        $eRow      = p4c_fetch_object($rsEmp);
        $username  = $this->toUtf8((string)($eRow->username ?? ''));
        $rule      = strtolower(trim((string)($eRow->rule ?? 'read'))) === 'write' ? 'write' : 'read';
        $rawAccess = trim((string)($eRow->access_area ?? ''));
        $areas     = $this->parseAccessAreas($rawAccess);
        $isAll     = in_array('all', $areas, true);
        $notes     = $this->toUtf8((string)($eRow->notes ?? ''));

        // Vorheriger / Nächster Mitarbeiter
        $prevId = 0;
        $nextId = 0;
        $rsPrev = p4c_query("SELECT `id` FROM `employee` WHERE `id` < '" . $empId . "' ORDER BY `id` DESC LIMIT 1;", __FILE__, __LINE__);
        if ($rsPrev && ($rP = p4c_fetch_object($rsPrev))) {
            $prevId = abs((int)$rP->id);
        }
        $rsNext = p4c_query("SELECT `id` FROM `employee` WHERE `id` > '" . $empId . "' ORDER BY `id` ASC LIMIT 1;", __FILE__, __LINE__);
        if ($rsNext && ($rN = p4c_fetch_object($rsNext))) {
            $nextId = abs((int)$rN->id);
        }

        // KPI-Zähler für diesen Mitarbeiter
        $movCnt   = 0;
        $albCnt   = 0;
        $coActCnt = 0;

        $rsMovCnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` WHERE `released_from` = '" . $empId . "';", __FILE__, __LINE__);
        if ($rsMovCnt && ($rM = p4c_fetch_object($rsMovCnt))) {
            $movCnt = (int)($rM->cnt ?? 0);
        }

        $rsAlbCnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE `released_from` = '" . $empId . "';", __FILE__, __LINE__);
        if ($rsAlbCnt && ($rA = p4c_fetch_object($rsAlbCnt))) {
            $albCnt = (int)($rA->cnt ?? 0);
        }

        if (class_exists('CoActorManager')) {
            \CoActorManager::ensureSchema();
        }
        $rsCoCnt = @p4c_query("SELECT COUNT(*) AS `cnt` FROM `co_actors` WHERE `checked_by_employee` = '" . $empId . "';", __FILE__, __LINE__);
        if ($rsCoCnt && ($rC = p4c_fetch_object($rsCoCnt))) {
            $coActCnt = (int)($rC->cnt ?? 0);
        }

        // Historien-KPIs
        $totalActions = 0;
        $loginCount   = 0;
        $lastActionDe = '-';
        $lastLoginDe  = '-';
        $lastIp       = '-';

        $rsHistKpi = p4c_query(
            "SELECT
                COUNT(*) AS `total_actions`,
                SUM(CASE WHEN `description` = 'Am System angemeldet.' THEN 1 ELSE 0 END) AS `login_count`,
                MAX(`datetime`) AS `last_action_dt`,
                MAX(CASE WHEN `description` = 'Am System angemeldet.' THEN `datetime` ELSE NULL END) AS `last_login_dt`
             FROM `employee_history`
             WHERE `employee_id` = '" . $empId . "';",
            __FILE__,
            __LINE__
        );
        if ($rsHistKpi && ($rHk = p4c_fetch_object($rsHistKpi))) {
            $totalActions = (int)($rHk->total_actions ?? 0);
            $loginCount   = (int)($rHk->login_count ?? 0);
            $lastActionDe = $this->formatDateTimeDe((string)($rHk->last_action_dt ?? ''));
            $lastLoginDe  = $this->formatDateTimeDe((string)($rHk->last_login_dt ?? ''));
        }

        // Letzte 300 Einträge aus employee_history
        $historyEntries = [];
        $rsHistList = p4c_query(
            "SELECT `id`, `description`, `datetime`, `ip`
             FROM `employee_history`
             WHERE `employee_id` = '" . $empId . "'
             ORDER BY `id` DESC
             LIMIT 300;",
            __FILE__,
            __LINE__
        );
        if ($rsHistList) {
            while ($hObj = p4c_fetch_object($rsHistList)) {
                $desc = $this->toUtf8((string)($hObj->description ?? ''));
                $ip   = (string)($hObj->ip ?? '');
                if ($lastIp === '-' && $ip !== '') {
                    $lastIp = $ip;
                }

                $descLower = mb_strtolower($desc);
                $badgeType = 'neutral';
                $badgeLabel = 'Aktion';
                if (str_contains($descLower, 'angemeldet')) {
                    $badgeType = 'success';
                    $badgeLabel = 'Login';
                } elseif (str_contains($descLower, 'abgemeldet')) {
                    $badgeType = 'info';
                    $badgeLabel = 'Logout';
                } elseif (str_contains($descLower, 'error') || str_contains($descLower, 'falsch') || str_contains($descLower, 'abgelehnt') || str_contains($descLower, 'gelöscht')) {
                    $badgeType = 'danger';
                    $badgeLabel = 'Warnung / Löschung';
                } elseif (str_contains($descLower, 'geprüft') || str_contains($descLower, 'freigeschaltet') || str_contains($descLower, 'gespeichert') || str_contains($descLower, 'aktualisiert')) {
                    $badgeType = 'primary';
                    $badgeLabel = 'Moderation';
                }

                $historyEntries[] = [
                    'id'          => abs((int)$hObj->id),
                    'description' => $desc,
                    'datetime_de' => $this->formatDateTimeDe((string)($hObj->datetime ?? ''), true),
                    'ip'          => $ip,
                    'badge_type'  => $badgeType,
                    'badge_label' => $badgeLabel,
                ];
            }
        }

        // Zuletzt durch diesen Mitarbeiter geprüfte/bearbeitete Filme (Tab 2)
        $recentMovies = [];
        $rsRecMov = p4c_query(
            "SELECT
                m.`id`, m.`file_id`, m.`title`, m.`status`, m.`released`,
                m.`released_datetime`, m.`last_updated_datetime`, m.`actor_id`,
                a.`username` AS `actor_name`
             FROM `movies` m
             LEFT JOIN `actors` a ON a.`id` = m.`actor_id`
             WHERE m.`released_from` = '" . $empId . "' OR m.`last_updated_by` = '" . $empId . "'
             ORDER BY COALESCE(NULLIF(m.`last_updated_datetime`, '0000-00-00 00:00:00'), m.`released_datetime`) DESC, m.`id` DESC
             LIMIT 40;",
            __FILE__,
            __LINE__
        );
        if ($rsRecMov) {
            while ($mObj = p4c_fetch_object($rsRecMov)) {
                $dtRaw = (string)($mObj->last_updated_datetime ?? '');
                if ($dtRaw === '' || str_starts_with($dtRaw, '0000-00-00')) {
                    $dtRaw = (string)($mObj->released_datetime ?? '');
                }
                $recentMovies[] = [
                    'id'         => abs((int)$mObj->id),
                    'file_id'    => (string)($mObj->file_id ?? ''),
                    'title'      => $this->toUtf8((string)($mObj->title ?? '')),
                    'actor_id'   => abs((int)($mObj->actor_id ?? 0)),
                    'actor_name' => $this->toUtf8((string)($mObj->actor_name ?? '')),
                    'status'     => (string)($mObj->status ?? 'active'),
                    'released'   => (int)($mObj->released ?? 0),
                    'dt_de'      => $this->formatDateTimeDe($dtRaw),
                ];
            }
        }

        // Zuletzt durch diesen Mitarbeiter geprüfte/bearbeitete Fotoalben (Tab 2)
        $recentAlbums = [];
        $rsRecAlb = p4c_query(
            "SELECT
                pa.`id`, pa.`album_id`, pa.`title`, pa.`number_of_photos`, pa.`status`, pa.`released`,
                pa.`released_datetime`, pa.`last_updated_datetime`, pa.`actor_id`,
                a.`username` AS `actor_name`
             FROM `photo_albums` pa
             LEFT JOIN `actors` a ON a.`id` = pa.`actor_id`
             WHERE pa.`released_from` = '" . $empId . "' OR pa.`last_updated_by` = '" . $empId . "'
             ORDER BY COALESCE(NULLIF(pa.`last_updated_datetime`, '0000-00-00 00:00:00'), pa.`released_datetime`) DESC, pa.`id` DESC
             LIMIT 30;",
            __FILE__,
            __LINE__
        );
        if ($rsRecAlb) {
            while ($aObj = p4c_fetch_object($rsRecAlb)) {
                $dtRaw = (string)($aObj->last_updated_datetime ?? '');
                if ($dtRaw === '' || str_starts_with($dtRaw, '0000-00-00')) {
                    $dtRaw = (string)($aObj->released_datetime ?? '');
                }
                $recentAlbums[] = [
                    'id'               => abs((int)$aObj->id),
                    'album_id'         => (string)($aObj->album_id ?? ''),
                    'title'            => $this->toUtf8((string)($aObj->title ?? '')),
                    'number_of_photos' => abs((int)($aObj->number_of_photos ?? 0)),
                    'actor_id'         => abs((int)($aObj->actor_id ?? 0)),
                    'actor_name'       => $this->toUtf8((string)($aObj->actor_name ?? '')),
                    'status'           => (string)($aObj->status ?? 'active'),
                    'released'         => (int)($aObj->released ?? 0),
                    'dt_de'            => $this->formatDateTimeDe($dtRaw),
                ];
            }
        }

        // Zuletzt durch diesen Mitarbeiter geprüfte 18+ Drehpartner (Tab 2)
        $recentCoActors = [];
        $rsRecCo = @p4c_query(
            "SELECT
                c.`id`, c.`merchant_id`, c.`is_owner`, c.`creator_name`,
                c.`checked_status`, c.`checked_on`, c.`id_doc_type`
             FROM `co_actors` c
             WHERE c.`checked_by_employee` = '" . $empId . "' AND c.`status` != 'deleted'
             ORDER BY c.`checked_on` DESC, c.`id` DESC
             LIMIT 30;",
            __FILE__,
            __LINE__
        );
        if ($rsRecCo) {
            while ($cObj = p4c_fetch_object($rsRecCo)) {
                $recentCoActors[] = [
                    'id'             => abs((int)$cObj->id),
                    'merchant_id'    => abs((int)($cObj->merchant_id ?? 0)),
                    'is_owner'       => (int)($cObj->is_owner ?? 0) === 1,
                    'creator_name'   => $this->toUtf8((string)($cObj->creator_name ?? '')),
                    'checked_status' => (string)($cObj->checked_status ?? ''),
                    'id_doc_type'    => (string)($cObj->id_doc_type ?? ''),
                    'checked_on_de'  => $this->formatDateTimeDe((string)($cObj->checked_on ?? '')),
                ];
            }
        }

        $initials = mb_strtoupper(mb_substr($username !== '' ? $username : 'AD', 0, 2));

        $employee = [
            'id'             => $empId,
            'is_new'         => false,
            'is_self'        => ($empId === $currentEmployeeId),
            'username'       => $username,
            'initials'       => $initials,
            'rule'           => $rule,
            'is_all_access'  => $isAll,
            'access_areas'   => $areas,
            'notes'          => $notes,
            'movies_cnt'     => $movCnt,
            'albums_cnt'     => $albCnt,
            'coactors_cnt'   => $coActCnt,
            'total_actions'  => $totalActions,
            'login_count'    => $loginCount,
            'last_action_de' => $lastActionDe,
            'last_login_de'  => $lastLoginDe,
            'last_ip'        => $lastIp,
        ];

        return View::render('Employees', 'detail', [
            'employee'            => $employee,
            'prevId'              => $prevId,
            'nextId'              => $nextId,
            'availableAreas'      => $availableAreas,
            'rolePresets'         => $rolePresets,
            'recentMovies'        => $recentMovies,
            'recentAlbums'        => $recentAlbums,
            'recentCoActors'      => $recentCoActors,
            'historyEntries'      => $historyEntries,
            'currentEmployeeId'   => $currentEmployeeId,
            'currentEmployeeRule' => $currentEmployeeRule,
            'impersonator'        => $impersonator,
            'canImpersonate'      => $canImpersonate,
        ]);
    }

    // =========================================================================
    // 3. Mitarbeiter / Admin anlegen oder speichern (POST ?route=employees/save)
    // =========================================================================
    public function save(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Dein Sicherheitstoken (CSRF) ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $currentEmployeeId   = abs((int)($_SESSION['employee_id'] ?? 0));
        $currentEmployeeRule = strtolower(trim((string)($_SESSION['employee_rule'] ?? '')));

        if ($currentEmployeeRule !== 'write') {
            Response::error('Nur Administratoren mit Schreibberechtigung (`write`) dürfen Mitarbeiter-Accounts anlegen oder bearbeiten.', 403);
            return;
        }

        $empId          = abs((int)$this->request->post('employee_id', 0));
        $isNew          = ($empId <= 0);
        $rawUsername    = trim((string)$this->request->post('username', ''));
        $cleanUsername  = (string)preg_replace('/[^a-zA-Z0-9]/', '', $rawUsername);
        $newPassword    = trim((string)$this->request->post('new_password', ''));
        $newPasswordRep = trim((string)$this->request->post('new_password_confirm', ''));
        $rule           = strtolower(trim((string)$this->request->post('rule', 'read'))) === 'write' ? 'write' : 'read';
        $isAllAccess    = (int)$this->request->post('access_all', 0) === 1;
        $postedAreas    = $this->request->post('access_areas', []);
        $notes          = (string)$this->request->post('notes', '');

        // 1. Benutzernamen prüfen (Login erlaubt nur alphanumerische Zeichen A-Za-z0-9, max. 25 Zeichen)
        if ($cleanUsername === '' || $cleanUsername !== $rawUsername) {
            Response::error('Der Benutzername darf aus Sicherheitsgründen beim ACP-Login nur aus Buchstaben und Zahlen (A–Z, a–z, 0–9, ohne Leer- oder Sonderzeichen) bestehen.', 422);
            return;
        }
        if (mb_strlen($cleanUsername) < 2 || mb_strlen($cleanUsername) > 25) {
            Response::error('Der Benutzername muss zwischen 2 und 25 Zeichen lang sein.', 422);
            return;
        }

        // Eindeutigkeit des Benutzernamens prüfen
        $rsDup = p4c_query(
            "SELECT `id` FROM `employee`
             WHERE LOWER(`username`) = LOWER('" . p4c_escape_string($cleanUsername) . "')
               AND `id` != '" . $empId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if ($rsDup && p4c_num_rows($rsDup) > 0) {
            Response::error("Der Benutzername „{$cleanUsername}“ ist bereits vergeben. Bitte wähle einen anderen Benutzernamen.", 422);
            return;
        }

        // 2. Passwort prüfen
        if ($isNew && $newPassword === '') {
            Response::error('Bitte vergib ein Passwort für den neuen Mitarbeiter-Account.', 422);
            return;
        }
        if ($newPassword !== '') {
            if (mb_strlen($newPassword) < 6) {
                Response::error('Das Passwort muss mindestens 6 Zeichen lang sein.', 422);
                return;
            }
            if ($newPasswordRep !== '' && $newPassword !== $newPasswordRep) {
                Response::error('Die beiden eingegebenen Passwörter stimmen nicht überein.', 422);
                return;
            }
        }

        // 3. Zugriffs-Bereiche (access_area) validieren
        $validAreaKeys = array_keys(self::getAvailableAccessAreas());
        $selectedAreas = [];

        if ($isAllAccess) {
            $selectedAreas = ['all'];
        } elseif (is_array($postedAreas)) {
            foreach ($postedAreas as $areaItem) {
                $areaKey = trim((string)$areaItem);
                if (in_array($areaKey, $validAreaKeys, true)) {
                    $selectedAreas[] = $areaKey;
                }
            }
            $selectedAreas = array_values(array_unique($selectedAreas));
        }

        if (empty($selectedAreas)) {
            Response::error('Bitte wähle mindestens einen freigeschalteten ACP-Bereich aus oder aktiviere den Vollzugriff (`all`).', 422);
            return;
        }

        // 4. Selbstschutz-Prüfung beim Bearbeiten des eigenen Accounts
        if (!$isNew && $empId === $currentEmployeeId) {
            if ($rule !== 'write') {
                Response::error('Selbstschutz: Du kannst deinem eigenen Account nicht die Administrator-Schreibberechtigung (`write`) entziehen.', 422);
                return;
            }
            if (!in_array('all', $selectedAreas, true) && !in_array('employees', $selectedAreas, true)) {
                Response::error('Selbstschutz: Du kannst dir nicht selbst die Berechtigung für die Mitarbeiter-Verwaltung (`employees` / `all`) entziehen.', 422);
                return;
            }
        }

        $accessAreaStr = implode(',', $selectedAreas);

        // Passwort-Hash über die zentrale EroCloud-Funktion erzeugen (sha512($pass.$salt) . ':' . $salt)
        $passwordHashSql = '';
        if ($newPassword !== '') {
            if (function_exists('hash_password')) {
                $hashedPassword = (string)hash_password($newPassword);
            } else {
                $cleanPass      = trim(htmlspecialchars(strip_tags($newPassword), ENT_QUOTES, 'ISO-8859-1'));
                $salt           = (string)mt_rand();
                $hashedPassword = hash('sha512', $cleanPass . $salt) . ':' . $salt;
            }
            $passwordHashSql = ", `password` = '" . p4c_escape_string($hashedPassword) . "'";
        }

        if ($isNew) {
            $hashedPassword = function_exists('hash_password')
                ? (string)hash_password($newPassword)
                : (hash('sha512', trim(htmlspecialchars(strip_tags($newPassword), ENT_QUOTES, 'ISO-8859-1')) . ($salt = (string)mt_rand())) . ':' . $salt);

            p4c_query(
                "INSERT INTO `employee` SET
                    `username`    = '" . p4c_escape_string($cleanUsername) . "',
                    `password`    = '" . p4c_escape_string($hashedPassword) . "',
                    `notes`       = '" . p4c_escape_string($notes) . "',
                    `rule`        = '" . p4c_escape_string($rule) . "',
                    `access_area` = '" . p4c_escape_string($accessAreaStr) . "';",
                __FILE__,
                __LINE__
            );

            $newEmpId = abs((int)p4c_insert_id());
            if (function_exists('log_action')) {
                log_action("Neuen Mitarbeiter/Admin angelegt: {$cleanUsername} (#{$newEmpId}, Rolle: {$rule}, Bereiche: {$accessAreaStr}).");
            }

            Response::json([
                'status'      => 'ok',
                'is_new'      => true,
                'employee_id' => $newEmpId,
                'message'     => "Der Mitarbeiter-Account „{$cleanUsername}“ (#{$newEmpId}) wurde erfolgreich angelegt!",
            ]);
            return;
        }

        // Bestehenden Mitarbeiter prüfen & aktualisieren
        $rsExists = p4c_query("SELECT `id`, `username` FROM `employee` WHERE `id` = '" . $empId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsExists || p4c_num_rows($rsExists) === 0) {
            Response::error('Der zu bearbeitende Mitarbeiter-Account wurde nicht gefunden.', 404);
            return;
        }

        p4c_query(
            "UPDATE `employee` SET
                `username`    = '" . p4c_escape_string($cleanUsername) . "',
                `notes`       = '" . p4c_escape_string($notes) . "',
                `rule`        = '" . p4c_escape_string($rule) . "',
                `access_area` = '" . p4c_escape_string($accessAreaStr) . "'
                {$passwordHashSql}
             WHERE `id` = '" . $empId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        // Falls der eigene Account bearbeitet wurde -> Session & Auth-Cookie sofort synchronisieren
        if ($empId === $currentEmployeeId) {
            $_SESSION['employee_username']    = $cleanUsername;
            $_SESSION['employee_rule']        = $rule;
            $_SESSION['employee_access_area'] = $selectedAreas;
            if (function_exists('acp_neu_sync_auth_cookie')) {
                acp_neu_sync_auth_cookie($empId, $cleanUsername);
            }
        }

        if (function_exists('log_action')) {
            $pwNote = $newPassword !== '' ? ', inkl. Passwort-Änderung' : '';
            log_action("Mitarbeiter/Admin aktualisiert: {$cleanUsername} (#{$empId}, Rolle: {$rule}, Bereiche: {$accessAreaStr}{$pwNote}).");
        }

        Response::json([
            'status'      => 'ok',
            'is_new'      => false,
            'employee_id' => $empId,
            'message'     => "Die Einstellungen für „{$cleanUsername}“ (#{$empId}) wurden erfolgreich gespeichert." . ($newPassword !== '' ? ' Das neue Passwort ist ab sofort aktiv.' : ''),
        ]);
    }

    // =========================================================================
    // 4. Mitarbeiter-Account löschen (POST ?route=employees/delete)
    // =========================================================================
    public function delete(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Dein Sicherheitstoken (CSRF) ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $currentEmployeeId   = abs((int)($_SESSION['employee_id'] ?? 0));
        $currentEmployeeRule = strtolower(trim((string)($_SESSION['employee_rule'] ?? '')));

        if ($currentEmployeeRule !== 'write') {
            Response::error('Nur Administratoren mit Schreibberechtigung (`write`) dürfen Mitarbeiter-Accounts löschen.', 403);
            return;
        }

        $targetId = abs((int)$this->request->post('employee_id', 0));
        if ($targetId <= 0) {
            Response::error('Ungültige Mitarbeiter-ID.', 400);
            return;
        }

        if ($targetId === $currentEmployeeId) {
            Response::error('Selbstschutz: Du kannst deinen eigenen, aktuell eingeloggten Admin-Account nicht löschen.', 422);
            return;
        }

        $rsTarget = p4c_query(
            "SELECT `id`, `username`, `rule`, `access_area` FROM `employee` WHERE `id` = '" . $targetId . "' LIMIT 1;",
            __FILE__,
            __LINE__
        );
        if (!$rsTarget || p4c_num_rows($rsTarget) === 0) {
            Response::error('Dieser Mitarbeiter-Account existiert nicht mehr.', 404);
            return;
        }

        $targetObj  = p4c_fetch_object($rsTarget);
        $targetName = $this->toUtf8((string)($targetObj->username ?? ('Admin #' . $targetId)));
        $targetRule = strtolower(trim((string)($targetObj->rule ?? 'read')));

        // Sicherstellen, dass mindestens 1 Administrator mit Schreibrecht im System verbleibt
        if ($targetRule === 'write') {
            $rsAdminsCnt = p4c_query("SELECT COUNT(*) AS `cnt` FROM `employee` WHERE `rule` = 'write' AND `id` != '" . $targetId . "';", __FILE__, __LINE__);
            $remainingAdmins = ($rsAdminsCnt && ($rA = p4c_fetch_object($rsAdminsCnt))) ? (int)($rA->cnt ?? 0) : 0;
            if ($remainingAdmins <= 0) {
                Response::error('Der letzte verbleibende Administrator-Account kann nicht gelöscht werden.', 422);
                return;
            }
        }

        // Falls dieser Mitarbeiter aktuell die Admin-Notizen-Sperre hält, freigeben
        p4c_query(
            "UPDATE `settings` SET `value` = '0' WHERE `variable` = 'admin_notes_edit' AND `value` = '" . $targetId . "';",
            __FILE__,
            __LINE__
        );

        // Mitarbeiter aus employee löschen
        p4c_query("DELETE FROM `employee` WHERE `id` = '" . $targetId . "' LIMIT 1;", __FILE__, __LINE__);

        if (function_exists('log_action')) {
            log_action("Mitarbeiter-Account gelöscht: {$targetName} (#{$targetId}, Rolle: {$targetRule}).");
        }

        Response::json([
            'status'  => 'ok',
            'message' => "Der Mitarbeiter-Account „{$targetName}“ (#{$targetId}) wurde erfolgreich gelöscht.",
        ]);
    }

    // =========================================================================
    // 5. Aktivitäts-Historie eines Mitarbeiters bereinigen (POST ?route=employees/clear-history)
    // =========================================================================
    public function clearHistory(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Dein Sicherheitstoken (CSRF) ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $currentEmployeeRule = strtolower(trim((string)($_SESSION['employee_rule'] ?? '')));
        if ($currentEmployeeRule !== 'write') {
            Response::error('Nur Administratoren mit Schreibberechtigung (`write`) dürfen die Aktivitäts-Historie bereinigen.', 403);
            return;
        }

        $targetId = abs((int)$this->request->post('employee_id', 0));
        $mode     = trim((string)$this->request->post('mode', 'older_30d')); // 'older_30d' oder 'all'

        if ($targetId <= 0) {
            Response::error('Ungültige Mitarbeiter-ID.', 400);
            return;
        }

        $rsTarget = p4c_query("SELECT `id`, `username` FROM `employee` WHERE `id` = '" . $targetId . "' LIMIT 1;", __FILE__, __LINE__);
        if (!$rsTarget || p4c_num_rows($rsTarget) === 0) {
            Response::error('Mitarbeiter-Account wurde nicht gefunden.', 404);
            return;
        }
        $targetName = $this->toUtf8((string)(p4c_fetch_object($rsTarget)->username ?? ('Admin #' . $targetId)));

        if ($mode === 'all') {
            p4c_query("DELETE FROM `employee_history` WHERE `employee_id` = '" . $targetId . "';", __FILE__, __LINE__);
            $deleted = function_exists('p4c_affected_rows') ? (int)p4c_affected_rows() : 0;
            if (function_exists('log_action')) {
                log_action("Aktivitäts-Historie von {$targetName} (#{$targetId}) vollständig geleert ({$deleted} Einträge entfernt).");
            }
            Response::json([
                'status'  => 'ok',
                'message' => "Die gesamte Aktivitäts-Historie von „{$targetName}“ ({$deleted} Einträge) wurde geleert.",
            ]);
            return;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
        p4c_query(
            "DELETE FROM `employee_history` WHERE `employee_id` = '" . $targetId . "' AND `datetime` < '" . p4c_escape_string($cutoff) . "';",
            __FILE__,
            __LINE__
        );
        $deleted = function_exists('p4c_affected_rows') ? (int)p4c_affected_rows() : 0;

        if (function_exists('log_action')) {
            log_action("Aktivitäts-Historie (>30 Tage) von {$targetName} (#{$targetId}) bereinigt ({$deleted} Einträge entfernt).");
        }

        Response::json([
            'status'  => 'ok',
            'message' => "Es wurden {$deleted} Historien-Einträge (älter als 30 Tage) von „{$targetName}“ bereinigt.",
        ]);
    }

    // =========================================================================
    // 6. Session eines Mitarbeiters übernehmen (POST ?route=employees/impersonate)
    // =========================================================================
    public function impersonate(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Dein Sicherheitstoken (CSRF) ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $currentEmployeeId   = abs((int)($_SESSION['employee_id'] ?? 0));
        $currentEmployeeRule = strtolower(trim((string)($_SESSION['employee_rule'] ?? '')));
        $impersonator        = isset($_SESSION['impersonator_employee']) && is_array($_SESSION['impersonator_employee'])
            ? $_SESSION['impersonator_employee']
            : null;

        $canImpersonate = ($currentEmployeeRule === 'write')
            || (is_array($impersonator) && strtolower((string)($impersonator['rule'] ?? '')) === 'write');

        if (!$canImpersonate) {
            Response::error('Nur Administratoren mit Schreibberechtigung (`write`) dürfen die Session eines anderen Mitarbeiters übernehmen.', 403);
            return;
        }

        $targetId = abs((int)$this->request->post('employee_id', 0));
        if ($targetId <= 0) {
            Response::error('Ungültige Mitarbeiter-ID.', 400);
            return;
        }

        if ($targetId === $currentEmployeeId) {
            Response::error('Du bist bereits in dieser Mitarbeiter-Session eingeloggt.', 422);
            return;
        }

        // Falls der Admin auf seinen eigenen ursprünglichen Admin-Account klickt -> Impersonation beenden
        if (is_array($impersonator) && abs((int)($impersonator['id'] ?? 0)) === $targetId) {
            $this->stopImpersonate();
            return;
        }

        $rsTarget = p4c_query(
            "SELECT `id`, `username`, `rule`, `access_area`
             FROM `employee`
             WHERE `id` = '" . $targetId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsTarget || p4c_num_rows($rsTarget) === 0) {
            Response::error('Der ausgewählte Mitarbeiter-Account wurde nicht gefunden.', 404);
            return;
        }

        $targetObj   = p4c_fetch_object($rsTarget);
        $targetName  = $this->toUtf8((string)($targetObj->username ?? ('Mitarbeiter #' . $targetId)));
        $targetRule  = strtolower(trim((string)($targetObj->rule ?? 'read'))) === 'write' ? 'write' : 'read';
        $targetAreas = $this->parseAccessAreas((string)($targetObj->access_area ?? ''));

        // Ursprüngliche Admin-Session beim ersten Wechsel sicher hinterlegen
        if (!is_array($impersonator) || empty($impersonator['id'])) {
            $_SESSION['impersonator_employee'] = [
                'id'          => $currentEmployeeId,
                'username'    => (string)($_SESSION['employee_username'] ?? 'Admin'),
                'rule'        => $currentEmployeeRule,
                'access_area' => isset($_SESSION['employee_access_area']) && is_array($_SESSION['employee_access_area'])
                    ? $_SESSION['employee_access_area']
                    : ['all'],
                'started_at'  => time(),
            ];
        }

        $origAdminId   = abs((int)$_SESSION['impersonator_employee']['id']);
        $origAdminName = (string)($_SESSION['impersonator_employee']['username'] ?? 'Admin');

        // Noch unter der bisherigen / Admin-ID protokollieren
        if (function_exists('log_action')) {
            log_action("Session-Übernahme: Admin {$origAdminName} (#{$origAdminId}) hat sich in die Session von {$targetName} (#{$targetId}) eingeloggt.");
        }

        // Session auf den Ziel-Mitarbeiter umschalten
        $_SESSION['logged_id']            = 'acp';
        $_SESSION['employee_id']          = $targetId;
        $_SESSION['employee_username']    = $targetName;
        $_SESSION['employee_rule']        = $targetRule;
        $_SESSION['employee_access_area'] = $targetAreas;
        $_SESSION['last_activity']        = time();

        if (function_exists('acp_neu_sync_auth_cookie')) {
            acp_neu_sync_auth_cookie($targetId, $targetName);
        }

        Response::json([
            'status'      => 'ok',
            'employee_id' => $targetId,
            'username'    => $targetName,
            'message'     => "Du bist jetzt in der Session von „{$targetName}“ (#{$targetId}) eingeloggt.",
        ]);
    }

    // =========================================================================
    // 7. Zurück zur ursprünglichen Admin-Session wechseln (POST ?route=employees/stop-impersonate)
    // =========================================================================
    public function stopImpersonate(): void
    {
        if (!$this->request->isPost()) {
            Response::error('Ungültige Anfrage-Methode.', 405);
            return;
        }

        if (!Csrf::validate($this->request->csrfToken())) {
            Response::error('Dein Sicherheitstoken (CSRF) ist abgelaufen. Bitte lade die Seite neu.', 403);
            return;
        }

        $impersonator = isset($_SESSION['impersonator_employee']) && is_array($_SESSION['impersonator_employee'])
            ? $_SESSION['impersonator_employee']
            : null;

        if (!is_array($impersonator) || empty($impersonator['id'])) {
            Response::error('Es ist aktuell keine übernommene Mitarbeiter-Session aktiv.', 400);
            return;
        }

        $origAdminId    = abs((int)$impersonator['id']);
        $prevEmpId      = abs((int)($_SESSION['employee_id'] ?? 0));
        $prevEmpName    = (string)($_SESSION['employee_username'] ?? ('#' . $prevEmpId));

        $rsAdmin = p4c_query(
            "SELECT `id`, `username`, `rule`, `access_area`
             FROM `employee`
             WHERE `id` = '" . $origAdminId . "'
             LIMIT 1;",
            __FILE__,
            __LINE__
        );

        if (!$rsAdmin || p4c_num_rows($rsAdmin) === 0) {
            Response::error('Der ursprüngliche Administrator-Account konnte nicht gefunden werden.', 404);
            return;
        }

        $adminObj   = p4c_fetch_object($rsAdmin);
        $adminName  = $this->toUtf8((string)($adminObj->username ?? 'Admin'));
        $adminRule  = strtolower(trim((string)($adminObj->rule ?? 'write'))) === 'write' ? 'write' : 'read';
        $adminAreas = $this->parseAccessAreas((string)($adminObj->access_area ?? 'all'));

        unset($_SESSION['impersonator_employee']);

        $_SESSION['logged_id']            = 'acp';
        $_SESSION['employee_id']          = $origAdminId;
        $_SESSION['employee_username']    = $adminName;
        $_SESSION['employee_rule']        = $adminRule;
        $_SESSION['employee_access_area'] = $adminAreas;
        $_SESSION['last_activity']        = time();

        if (function_exists('acp_neu_sync_auth_cookie')) {
            acp_neu_sync_auth_cookie($origAdminId, $adminName);
        }

        if (function_exists('log_action')) {
            log_action("Session-Übernahme beendet: Zurückgekehrt von {$prevEmpName} (#{$prevEmpId}) in den eigenen Admin-Account {$adminName} (#{$origAdminId}).");
        }

        Response::json([
            'status'      => 'ok',
            'employee_id' => $origAdminId,
            'username'    => $adminName,
            'message'     => "Willkommen zurück, {$adminName}! Deine eigene Admin-Session wurde wiederhergestellt.",
        ]);
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\ContentCleanup;

use App\Core\Request;
use App\Core\View;
use App\Modules\MoviesOnline\MoviesOnlineController;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * EroCloud ACP (v2.0) - ContentCleanupController (#/content-cleanup)
 * Vollständige Modernisierung von acp/includes/content_cleanup.php & acp/includes/ajax/orphan_scan.php.
 * Umfasst:
 * - Tab 1: Lösch-Vorschau (Simulation der 5 Bereinigungs-Regeln inkl. Speicherplatz-Berechnung)
 * - Tab 2: Lösch-Historie & Statistiken (movies_deleted)
 * - Tab 3: Dateileichen-Suche (Abgleich cloud_storage/ gegen Datenbank)
 */
final class ContentCleanupController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * GET ?route=content-cleanup
     */
    public function index(): string
    {
        $cutoff180Days = date('Y-m-d H:i:s', strtotime('-180 days'));
        $twoYearsAgo   = strtotime('-2 years');
        $nowTs         = time();

        // 1. Kandidaten für die Lösch-Vorschau laden
        $rsCandidates = \p4c_query(
            "SELECT `id`, `file_id`, `merchant_id`, `actor_id`, `storage_location`, `title`,
                    `status`, `released`, `online_at`, `movie_checked`, `create_datetime`,
                    `last_updated_datetime`, `deleted_datetime`
             FROM `movies`
             WHERE `status` = 'deleted'
                OR (
                    (`released` = '2' OR `status` = 'blocked')
                    AND `status` != 'deleted'
                    AND COALESCE(
                        NULLIF(`movie_checked`, '0000-00-00 00:00:00'),
                        NULLIF(`online_at`, '0000-00-00 00:00:00'),
                        NULLIF(`create_datetime`, '0000-00-00 00:00:00'),
                        NULLIF(`last_updated_datetime`, '0000-00-00 00:00:00')
                    ) < '" . \p4c_escape_string($cutoff180Days) . "'
                )
                OR (
                    `released` = '0'
                    AND `status` != 'deleted'
                    AND COALESCE(
                        NULLIF(`create_datetime`, '0000-00-00 00:00:00'),
                        NULLIF(`online_at`, '0000-00-00 00:00:00'),
                        NULLIF(`last_updated_datetime`, '0000-00-00 00:00:00')
                    ) < '" . \p4c_escape_string($cutoff180Days) . "'
                )
             ORDER BY `deleted_datetime` ASC, `id` ASC;",
            __FILE__,
            __LINE__
        );

        $rawMovies = [];
        $fileIds   = [];
        $actorIds  = [];

        while ($row = \p4c_fetch_object($rsCandidates)) {
            $rawMovies[] = $row;
            if (!empty($row->file_id)) {
                $fileIds[(string)$row->file_id] = true;
            }
            if ((int)$row->actor_id > 0) {
                $actorIds[(int)$row->actor_id] = true;
            }
        }

        // Batch-Lookups für movies_access, movies_online und actors (vermeidet N+1 Queries)
        $accessStatsByFileId = [];
        $onlineIdByFileId    = [];
        $actorNameById       = [];

        if (!empty($fileIds)) {
            $escapedFileIds = array_map(static fn(string $fid): string => "'" . \p4c_escape_string($fid) . "'", array_keys($fileIds));
            $inFileIdsSql   = implode(',', $escapedFileIds);

            $rsAcc = \p4c_query(
                "SELECT `movie_id`,
                        COUNT(*) AS `purchases_count`,
                        MAX(`buy_timestamp`) AS `last_buy`,
                        MAX(CASE WHEN `access_token_datetime` != '0000-00-00 00:00:00' THEN `access_token_datetime` ELSE NULL END) AS `last_view`
                 FROM `movies_access`
                 WHERE `movie_id` IN (" . $inFileIdsSql . ")
                 GROUP BY `movie_id`;",
                __FILE__,
                __LINE__
            );
            while ($aRow = \p4c_fetch_object($rsAcc)) {
                $accessStatsByFileId[(string)$aRow->movie_id] = [
                    'purchases_count' => (int)$aRow->purchases_count,
                    'last_buy'        => (string)($aRow->last_buy ?? ''),
                    'last_view'       => (string)($aRow->last_view ?? ''),
                ];
            }

            $rsOnline = \p4c_query(
                "SELECT `id`, `file_id` FROM `movies_online` WHERE `file_id` IN (" . $inFileIdsSql . ");",
                __FILE__,
                __LINE__
            );
            while ($oRow = \p4c_fetch_object($rsOnline)) {
                $onlineIdByFileId[(string)$oRow->file_id] = (int)$oRow->id;
            }
        }

        if (!empty($actorIds)) {
            $inActorIdsSql = implode(',', array_map('abs', array_keys($actorIds)));
            $rsActors = \p4c_query(
                "SELECT `id`, `username` FROM `actors` WHERE `id` IN (" . $inActorIdsSql . ");",
                __FILE__,
                __LINE__
            );
            while ($acRow = \p4c_fetch_object($rsActors)) {
                $actorNameById[(int)$acRow->id] = $this->cleanUtf8((string)$acRow->username);
            }
        }

        $previewRows       = [];
        $upcomingCount     = 0;
        $upcomingTotalSize = 0.0;
        $ruleCounts        = [
            'all'       => 0,
            'immediate' => 0,
            'rule_1'    => 0,
            'rule_2'    => 0,
            'rule_3'    => 0,
            'rule_4'    => 0,
            'rule_5'    => 0,
        ];

        foreach ($rawMovies as $row) {
            $mid      = (int)$row->id;
            $fid      = (string)$row->file_id;
            $accInfo  = $accessStatsByFileId[$fid] ?? ['purchases_count' => 0, 'last_buy' => '', 'last_view' => ''];
            $pCount   = (int)$accInfo['purchases_count'];
            $lastBuy  = $accInfo['last_buy'];
            $lastView = $accInfo['last_view'];

            $lastBuyTs    = ($lastBuy !== '' && $lastBuy !== '0000-00-00 00:00:00') ? (int)strtotime($lastBuy) : 0;
            $lastViewTs   = ($lastView !== '' && $lastView !== '0000-00-00 00:00:00') ? (int)strtotime($lastView) : 0;
            $lastBuyFmt   = $lastBuyTs > 0 ? date('d.m.Y H:i', $lastBuyTs) : '-';
            $lastViewFmt  = $lastViewTs > 0 ? date('d.m.Y H:i', $lastViewTs) : '-';

            $ruleKey      = '';
            $ruleLabel    = '';
            $ruleBadgeCls = 'bg-secondary';
            $plannedTs    = 0;
            $isImmediate  = false;

            if ((string)$row->released === '0' && (string)$row->status !== 'deleted') {
                $ruleKey      = 'rule_5';
                $ruleLabel    = 'Regel 5: Inaktive Entwürfe';
                $ruleBadgeCls = 'bg-danger';
                $plannedTs    = 1;
                $isImmediate  = true;
            } elseif ((string)$row->status !== 'deleted') {
                $ruleKey      = 'rule_4';
                $ruleLabel    = 'Regel 4: Alt & Abgelehnt';
                $ruleBadgeCls = 'bg-purple text-white';
                $plannedTs    = 1;
                $isImmediate  = true;
            } elseif ($pCount === 0) {
                $ruleKey      = 'rule_1';
                $ruleLabel    = 'Regel 1: Nie gekauft';
                $ruleBadgeCls = 'bg-warning text-dark';
                $plannedTs    = 1;
                $isImmediate  = true;
            } else {
                if ($lastBuyTs < $twoYearsAgo && $lastViewTs < $twoYearsAgo) {
                    $ruleKey      = 'rule_2';
                    $ruleLabel    = 'Regel 2: Inaktiv (> 2 Jahre)';
                    $ruleBadgeCls = 'bg-info text-dark';
                    $plannedTs    = (int)strtotime((string)$row->deleted_datetime . ' + 30 days');
                    $isImmediate  = ($plannedTs < $nowTs);
                } else {
                    $ruleKey      = 'rule_3';
                    $ruleLabel    = 'Regel 3: Aktiv (< 2 Jahre)';
                    $ruleBadgeCls = 'bg-secondary';
                    $plannedTs    = (int)strtotime((string)$row->deleted_datetime . ' + 365 days');
                    $isImmediate  = ($plannedTs < $nowTs);
                }
            }

            $ruleCounts['all']++;
            if (isset($ruleCounts[$ruleKey])) {
                $ruleCounts[$ruleKey]++;
            }

            $folderSize = 0.0;
            if ($pCount === 0 || $plannedTs < $nowTs) {
                $upcomingCount++;
                $ruleCounts['immediate']++;
                $loc        = !empty($row->storage_location) ? (string)$row->storage_location : (string)MOVIES_DEFAULT_DIR;
                $folderPath = MOVIES_PATH . '/' . $loc . '/' . (int)$row->merchant_id . '/' . $mid;
                $folderSize = $this->getFolderSize($folderPath);
                $upcomingTotalSize += $folderSize;
            }

            $onlineTs  = (!empty($row->online_at) && $row->online_at !== '0000-00-00 00:00:00') ? (int)strtotime((string)$row->online_at) : 0;
            $deletedTs = (!empty($row->deleted_datetime) && $row->deleted_datetime !== '0000-00-00 00:00:00') ? (int)strtotime((string)$row->deleted_datetime) : 0;

            $onlineId  = $onlineIdByFileId[$fid] ?? 0;
            $actorId   = (int)$row->actor_id;
            $actorName = $actorId > 0 ? ($actorNameById[$actorId] ?? ('ID: ' . $actorId)) : '-';

            $previewRows[] = [
                'id'               => $mid,
                'online_id'        => $onlineId,
                'merchant_id'      => (int)$row->merchant_id,
                'actor_id'         => $actorId,
                'actor_name'       => $actorName,
                'title'            => $this->cleanUtf8((string)$row->title),
                'online_ts'        => $onlineTs,
                'online_fmt'       => $onlineTs > 0 ? date('d.m.Y H:i', $onlineTs) : '-',
                'deleted_ts'       => $deletedTs,
                'deleted_fmt'      => $deletedTs > 0 ? date('d.m.Y H:i', $deletedTs) : '-',
                'purchases_count'  => $pCount,
                'last_buy_ts'      => $lastBuyTs,
                'last_buy_fmt'     => $lastBuyFmt,
                'last_view_ts'     => $lastViewTs,
                'last_view_fmt'    => $lastViewFmt,
                'planned_ts'       => $plannedTs,
                'planned_fmt'      => $isImmediate ? 'Sofort (nächster Cronjob-Lauf)' : date('d.m.Y H:i', $plannedTs),
                'is_immediate'     => $isImmediate,
                'rule_key'         => $ruleKey,
                'rule_label'       => $ruleLabel,
                'rule_badge_cls'   => $ruleBadgeCls,
                'folder_size_fmt'  => $folderSize > 0 ? $this->formatBytes($folderSize) : '-',
            ];
        }

        // 2. Reiter 2: Lösch-Historie (movies_deleted)
        $rsDeletedLog = \p4c_query(
            "SELECT * FROM `movies_deleted` ORDER BY `deletion_logged_at` DESC;",
            __FILE__,
            __LINE__
        );

        $historyRows     = [];
        $totalSavedBytes = 0.0;

        while ($logRow = \p4c_fetch_object($rsDeletedLog)) {
            $sizeBytes        = (float)($logRow->folder_size_bytes ?? 0);
            $totalSavedBytes += $sizeBytes;

            $logTs = (!empty($logRow->deletion_logged_at) && $logRow->deletion_logged_at !== '0000-00-00 00:00:00')
                ? (int)strtotime((string)$logRow->deletion_logged_at)
                : (int)strtotime((string)($logRow->deleted_datetime ?? ''));

            $historyRows[] = [
                'movie_id'         => (int)$logRow->movie_id,
                'title'            => $this->cleanUtf8((string)$logRow->title),
                'deleted_ts'       => $logTs,
                'deleted_fmt'      => $logTs > 0 ? date('d.m.Y H:i:s', $logTs) : '-',
                'size_bytes'       => $sizeBytes,
                'size_fmt'         => $this->formatBytes($sizeBytes),
                'purchases'        => (int)($logRow->purchases ?? 0),
                'views'            => (int)($logRow->views ?? 0),
            ];
        }

        $switcherCounts = MoviesOnlineController::getContentSwitcherCounts();

        return View::render('ContentCleanup', 'index', [
            'switcherCounts'   => $switcherCounts,
            'upcomingCount'    => $upcomingCount,
            'upcomingSizeFmt'  => $this->formatBytes($upcomingTotalSize),
            'ruleCounts'       => $ruleCounts,
            'previewRows'      => $previewRows,
            'historyCount'     => count($historyRows),
            'totalSavedFmt'    => $this->formatBytes($totalSavedBytes),
            'historyRows'      => $historyRows,
        ]);
    }

    /**
     * GET ?route=content-cleanup/orphan-scan
     * Scannt cloud_storage/ nach Dateileichen und gleicht gegen die Datenbank ab.
     */
    public function orphanScan(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        @session_write_close();
        @set_time_limit(180);

        $startTime = microtime(true);

        $merchants   = [];
        $rsMerchants = \p4c_query("SELECT `id` FROM `merchants`;", __FILE__, __LINE__);
        while ($row = \p4c_fetch_object($rsMerchants)) {
            $merchants[(int)$row->id] = true;
        }

        $movies   = [];
        $rsMovies = \p4c_query("SELECT `id`, `merchant_id`, `status` FROM `movies`;", __FILE__, __LINE__);
        while ($row = \p4c_fetch_object($rsMovies)) {
            $movies[(int)$row->merchant_id][(int)$row->id] = (string)$row->status;
        }

        $albums   = [];
        $rsAlbums = \p4c_query("SELECT `id`, `merchant_id`, `status` FROM `photo_albums`;", __FILE__, __LINE__);
        while ($row = \p4c_fetch_object($rsAlbums)) {
            $albums[(int)$row->merchant_id][(int)$row->id] = (string)$row->status;
        }

        $sites   = [];
        $rsSites = \p4c_query("SELECT `id` FROM `sites`;", __FILE__, __LINE__);
        while ($row = \p4c_fetch_object($rsSites)) {
            $sites[(int)$row->id] = true;
        }

        $ads   = [];
        $rsAds = \p4c_query("SELECT `site_id`, `filename`, `new_filename` FROM `ads_media`;", __FILE__, __LINE__);
        while ($row = \p4c_fetch_object($rsAds)) {
            $siteId = (int)$row->site_id;
            if ((string)$row->filename !== '') {
                $ads[$siteId][(string)$row->filename] = true;
            }
            if ((string)$row->new_filename !== '') {
                $ads[$siteId][(string)$row->new_filename] = true;
            }
        }

        $basePath  = rtrim(MOVIES_PATH . '/' . MOVIES_DEFAULT_DIR, '/\\');
        $orphans   = [];
        $totalSize = 0.0;

        if (!is_dir($basePath)) {
            echo json_encode([
                'status'               => 'success',
                'is_local_missing'     => true,
                'duration_ms'          => round((microtime(true) - $startTime) * 1000, 2),
                'total_count'          => 0,
                'total_size_formatted' => '0 Bytes',
                'message'              => 'Lokale Entwicklungsumgebung: Das Verzeichnis "' . $basePath . '" ist lokal nicht vorhanden. Auf dem Live-Server wird hier das physische cloud_storage-Verzeichnis gescannt.',
                'data'                 => [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $items = @scandir($basePath);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === '.htaccess' || $item === 'lost+found') {
                    continue;
                }
                $itemPath = $basePath . '/' . $item;

                if ($item === 'photo_albums') {
                    if (is_dir($itemPath)) {
                        $subItems = @scandir($itemPath);
                        if ($subItems !== false) {
                            foreach ($subItems as $subItem) {
                                if ($subItem === '.' || $subItem === '..') {
                                    continue;
                                }
                                $subItemPath = $itemPath . '/' . $subItem;
                                if (is_dir($subItemPath) && is_numeric($subItem)) {
                                    $merchantId = (int)$subItem;
                                    if (!isset($merchants[$merchantId])) {
                                        $size        = $this->getFolderSize($subItemPath);
                                        $orphans[]   = [
                                            'path'           => 'cloud_storage/photo_albums/' . $subItem,
                                            'type'           => 'Fotoalben Händler-Ordner',
                                            'reason'         => 'Der Händler (ID ' . $merchantId . ') existiert nicht in der Datenbank.',
                                            'size'           => $size,
                                            'size_formatted' => $this->formatBytes($size),
                                        ];
                                        $totalSize += $size;
                                    } else {
                                        $albumsInDir = @scandir($subItemPath);
                                        if ($albumsInDir !== false) {
                                            foreach ($albumsInDir as $albumDir) {
                                                if ($albumDir === '.' || $albumDir === '..') {
                                                    continue;
                                                }
                                                $albumPath = $subItemPath . '/' . $albumDir;
                                                if (is_dir($albumPath) && is_numeric($albumDir)) {
                                                    $albumId = (int)$albumDir;
                                                    if (!isset($albums[$merchantId][$albumId])) {
                                                        $size        = $this->getFolderSize($albumPath);
                                                        $orphans[]   = [
                                                            'path'           => 'cloud_storage/photo_albums/' . $subItem . '/' . $albumDir,
                                                            'type'           => 'Fotoalbum-Ordner',
                                                            'reason'         => 'Das Fotoalbum (ID ' . $albumId . ') existiert nicht in der Datenbank.',
                                                            'size'           => $size,
                                                            'size_formatted' => $this->formatBytes($size),
                                                        ];
                                                        $totalSize += $size;
                                                    }
                                                } else {
                                                    $size        = $this->getFolderSize($albumPath);
                                                    $orphans[]   = [
                                                        'path'           => 'cloud_storage/photo_albums/' . $subItem . '/' . $albumDir,
                                                        'type'           => is_dir($albumPath) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                                                        'reason'         => 'Ungültige Verzeichnisstruktur im Fotoalben-Händler-Ordner.',
                                                        'size'           => $size,
                                                        'size_formatted' => $this->formatBytes($size),
                                                    ];
                                                    $totalSize += $size;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $size        = $this->getFolderSize($subItemPath);
                                    $orphans[]   = [
                                        'path'           => 'cloud_storage/photo_albums/' . $subItem,
                                        'type'           => is_dir($subItemPath) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                                        'reason'         => 'Ungültige Verzeichnisstruktur im Fotoalben-Hauptordner.',
                                        'size'           => $size,
                                        'size_formatted' => $this->formatBytes($size),
                                    ];
                                    $totalSize += $size;
                                }
                            }
                        }
                    }
                } elseif ($item === 'ads') {
                    if (is_dir($itemPath)) {
                        $subItems = @scandir($itemPath);
                        if ($subItems !== false) {
                            foreach ($subItems as $subItem) {
                                if ($subItem === '.' || $subItem === '..') {
                                    continue;
                                }
                                $subItemPath = $itemPath . '/' . $subItem;
                                if (is_dir($subItemPath) && is_numeric($subItem)) {
                                    $siteId = (int)$subItem;
                                    if (!isset($sites[$siteId])) {
                                        $size        = $this->getFolderSize($subItemPath);
                                        $orphans[]   = [
                                            'path'           => 'cloud_storage/ads/' . $subItem,
                                            'type'           => 'Werbebanner Webseiten-Ordner',
                                            'reason'         => 'Die Webseite (ID ' . $siteId . ') existiert nicht in der Datenbank.',
                                            'size'           => $size,
                                            'size_formatted' => $this->formatBytes($size),
                                        ];
                                        $totalSize += $size;
                                    } else {
                                        $files = @scandir($subItemPath);
                                        if ($files !== false) {
                                            foreach ($files as $file) {
                                                if ($file === '.' || $file === '..') {
                                                    continue;
                                                }
                                                $filePath = $subItemPath . '/' . $file;
                                                if (is_file($filePath)) {
                                                    if (!isset($ads[$siteId][$file])) {
                                                        $size        = (float)@filesize($filePath);
                                                        $orphans[]   = [
                                                            'path'           => 'cloud_storage/ads/' . $subItem . '/' . $file,
                                                            'type'           => 'Werbebanner-Datei',
                                                            'reason'         => 'Die Datei ist nicht der Webseite zugeordnet oder registriert.',
                                                            'size'           => $size,
                                                            'size_formatted' => $this->formatBytes($size),
                                                        ];
                                                        $totalSize += $size;
                                                    }
                                                } else {
                                                    $size        = $this->getFolderSize($filePath);
                                                    $orphans[]   = [
                                                        'path'           => 'cloud_storage/ads/' . $subItem . '/' . $file,
                                                        'type'           => 'Unbekannter Ordner',
                                                        'reason'         => 'Unerwartetes Verzeichnis im Webseiten-Banner-Ordner.',
                                                        'size'           => $size,
                                                        'size_formatted' => $this->formatBytes($size),
                                                    ];
                                                    $totalSize += $size;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $size        = $this->getFolderSize($subItemPath);
                                    $orphans[]   = [
                                        'path'           => 'cloud_storage/ads/' . $subItem,
                                        'type'           => is_dir($subItemPath) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                                        'reason'         => 'Ungültige Struktur im Banner-Hauptordner.',
                                        'size'           => $size,
                                        'size_formatted' => $this->formatBytes($size),
                                    ];
                                    $totalSize += $size;
                                }
                            }
                        }
                    }
                } else {
                    if (is_dir($itemPath) && is_numeric($item)) {
                        $merchantId = (int)$item;
                        if (!isset($merchants[$merchantId])) {
                            $size        = $this->getFolderSize($itemPath);
                            $orphans[]   = [
                                'path'           => 'cloud_storage/' . $item,
                                'type'           => 'Händler-Ordner',
                                'reason'         => 'Der Händler (ID ' . $merchantId . ') existiert nicht in der Datenbank.',
                                'size'           => $size,
                                'size_formatted' => $this->formatBytes($size),
                            ];
                            $totalSize += $size;
                        } else {
                            $moviesInDir = @scandir($itemPath);
                            if ($moviesInDir !== false) {
                                foreach ($moviesInDir as $movieDir) {
                                    if ($movieDir === '.' || $movieDir === '..') {
                                        continue;
                                    }
                                    $moviePath = $itemPath . '/' . $movieDir;
                                    if (is_dir($moviePath) && is_numeric($movieDir)) {
                                        $movieId = (int)$movieDir;
                                        if (!isset($movies[$merchantId][$movieId])) {
                                            $size        = $this->getFolderSize($moviePath);
                                            $orphans[]   = [
                                                'path'           => 'cloud_storage/' . $item . '/' . $movieDir,
                                                'type'           => 'Film-Ordner',
                                                'reason'         => 'Der Film (ID ' . $movieId . ') existiert nicht in der Datenbank.',
                                                'size'           => $size,
                                                'size_formatted' => $this->formatBytes($size),
                                            ];
                                            $totalSize += $size;
                                        }
                                    } else {
                                        $size        = $this->getFolderSize($moviePath);
                                        $orphans[]   = [
                                            'path'           => 'cloud_storage/' . $item . '/' . $movieDir,
                                            'type'           => is_dir($moviePath) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                                            'reason'         => 'Ungültige Verzeichnisstruktur im Händler-Ordner.',
                                            'size'           => $size,
                                            'size_formatted' => $this->formatBytes($size),
                                        ];
                                        $totalSize += $size;
                                    }
                                }
                            }
                        }
                    } else {
                        $size        = $this->getFolderSize($itemPath);
                        $orphans[]   = [
                            'path'           => 'cloud_storage/' . $item,
                            'type'           => is_dir($itemPath) ? 'Unbekannter Ordner' : 'Unbekannte Datei',
                            'reason'         => 'Verzeichnis oder Datei befindet sich direkt im Hauptordner.',
                            'size'           => $size,
                            'size_formatted' => $this->formatBytes($size),
                        ];
                        $totalSize += $size;
                    }
                }
            }
        }

        // Nach Größe absteigend sortieren
        usort($orphans, static fn(array $a, array $b): int => $b['size'] <=> $a['size']);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        echo json_encode([
            'status'               => 'success',
            'duration_ms'          => $durationMs,
            'total_count'          => count($orphans),
            'total_size_formatted' => $this->formatBytes($totalSize),
            'data'                 => $orphans,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function getFolderSize(string $dir): float
    {
        $size = 0.0;
        if (!is_dir($dir)) {
            return is_file($dir) ? (float)@filesize($dir) : 0.0;
        }
        $files = @scandir($dir);
        if ($files === false) {
            return 0.0;
        }
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $size += $this->getFolderSize($path);
            } else {
                $size += (float)@filesize($path);
            }
        }
        return $size;
    }

    private function formatBytes(float $bytes): string
    {
        if ($bytes >= 1099511627776) {
            return number_format($bytes / 1099511627776, 2, ',', '.') . ' TB';
        }
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return number_format($bytes, 0, ',', '.') . ' Bytes';
    }

    private function cleanUtf8(string $val): string
    {
        if ($val === '') {
            return '';
        }
        if (!mb_check_encoding($val, 'UTF-8')) {
            $val = mb_convert_encoding($val, 'UTF-8', 'ISO-8859-1');
        }
        return html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

<?php
declare(strict_types=1);

namespace App\Modules\StorageAnalytics;

use App\Core\Request;
use App\Core\View;
use App\Modules\MoviesOnline\MoviesOnlineController;

if (!defined('SAFE_INC')) {
    die('Hacking attempt...');
}

/**
 * EroCloud ACP (v2.0) - StorageAnalyticsController (#/storage-analytics)
 * Vollständige Modernisierung von acp/includes/storage_analytics.php,
 * acp/includes/ajax/storage_analytics_scan.php & acp/includes/ajax/storage_analytics_legacy_cleanup.php.
 */
final class StorageAnalyticsController
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * GET ?route=storage-analytics
     */
    public function index(): string
    {
        $analyticsData  = $this->computeOrLoadAnalytics(false);
        $switcherCounts = MoviesOnlineController::getContentSwitcherCounts();

        return View::render('StorageAnalytics', 'index', [
            'analytics'      => $analyticsData,
            'switcherCounts' => $switcherCounts,
        ]);
    }

    /**
     * GET ?route=storage-analytics/scan
     */
    public function scan(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        @session_write_close();

        $forceRefresh = ((string)$this->request->get('force_refresh', '0') === '1');
        $data         = $this->computeOrLoadAnalytics($forceRefresh);

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * GET/POST ?route=storage-analytics/legacy-cleanup
     * 7-Stufen-Sicherheitsprüfung für Einzel-Vorschau, Einzel-Löschung & Stapel-Bereinigung (m_*.mp4, m_*.webm, m_*.ogv).
     */
    public function legacyCleanup(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        @session_write_close();
        @set_time_limit(120);

        $action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : 'preview';

        // 1. VORSCHAU FÜR GENAU 1 VIDEO LADEN
        if ($action === 'preview') {
            $filterMovieId    = isset($_GET['movie_id']) ? abs((int)$_GET['movie_id']) : 0;
            $filterMerchantId = isset($_GET['merchant_id']) ? abs((int)$_GET['merchant_id']) : 0;
            $skipCount        = isset($_GET['skip']) ? abs((int)$_GET['skip']) : 0;

            $whereParts = [];
            $orderBySql = '`mo`.`id` DESC, `m`.`id` DESC';

            if ($filterMovieId > 0) {
                $whereParts[] = "(`mo`.`id` = '" . $filterMovieId . "' OR `m`.`id` = '" . $filterMovieId . "')";
                $orderBySql   = "(CASE WHEN `mo`.`id` = '" . $filterMovieId . "' THEN 0 ELSE 1 END) ASC, `m`.`id` DESC";
            } else {
                $whereParts[] = "`m`.`convert_status` = '2'";
            }

            if ($filterMerchantId > 0) {
                $whereParts[] = "`m`.`merchant_id` = '" . $filterMerchantId . "'";
            }

            $whereSql = implode(' AND ', $whereParts);
            $rsMovies = \p4c_query(
                "SELECT `m`.`id`, `mo`.`id` AS `online_id`, `m`.`file_id`, `m`.`merchant_id`, `m`.`actor_id`,
                        `m`.`storage_location`, `m`.`filename`, IFNULL(`mo`.`title`, `m`.`title`) AS `title`,
                        `m`.`status`, `m`.`resolution`, `m`.`playtime_string`, `a`.`username` AS `actor_username`
                 FROM `movies` AS `m`
                 LEFT JOIN `movies_online` AS `mo` ON `m`.`file_id` = `mo`.`file_id`
                 LEFT JOIN `actors` AS `a` ON `m`.`actor_id` = `a`.`id`
                 WHERE " . $whereSql . "
                 ORDER BY " . $orderBySql . ";",
                __FILE__,
                __LINE__
            );

            if (\p4c_num_rows($rsMovies) === 0) {
                echo json_encode([
                    'found'   => false,
                    'message' => 'Es wurde kein Film mit der ID #' . $filterMovieId . ' (weder in Filme-online noch in der Haupttabelle movies) gefunden.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $matchedIndex       = 0;
            $inspectedFolders   = 0;
            $selectedInspection = null;

            while ($m = \p4c_fetch_object($rsMovies)) {
                $info = $this->inspectMovieFolder($m);
                if ($info['folder_exists']) {
                    $inspectedFolders++;
                }

                if ($filterMovieId > 0) {
                    $selectedInspection = $info;
                    break;
                }

                if ($info['can_delete_safely']) {
                    if ($matchedIndex >= $skipCount) {
                        $selectedInspection = $info;
                        break;
                    }
                    $matchedIndex++;
                }
            }

            if ($selectedInspection === null) {
                $msg = ($inspectedFolders === 0)
                    ? 'Auf diesem System wurden keine physischen Film-Verzeichnisse unter ' . MOVIES_PATH . '/' . MOVIES_DEFAULT_DIR . ' gefunden (lokale Entwicklungsumgebung). Auf dem Live-Server wird hier automatisch das erste Video mit vorhandenen m_*-Dateien geladen.'
                    : 'Es wurden keine weiteren Videos gefunden, die noch unbereinigte m_*-Dateien (m_*.mp4, m_*.webm, m_*.ogv) enthalten.';
                echo json_encode([
                    'found'             => false,
                    'inspected_folders' => $inspectedFolders,
                    'message'           => $msg,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            echo json_encode([
                'found' => true,
                'movie' => $selectedInspection,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 2. BESTÄTIGTE EINZEL-LÖSCHUNG FÜR GENAU 1 VIDEO
        if ($action === 'delete_single') {
            $movieId       = isset($_POST['movie_id']) ? abs((int)$_POST['movie_id']) : 0;
            $confirmFileId = isset($_POST['confirm_file_id']) ? trim((string)$_POST['confirm_file_id']) : '';

            if ($movieId <= 0 || $confirmFileId === '') {
                echo json_encode(['error' => 'Sicherheitssperre: Film-ID oder File-ID zur Bestätigung fehlt.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $rsMovie = \p4c_query(
                "SELECT `m`.`id`, `mo`.`id` AS `online_id`, `m`.`file_id`, `m`.`merchant_id`, `m`.`actor_id`,
                        `m`.`storage_location`, `m`.`filename`, IFNULL(`mo`.`title`, `m`.`title`) AS `title`,
                        `m`.`status`, `m`.`resolution`, `m`.`playtime_string`, `a`.`username` AS `actor_username`
                 FROM `movies` AS `m`
                 LEFT JOIN `movies_online` AS `mo` ON `m`.`file_id` = `mo`.`file_id`
                 LEFT JOIN `actors` AS `a` ON `m`.`actor_id` = `a`.`id`
                 WHERE `m`.`id` = '" . $movieId . "'
                   AND `m`.`file_id` = '" . \p4c_escape_string($confirmFileId) . "'
                 LIMIT 1;",
                __FILE__,
                __LINE__
            );

            if (\p4c_num_rows($rsMovie) !== 1) {
                echo json_encode(['error' => 'Sicherheitssperre: Das angegebene Video wurde mit dieser Kombination aus ID und File-ID nicht gefunden.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $m          = \p4c_fetch_object($rsMovie);
            $beforeInfo = $this->inspectMovieFolder($m);

            if (!$beforeInfo['desktop_exists'] || $beforeInfo['desktop_size_bytes'] <= 0) {
                echo json_encode([
                    'error' => 'ABBRUCH DURCH SICHERHEITSSPERRE: Die Desktop-Hauptdatei (' . $beforeInfo['desktop_full_path'] . ') existiert nicht oder hat 0 Bytes. Es wurde keine einzige Datei gelöscht!',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($beforeInfo['deletable_count'] === 0) {
                echo json_encode([
                    'error' => 'Für dieses Video (#' . $movieId . ') sind keine löschbaren m_*-Dateien (mehr) vorhanden.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $execRes   = $this->executeSafeMovieCleanup($m, $beforeInfo);
            $afterInfo = $execRes['after_info'];

            $cacheFile = sys_get_temp_dir() . '/erocloud_storage_analytics_cache.json';
            if (is_file($cacheFile)) {
                @unlink($cacheFile);
            }

            echo json_encode([
                'success'                 => true,
                'movie_id'                => $movieId,
                'online_id'               => $afterInfo['online_id'],
                'deleted_count'           => count($execRes['deleted_log']),
                'deleted_files'           => $execRes['deleted_log'],
                'failed_files'            => $execRes['failed_log'],
                'freed_bytes'             => $execRes['freed_bytes'],
                'freed_formatted'         => $this->formatBytes($execRes['freed_bytes']),
                'desktop_verified_intact' => $execRes['desktop_verified_intact'],
                'desktop_full_path'       => $afterInfo['desktop_full_path'],
                'desktop_size_formatted'  => $afterInfo['desktop_size_formatted'],
                'thumbnails_count_after'  => $afterInfo['thumbnails_count'],
                'movie_after'             => $afterInfo,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 3. KONTROLLIERTE STAPEL-BEREINIGUNG (BATCH-MODUS MIT 7-STUFEN-PRÜFUNG)
        if ($action === 'delete_batch') {
            $batchLimit       = isset($_POST['batch_limit']) ? max(1, min(250, abs((int)$_POST['batch_limit']))) : 10;
            $filterMerchantId = isset($_POST['merchant_id']) ? abs((int)$_POST['merchant_id']) : 0;
            $cursorMovieId    = isset($_POST['cursor_movie_id']) ? abs((int)$_POST['cursor_movie_id']) : 0;

            $whereParts = ["`m`.`convert_status` = '2'"];
            if ($filterMerchantId > 0) {
                $whereParts[] = "`m`.`merchant_id` = '" . $filterMerchantId . "'";
            }
            if ($cursorMovieId > 0) {
                $whereParts[] = "`m`.`id` < '" . $cursorMovieId . "'";
            }
            $whereSql = implode(' AND ', $whereParts);

            $whereTotalParts = ["`m`.`convert_status` = '2'"];
            if ($filterMerchantId > 0) {
                $whereTotalParts[] = "`m`.`merchant_id` = '" . $filterMerchantId . "'";
            }
            $rsTotalCnt        = \p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` AS `m` WHERE " . implode(' AND ', $whereTotalParts) . ";", __FILE__, __LINE__);
            $totalDbCandidates = (int)(\p4c_fetch_object($rsTotalCnt)->cnt ?? 0);

            $maxScanPerCall = 500;
            $rsMovies       = \p4c_query(
                "SELECT `m`.`id`, `mo`.`id` AS `online_id`, `m`.`file_id`, `m`.`merchant_id`, `m`.`actor_id`,
                        `m`.`storage_location`, `m`.`filename`, IFNULL(`mo`.`title`, `m`.`title`) AS `title`,
                        `m`.`status`, `m`.`resolution`, `m`.`playtime_string`, `a`.`username` AS `actor_username`
                 FROM `movies` AS `m`
                 LEFT JOIN `movies_online` AS `mo` ON `m`.`file_id` = `mo`.`file_id`
                 LEFT JOIN `actors` AS `a` ON `m`.`actor_id` = `a`.`id`
                 WHERE " . $whereSql . "
                 ORDER BY `m`.`id` DESC
                 LIMIT " . $maxScanPerCall . ";",
                __FILE__,
                __LINE__
            );

            $cleanedMovies     = [];
            $skippedMovies     = [];
            $scannedInCall     = 0;
            $alreadyCleanCount = 0;
            $deletedFilesTotal = 0;
            $freedBytesTotal   = 0.0;
            $lastSeenMovieId   = $cursorMovieId;
            $emergencyStop     = false;
            $emergencyMessage  = '';

            while ($m = \p4c_fetch_object($rsMovies)) {
                $scannedInCall++;
                $lastSeenMovieId = (int)$m->id;

                $beforeInfo = $this->inspectMovieFolder($m);
                if (!$beforeInfo['folder_exists']) {
                    continue;
                }
                if ($beforeInfo['deletable_count'] === 0) {
                    $alreadyCleanCount++;
                    continue;
                }
                if (!$beforeInfo['desktop_exists'] || $beforeInfo['desktop_size_bytes'] <= 0) {
                    $skippedMovies[] = [
                        'movie_id'    => (int)$m->id,
                        'online_id'   => $beforeInfo['online_id'],
                        'merchant_id' => (int)$m->merchant_id,
                        'title'       => $beforeInfo['title'],
                        'folder_path' => $beforeInfo['folder_path'],
                        'reason'      => 'Desktop-MP4 fehlt oder hat 0 Bytes – Ordner zu 100 % unberührt übersprungen!',
                    ];
                    continue;
                }

                $execRes = $this->executeSafeMovieCleanup($m, $beforeInfo);

                $fileNamesDeleted = [];
                foreach ($execRes['deleted_log'] as $dl) {
                    $fileNamesDeleted[] = $dl['filename'];
                }

                $deletedCnt         = count($execRes['deleted_log']);
                $deletedFilesTotal += $deletedCnt;
                $freedBytesTotal   += $execRes['freed_bytes'];

                $cleanedMovies[] = [
                    'movie_id'                => (int)$m->id,
                    'online_id'               => $beforeInfo['online_id'],
                    'merchant_id'             => (int)$m->merchant_id,
                    'actor'                   => $beforeInfo['actor_username'],
                    'title'                   => $beforeInfo['title'],
                    'folder_path'             => $beforeInfo['folder_path'],
                    'deleted_count'           => $deletedCnt,
                    'deleted_filenames'       => $fileNamesDeleted,
                    'deleted_filenames_str'   => implode(', ', $fileNamesDeleted),
                    'freed_bytes'             => $execRes['freed_bytes'],
                    'freed_formatted'         => $this->formatBytes($execRes['freed_bytes']),
                    'desktop_filename'        => $beforeInfo['desktop_filename'],
                    'desktop_size_formatted'  => $execRes['after_info']['desktop_size_formatted'],
                    'desktop_verified_intact' => $execRes['desktop_verified_intact'],
                ];

                if (!$execRes['desktop_verified_intact']) {
                    $emergencyStop    = true;
                    $emergencyMessage = 'SICHERHEITS-STOPP bei Video #' . (int)$m->id . ': Nachprüfung der Desktop-MP4 fehlgeschlagen. Stapellauf wurde sofort angehalten!';
                    break;
                }

                if (count($cleanedMovies) >= $batchLimit) {
                    break;
                }
            }

            $hasMoreInDb     = false;
            $remainingDbRows = 0;
            if (!$emergencyStop && $lastSeenMovieId > 0) {
                $whereRemParts = ["`m`.`convert_status` = '2'", "`m`.`id` < '" . $lastSeenMovieId . "'"];
                if ($filterMerchantId > 0) {
                    $whereRemParts[] = "`m`.`merchant_id` = '" . $filterMerchantId . "'";
                }
                $rsRem           = \p4c_query("SELECT COUNT(*) AS `cnt` FROM `movies` AS `m` WHERE " . implode(' AND ', $whereRemParts) . ";", __FILE__, __LINE__);
                $remainingDbRows = (int)(\p4c_fetch_object($rsRem)->cnt ?? 0);
                $hasMoreInDb     = ($remainingDbRows > 0);
            }

            if ($deletedFilesTotal > 0) {
                $cacheFileSa = sys_get_temp_dir() . '/erocloud_storage_analytics_cache.json';
                if (is_file($cacheFileSa)) {
                    @unlink($cacheFileSa);
                }
                $cacheFileMo = sys_get_temp_dir() . '/erocloud_online_movies_size_cache.txt';
                if (is_file($cacheFileMo)) {
                    @unlink($cacheFileMo);
                }
            }

            echo json_encode([
                'success'                  => !$emergencyStop,
                'emergency_stop'           => $emergencyStop,
                'emergency_message'        => $emergencyMessage,
                'emergency_reason'         => $emergencyMessage,
                'batch_limit'              => $batchLimit,
                'scanned_in_call'          => $scannedInCall,
                'scanned_folders'          => $scannedInCall,
                'already_clean_count'      => $alreadyCleanCount,
                'cleaned_movies_count'     => count($cleanedMovies),
                'cleaned_movies'           => $cleanedMovies,
                'cleaned_items'            => $cleanedMovies,
                'skipped_movies_count'     => count($skippedMovies),
                'skipped_no_desktop_count' => count($skippedMovies),
                'skipped_movies'           => $skippedMovies,
                'skipped_items'            => $skippedMovies,
                'deleted_files_count'      => $deletedFilesTotal,
                'freed_bytes'              => $freedBytesTotal,
                'freed_formatted'          => $this->formatBytes($freedBytesTotal),
                'next_cursor_movie_id'     => $lastSeenMovieId,
                'has_more'                 => $hasMoreInDb,
                'total_db_candidates'      => $totalDbCandidates,
                'remaining_db_rows'        => $remainingDbRows,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['error' => 'Unbekannte Aktion.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeOrLoadAnalytics(bool $forceRefresh = false): array
    {
        @set_time_limit(300);
        $startTime     = microtime(true);
        $cacheFile     = sys_get_temp_dir() . '/erocloud_storage_analytics_cache.json';
        $cacheLifetime = 86400; // 24 Stunden

        if (!$forceRefresh && is_file($cacheFile) && (time() - (int)@filemtime($cacheFile) < $cacheLifetime)) {
            $cachedJson = @file_get_contents($cacheFile);
            if ($cachedJson !== false && $cachedJson !== '') {
                $cachedData = json_decode($cachedJson, true);
                if (is_array($cachedData)) {
                    $cachedData['from_cache']        = true;
                    $cachedData['cache_age_minutes'] = round((time() - (int)@filemtime($cacheFile)) / 60);
                    return $cachedData;
                }
            }
        }

        // 1. Online Movies Lookup
        $onlineFileIds = [];
        $rsMo          = \p4c_query("SELECT `file_id`, `status` FROM `movies_online`;", __FILE__, __LINE__);
        while ($moRow = \p4c_fetch_object($rsMo)) {
            $onlineFileIds[(string)$moRow->file_id] = (string)$moRow->status;
        }

        // 2. Movies Storage & File Format Breakdown
        $movieFormats = [
            'desktop_mp4'     => ['count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
            'mobile_mp4'      => ['count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
            'mobile_webm'     => ['count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
            'mobile_ogv'      => ['count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
            'thumbnails'      => ['count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
            'originals_other' => ['count' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
        ];

        $movieStatuses = [
            'online'   => ['count' => 0, 'playtime_sec' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
            'drafts'   => ['count' => 0, 'playtime_sec' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
            'rejected' => ['count' => 0, 'playtime_sec' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
            'deleted'  => ['count' => 0, 'playtime_sec' => 0, 'bytes' => 0.0, 'est_bytes' => 0.0],
        ];

        $movieDesktopSizes         = [];
        $physicalMovieFoldersFound = 0;

        $rsMovies      = \p4c_query(
            "SELECT `id`, `file_id`, `merchant_id`, `storage_location`, `filename`, `playtime_seconds`, `status`, `released`, `convert_status` FROM `movies`;",
            __FILE__,
            __LINE__
        );
        $totalDbMovies = \p4c_num_rows($rsMovies);

        while ($m = \p4c_fetch_object($rsMovies)) {
            $playtime = (int)$m->playtime_seconds;
            $loc      = ((string)$m->storage_location !== '') ? (string)$m->storage_location : (string)MOVIES_DEFAULT_DIR;
            $folder   = MOVIES_PATH . '/' . $loc . '/' . (int)$m->merchant_id . '/' . (int)$m->id;

            $statusKey = 'drafts';
            if ((string)$m->status === 'deleted') {
                $statusKey = 'deleted';
            } elseif ((string)$m->status === 'blocked' || (string)$m->released === '2') {
                $statusKey = 'rejected';
            } elseif (isset($onlineFileIds[(string)$m->file_id]) && $onlineFileIds[(string)$m->file_id] === 'active') {
                $statusKey = 'online';
            } elseif ((string)$m->released === '1' && (string)$m->status === 'active') {
                $statusKey = 'online';
            }

            $movieStatuses[$statusKey]['count']++;
            $movieStatuses[$statusKey]['playtime_sec'] += $playtime;

            $estDesktop = ((string)$m->convert_status === '2') ? ($playtime * 1016000.0) : 0.0;
            $estMMp4    = ((string)$m->convert_status === '2') ? ($playtime * 149500.0) : 0.0;
            $estMWebm   = ((string)$m->convert_status === '2') ? ($playtime * 141000.0) : 0.0;
            $estMOgv    = ((string)$m->convert_status === '2') ? ($playtime * 141000.0) : 0.0;
            $estThumbs  = ((string)$m->convert_status === '2' && $playtime > 0) ? 850000.0 : 0.0;

            $movieFormats['desktop_mp4']['est_bytes'] += $estDesktop;
            $movieFormats['mobile_mp4']['est_bytes']  += $estMMp4;
            $movieFormats['mobile_webm']['est_bytes'] += $estMWebm;
            $movieFormats['mobile_ogv']['est_bytes']  += $estMOgv;
            $movieFormats['thumbnails']['est_bytes']  += $estThumbs;

            $estFolderTotal = $estDesktop + $estMMp4 + $estMWebm + $estMOgv + $estThumbs;
            $movieStatuses[$statusKey]['est_bytes'] += $estFolderTotal;

            $actualDesktopBytes = 0.0;
            $folderBytes        = 0.0;

            if (is_dir($folder)) {
                $physicalMovieFoldersFound++;
                $files = @scandir($folder);
                if ($files !== false) {
                    foreach ($files as $f) {
                        if ($f === '.' || $f === '..') {
                            continue;
                        }
                        $fpath = $folder . '/' . $f;
                        if (!is_file($fpath)) {
                            continue;
                        }
                        $fsize        = (float)@filesize($fpath);
                        $folderBytes += $fsize;
                        $lowerF       = strtolower($f);
                        $ext          = pathinfo($lowerF, PATHINFO_EXTENSION);

                        if (strpos($lowerF, 'thumb_') === 0) {
                            $movieFormats['thumbnails']['count']++;
                            $movieFormats['thumbnails']['bytes'] += $fsize;
                        } elseif (strpos($lowerF, 'm_') === 0 && $ext === 'mp4') {
                            $movieFormats['mobile_mp4']['count']++;
                            $movieFormats['mobile_mp4']['bytes'] += $fsize;
                        } elseif (strpos($lowerF, 'm_') === 0 && $ext === 'webm') {
                            $movieFormats['mobile_webm']['count']++;
                            $movieFormats['mobile_webm']['bytes'] += $fsize;
                        } elseif (strpos($lowerF, 'm_') === 0 && $ext === 'ogv') {
                            $movieFormats['mobile_ogv']['count']++;
                            $movieFormats['mobile_ogv']['bytes'] += $fsize;
                        } elseif ($ext === 'mp4' && strpos($lowerF, 'neu_') !== 0) {
                            $movieFormats['desktop_mp4']['count']++;
                            $movieFormats['desktop_mp4']['bytes'] += $fsize;
                            $actualDesktopBytes = $fsize;
                        } else {
                            $movieFormats['originals_other']['count']++;
                            $movieFormats['originals_other']['bytes'] += $fsize;
                        }
                    }
                }
                $movieStatuses[$statusKey]['bytes'] += $folderBytes;
            }

            $movieDesktopSizes[(string)$m->file_id] = [
                'actual'    => $actualDesktopBytes,
                'estimated' => $estDesktop,
                'playtime'  => $playtime,
            ];
        }

        // 3. Other Storage Directories
        $photoAlbumsDir  = PHOTO_ALBUMS_PATH . '/' . PHOTO_ALBUMS_DEFAULT_DIR;
        $photoFilesCount = 0;
        $photoAlbumsBytes = $this->scanDirBytes($photoAlbumsDir, $photoFilesCount);

        $adsDir        = ADS_PATH . '/' . ADS_DEFAULT_DIR;
        $adsFilesCount = 0;
        $adsBytes      = $this->scanDirBytes($adsDir, $adsFilesCount);

        $merchantDir        = PROFILE_IMAGE_PATH . '/' . MERCHANT_DEFAULT_DIR;
        $merchantFilesCount = 0;
        $merchantBytes      = $this->scanDirBytes($merchantDir, $merchantFilesCount);

        $rsPaCnt            = \p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums` WHERE `status` != 'deleted';", __FILE__, __LINE__);
        $dbPhotoAlbumsCount = (int)(\p4c_fetch_object($rsPaCnt)->cnt ?? 0);

        $rsPaoCnt                 = \p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_online`;", __FILE__, __LINE__);
        $dbPhotoAlbumsOnlineCount = (int)(\p4c_fetch_object($rsPaoCnt)->cnt ?? 0);

        $rsPhotosCnt   = \p4c_query("SELECT COUNT(*) AS `cnt` FROM `photo_albums_photos`;", __FILE__, __LINE__);
        $dbPhotosCount = (int)(\p4c_fetch_object($rsPhotosCnt)->cnt ?? 0);

        $rsAdsCnt   = \p4c_query("SELECT COUNT(*) AS `cnt` FROM `ads_media`;", __FILE__, __LINE__);
        $dbAdsCount = (int)(\p4c_fetch_object($rsAdsCnt)->cnt ?? 0);

        $totalMoviesActualBytes = 0.0;
        $totalMoviesEstBytes    = 0.0;
        foreach ($movieFormats as $fmtVal) {
            $totalMoviesActualBytes += $fmtVal['bytes'];
            $totalMoviesEstBytes    += $fmtVal['est_bytes'];
        }

        $isLocalSimulation    = ($physicalMovieFoldersFound === 0 && $totalDbMovies > 0);
        $effectiveMoviesBytes = $isLocalSimulation ? $totalMoviesEstBytes : $totalMoviesActualBytes;

        $legacyWebmOgvBytes = $isLocalSimulation
            ? ($movieFormats['mobile_webm']['est_bytes'] + $movieFormats['mobile_ogv']['est_bytes'])
            : ($movieFormats['mobile_webm']['bytes'] + $movieFormats['mobile_ogv']['bytes']);

        $mobileMp4Bytes = $isLocalSimulation
            ? $movieFormats['mobile_mp4']['est_bytes']
            : $movieFormats['mobile_mp4']['bytes'];

        $cloudRootPath = MOVIES_PATH . '/' . MOVIES_DEFAULT_DIR;
        $diskCheckPath = is_dir($cloudRootPath) ? $cloudRootPath : MOVIES_PATH;

        // 4. Streaming & Download Traffic (Letzte 6 Monate)
        $sixMonthsAgo = date('Y-m-d H:i:s', strtotime('-6 months'));
        $rsAccess6m   = \p4c_query(
            "SELECT `movie_id`, `user_id`, `buy_as`, `buy_timestamp`, `access_token_datetime`,
                    GREATEST(
                        IFNULL(`buy_timestamp`, '0000-00-00 00:00:00'),
                        IFNULL(`access_token_datetime`, '0000-00-00 00:00:00')
                    ) AS `activity_date`
             FROM `movies_access`
             WHERE `buy_timestamp` >= '" . \p4c_escape_string($sixMonthsAgo) . "'
                OR `access_token_datetime` >= '" . \p4c_escape_string($sixMonthsAgo) . "'
             ORDER BY `activity_date` DESC;",
            __FILE__,
            __LINE__
        );

        $traffic6m = [
            'streaming_count' => 0,
            'download_count'  => 0,
            'total_count'     => 0,
            'unique_users'    => [],
            'unique_movies'   => [],
            'streaming_bytes' => 0.0,
            'download_bytes'  => 0.0,
            'total_bytes'     => 0.0,
            'streaming_sec'   => 0,
            'download_sec'    => 0,
        ];

        $monthlyBreakdown = [];
        for ($i = 0; $i < 6; $i++) {
            $mKey   = date('Y-m', strtotime("-$i months"));
            $mLabel = date('m / Y', strtotime("-$i months"));
            $monthlyBreakdown[$mKey] = [
                'month_key'       => $mKey,
                'month_label'     => $mLabel,
                'streaming_count' => 0,
                'download_count'  => 0,
                'streaming_bytes' => 0.0,
                'download_bytes'  => 0.0,
                'total_bytes'     => 0.0,
                'playtime_hours'  => 0.0,
            ];
        }

        while ($acc = \p4c_fetch_object($rsAccess6m)) {
            $fid       = (string)$acc->movie_id;
            $mSizeInfo = $movieDesktopSizes[$fid] ?? ['actual' => 0.0, 'estimated' => 0.0, 'playtime' => 0];
            $fileBytes = ($mSizeInfo['actual'] > 0) ? $mSizeInfo['actual'] : $mSizeInfo['estimated'];
            $playtimeS = (int)$mSizeInfo['playtime'];

            $traffic6m['total_count']++;
            if ((int)$acc->user_id > 0) {
                $traffic6m['unique_users'][(int)$acc->user_id] = true;
            }
            if ($fid !== '') {
                $traffic6m['unique_movies'][$fid] = true;
            }

            $monthKey = substr((string)$acc->activity_date, 0, 7);
            if (!isset($monthlyBreakdown[$monthKey])) {
                $monthlyBreakdown[$monthKey] = [
                    'month_key'       => $monthKey,
                    'month_label'     => date('m / Y', (int)strtotime((string)$acc->activity_date)),
                    'streaming_count' => 0,
                    'download_count'  => 0,
                    'streaming_bytes' => 0.0,
                    'download_bytes'  => 0.0,
                    'total_bytes'     => 0.0,
                    'playtime_hours'  => 0.0,
                ];
            }

            if ((string)$acc->buy_as === 'download') {
                $traffic6m['download_count']++;
                $traffic6m['download_bytes'] += $fileBytes;
                $traffic6m['download_sec']   += $playtimeS;
                $monthlyBreakdown[$monthKey]['download_count']++;
                $monthlyBreakdown[$monthKey]['download_bytes'] += $fileBytes;
            } else {
                $traffic6m['streaming_count']++;
                $traffic6m['streaming_bytes'] += $fileBytes;
                $traffic6m['streaming_sec']   += $playtimeS;
                $monthlyBreakdown[$monthKey]['streaming_count']++;
                $monthlyBreakdown[$monthKey]['streaming_bytes'] += $fileBytes;
            }

            $traffic6m['total_bytes']                         += $fileBytes;
            $monthlyBreakdown[$monthKey]['total_bytes']       += $fileBytes;
            $monthlyBreakdown[$monthKey]['playtime_hours']    += ($playtimeS / 3600.0);
        }

        ksort($monthlyBreakdown);
        $monthlyRows = [];
        foreach (array_reverse($monthlyBreakdown, true) as $mdata) {
            $monthlyRows[] = [
                'month_key'              => $mdata['month_key'],
                'month_label'            => $mdata['month_label'],
                'streaming_count'        => $mdata['streaming_count'],
                'download_count'         => $mdata['download_count'],
                'total_count'            => $mdata['streaming_count'] + $mdata['download_count'],
                'playtime_hours'         => number_format($mdata['playtime_hours'], 1, ',', '.') . ' Std.',
                'streaming_formatted'    => $this->formatBytes($mdata['streaming_bytes']),
                'download_formatted'     => $this->formatBytes($mdata['download_bytes']),
                'total_bytes'            => $mdata['total_bytes'],
                'total_formatted'        => $this->formatBytes($mdata['total_bytes']),
                'realistic_2x_formatted' => $this->formatBytes($mdata['total_bytes'] * 2.0),
            ];
        }

        $avgMonthlyBytes1x = $traffic6m['total_bytes'] / 6.0;
        $avgMonthlyBytes2x = $avgMonthlyBytes1x * 2.0;

        // 5. Cloud Provider Kosten-Kalkulation
        $totalStorageBytesNow     = $effectiveMoviesBytes + $photoAlbumsBytes + $adsBytes + $merchantBytes;
        $totalStorageBytesCleaned = max(0.0, $totalStorageBytesNow - $legacyWebmOgvBytes);

        $tbNow     = max(0.1, $totalStorageBytesNow / 1099511627776.0);
        $tbCleaned = max(0.1, $totalStorageBytesCleaned / 1099511627776.0);
        $tbTraffic = $avgMonthlyBytes2x / 1099511627776.0;

        $providers = [
            [
                'name'             => 'Backblaze B2 (Object Storage)',
                'rate_info'        => 'ca. 6,00 $ / TB Speicher | Traffic bis 3x Speichervolumen kostenlos',
                'adult_policy'     => 'Erlaubt (legaler Adult-Content / BRAM-konform)',
                'cost_now_eur'     => number_format(($tbNow * 6.0) + (max(0.0, $tbTraffic - ($tbNow * 3.0)) * 10.0), 2, ',', '.') . ' $ / Monat',
                'cost_cleaned_eur' => number_format(($tbCleaned * 6.0) + (max(0.0, $tbTraffic - ($tbCleaned * 3.0)) * 10.0), 2, ',', '.') . ' $ / Monat',
            ],
            [
                'name'             => 'OVHcloud (Object Storage Standard)',
                'rate_info'        => 'ca. 7,50 € / TB Speicher | Ausgehender Traffic (Egress) 0,00 €',
                'adult_policy'     => 'Erlaubt (EU-Standorte DE / FR)',
                'cost_now_eur'     => number_format($tbNow * 7.50, 2, ',', '.') . ' € / Monat',
                'cost_cleaned_eur' => number_format($tbCleaned * 7.50, 2, ',', '.') . ' € / Monat',
            ],
            [
                'name'             => 'Wasabi (Hot Cloud Storage)',
                'rate_info'        => 'ca. 6,99 $ / TB Speicher | Traffic kostenlos (Fair-Use bis 1x Speicher)',
                'adult_policy'     => 'Erlaubt (90 Tage Mindest-Speicherdauer beachten)',
                'cost_now_eur'     => number_format($tbNow * 6.99, 2, ',', '.') . ' $ / Monat',
                'cost_cleaned_eur' => number_format($tbCleaned * 6.99, 2, ',', '.') . ' $ / Monat',
            ],
            [
                'name'             => 'Bunny.net (Bunny Storage + Volume CDN)',
                'rate_info'        => 'ca. 10,00 $ / TB Speicher + 5,00 $ / TB Streaming-Traffic (inkl. SFTP)',
                'adult_policy'     => 'Explizit erlaubt (Spezialist für Adult-Streaming)',
                'cost_now_eur'     => number_format(($tbNow * 10.0) + ($tbTraffic * 5.0), 2, ',', '.') . ' $ / Monat',
                'cost_cleaned_eur' => number_format(($tbCleaned * 10.0) + ($tbTraffic * 5.0), 2, ',', '.') . ' $ / Monat',
            ],
        ];

        $durationMs = (int)round((microtime(true) - $startTime) * 1000);

        $response = [
            'from_cache'             => false,
            'cache_age_minutes'      => 0,
            'generated_at'           => date('d.m.Y H:i:s'),
            'duration_ms'            => $durationMs,
            'is_local_simulation'    => $isLocalSimulation,
            'physical_movie_folders' => $physicalMovieFoldersFound,
            'total_db_movies'        => $totalDbMovies,
            'disk'                   => $this->getDiskMetrics($diskCheckPath, $totalStorageBytesNow),
            'summary'                => [
                'total_cloud_formatted'       => $this->formatBytes($totalStorageBytesNow),
                'movies_total_formatted'      => $this->formatBytes($effectiveMoviesBytes),
                'legacy_savings_formatted'    => $this->formatBytes($legacyWebmOgvBytes),
                'legacy_with_m_mp4_formatted' => $this->formatBytes($legacyWebmOgvBytes + $mobileMp4Bytes),
                'cleaned_cloud_formatted'     => $this->formatBytes($totalStorageBytesCleaned),
            ],
            'categories' => [
                [
                    'name'          => 'Videos (cloud_storage/{merchant_id}/{movie_id}/)',
                    'db_info'       => number_format($totalDbMovies, 0, ',', '.') . ' Filme in DB (' . number_format($movieStatuses['online']['count'], 0, ',', '.') . ' online)',
                    'files_count'   => number_format(
                        $movieFormats['desktop_mp4']['count'] +
                        $movieFormats['mobile_mp4']['count'] +
                        $movieFormats['mobile_webm']['count'] +
                        $movieFormats['mobile_ogv']['count'] +
                        $movieFormats['thumbnails']['count'] +
                        $movieFormats['originals_other']['count'],
                        0,
                        ',',
                        '.'
                    ) . ' Dateien',
                    'actual_bytes'  => $totalMoviesActualBytes,
                    'actual_fmt'    => $this->formatBytes($totalMoviesActualBytes),
                    'est_fmt'       => $this->formatBytes($totalMoviesEstBytes),
                    'effective_fmt' => $this->formatBytes($effectiveMoviesBytes),
                ],
                [
                    'name'          => 'Fotoalben (cloud_storage/photo_albums/)',
                    'db_info'       => number_format($dbPhotoAlbumsCount, 0, ',', '.') . ' Alben (' . number_format($dbPhotoAlbumsOnlineCount, 0, ',', '.') . ' online, ' . number_format($dbPhotosCount, 0, ',', '.') . ' Fotos)',
                    'files_count'   => number_format($photoFilesCount, 0, ',', '.') . ' Dateien',
                    'actual_bytes'  => $photoAlbumsBytes,
                    'actual_fmt'    => $this->formatBytes($photoAlbumsBytes),
                    'est_fmt'       => $this->formatBytes($photoAlbumsBytes),
                    'effective_fmt' => $this->formatBytes($photoAlbumsBytes),
                ],
                [
                    'name'          => 'Werbebanner (cloud_storage/ads/)',
                    'db_info'       => number_format($dbAdsCount, 0, ',', '.') . ' Banner in DB',
                    'files_count'   => number_format($adsFilesCount, 0, ',', '.') . ' Dateien',
                    'actual_bytes'  => $adsBytes,
                    'actual_fmt'    => $this->formatBytes($adsBytes),
                    'est_fmt'       => $this->formatBytes($adsBytes),
                    'effective_fmt' => $this->formatBytes($adsBytes),
                ],
                [
                    'name'          => 'Händler- & Profilbilder (priv/merchant_files/)',
                    'db_info'       => 'Ausweise, Verifizierungen & Profilbilder',
                    'files_count'   => number_format($merchantFilesCount, 0, ',', '.') . ' Dateien',
                    'actual_bytes'  => $merchantBytes,
                    'actual_fmt'    => $this->formatBytes($merchantBytes),
                    'est_fmt'       => $this->formatBytes($merchantBytes),
                    'effective_fmt' => $this->formatBytes($merchantBytes),
                ],
            ],
            'formats' => [
                [
                    'type'          => 'Desktop Hauptvideo ({id}_{file_id}.mp4)',
                    'codec'         => 'H.264 (libx264, 8000 kbit/s, Originalauflösung)',
                    'usage_status'  => 'AKTIV GENUTZT (Streaming & Download)',
                    'badge_color'   => '#198754',
                    'count'         => $movieFormats['desktop_mp4']['count'],
                    'actual_fmt'    => $this->formatBytes($movieFormats['desktop_mp4']['bytes']),
                    'est_fmt'       => $this->formatBytes($movieFormats['desktop_mp4']['est_bytes']),
                    'effective_fmt' => $this->formatBytes($isLocalSimulation ? $movieFormats['desktop_mp4']['est_bytes'] : $movieFormats['desktop_mp4']['bytes']),
                ],
                [
                    'type'          => 'Mobile MP4 (m_{id}_{file_id}.mp4)',
                    'codec'         => 'H.264 (libx264, 1000 kbit/s, 640px Breite)',
                    'usage_status'  => 'INAKTIV (wird in movie_player.php ignoriert)',
                    'badge_color'   => '#fd7e14',
                    'count'         => $movieFormats['mobile_mp4']['count'],
                    'actual_fmt'    => $this->formatBytes($movieFormats['mobile_mp4']['bytes']),
                    'est_fmt'       => $this->formatBytes($movieFormats['mobile_mp4']['est_bytes']),
                    'effective_fmt' => $this->formatBytes($isLocalSimulation ? $movieFormats['mobile_mp4']['est_bytes'] : $movieFormats['mobile_mp4']['bytes']),
                ],
                [
                    'type'          => 'Mobile WebM (m_{id}_{file_id}.webm)',
                    'codec'         => 'VP8 / Vorbis (libvpx, 1000 kbit/s, 640px Breite)',
                    'usage_status'  => 'VERALTET / NUTZLOS (Kann gelöscht werden)',
                    'badge_color'   => '#dc3545',
                    'count'         => $movieFormats['mobile_webm']['count'],
                    'actual_fmt'    => $this->formatBytes($movieFormats['mobile_webm']['bytes']),
                    'est_fmt'       => $this->formatBytes($movieFormats['mobile_webm']['est_bytes']),
                    'effective_fmt' => $this->formatBytes($isLocalSimulation ? $movieFormats['mobile_webm']['est_bytes'] : $movieFormats['mobile_webm']['bytes']),
                ],
                [
                    'type'          => 'Mobile OGV (m_{id}_{file_id}.ogv)',
                    'codec'         => 'Theora / Vorbis (libtheora, 1000 kbit/s, 640px Breite)',
                    'usage_status'  => 'VERALTET / NUTZLOS (Kann gelöscht werden)',
                    'badge_color'   => '#dc3545',
                    'count'         => $movieFormats['mobile_ogv']['count'],
                    'actual_fmt'    => $this->formatBytes($movieFormats['mobile_ogv']['bytes']),
                    'est_fmt'       => $this->formatBytes($movieFormats['mobile_ogv']['est_bytes']),
                    'effective_fmt' => $this->formatBytes($isLocalSimulation ? $movieFormats['mobile_ogv']['est_bytes'] : $movieFormats['mobile_ogv']['bytes']),
                ],
                [
                    'type'          => 'Vorschaubilder & Covers (thumb_*.jpg / .png)',
                    'codec'         => 'JPEG / PNG (10 Auto-Thumbs + Custom FSK16/18)',
                    'usage_status'  => 'AKTIV GENUTZT (Vorschau & Player-Poster)',
                    'badge_color'   => '#198754',
                    'count'         => $movieFormats['thumbnails']['count'],
                    'actual_fmt'    => $this->formatBytes($movieFormats['thumbnails']['bytes']),
                    'est_fmt'       => $this->formatBytes($movieFormats['thumbnails']['est_bytes']),
                    'effective_fmt' => $this->formatBytes($isLocalSimulation ? $movieFormats['thumbnails']['est_bytes'] : $movieFormats['thumbnails']['bytes']),
                ],
                [
                    'type'          => 'Unkonvertierte Originale / Temporäre Dateien',
                    'codec'         => 'Rohdaten vor/während Konvertierung',
                    'usage_status'  => 'TEMPORÄR',
                    'badge_color'   => '#6c757d',
                    'count'         => $movieFormats['originals_other']['count'],
                    'actual_fmt'    => $this->formatBytes($movieFormats['originals_other']['bytes']),
                    'est_fmt'       => $this->formatBytes($movieFormats['originals_other']['est_bytes']),
                    'effective_fmt' => $this->formatBytes($movieFormats['originals_other']['bytes']),
                ],
            ],
            'statuses' => [
                [
                    'label'         => 'Filme Online (Veröffentlicht)',
                    'count'         => $movieStatuses['online']['count'],
                    'hours'         => number_format($movieStatuses['online']['playtime_sec'] / 3600.0, 1, ',', '.') . ' Std.',
                    'effective_fmt' => $this->formatBytes($isLocalSimulation ? $movieStatuses['online']['est_bytes'] : $movieStatuses['online']['bytes']),
                ],
                [
                    'label'         => 'Filme in Planung (Entwürfe)',
                    'count'         => $movieStatuses['drafts']['count'],
                    'hours'         => number_format($movieStatuses['drafts']['playtime_sec'] / 3600.0, 1, ',', '.') . ' Std.',
                    'effective_fmt' => $this->formatBytes($isLocalSimulation ? $movieStatuses['drafts']['est_bytes'] : $movieStatuses['drafts']['bytes']),
                ],
                [
                    'label'         => 'Abgelehnte & Gesperrte Filme',
                    'count'         => $movieStatuses['rejected']['count'],
                    'hours'         => number_format($movieStatuses['rejected']['playtime_sec'] / 3600.0, 1, ',', '.') . ' Std.',
                    'effective_fmt' => $this->formatBytes($isLocalSimulation ? $movieStatuses['rejected']['est_bytes'] : $movieStatuses['rejected']['bytes']),
                ],
                [
                    'label'         => 'Soft-Gelöschte Filme (in Schutzfrist)',
                    'count'         => $movieStatuses['deleted']['count'],
                    'hours'         => number_format($movieStatuses['deleted']['playtime_sec'] / 3600.0, 1, ',', '.') . ' Std.',
                    'effective_fmt' => $this->formatBytes($isLocalSimulation ? $movieStatuses['deleted']['est_bytes'] : $movieStatuses['deleted']['bytes']),
                ],
            ],
            'traffic' => [
                'streaming_count_6m'     => number_format($traffic6m['streaming_count'], 0, ',', '.'),
                'download_count_6m'      => number_format($traffic6m['download_count'], 0, ',', '.'),
                'total_count_6m'         => number_format($traffic6m['total_count'], 0, ',', '.'),
                'unique_users_6m'        => number_format(count($traffic6m['unique_users']), 0, ',', '.'),
                'unique_movies_6m'       => number_format(count($traffic6m['unique_movies']), 0, ',', '.'),
                'streaming_hours_6m'     => number_format($traffic6m['streaming_sec'] / 3600.0, 1, ',', '.') . ' Std.',
                'download_hours_6m'      => number_format($traffic6m['download_sec'] / 3600.0, 1, ',', '.') . ' Std.',
                'streaming_bytes_6m_fmt' => $this->formatBytes($traffic6m['streaming_bytes']),
                'download_bytes_6m_fmt'  => $this->formatBytes($traffic6m['download_bytes']),
                'total_bytes_6m_fmt'     => $this->formatBytes($traffic6m['total_bytes']),
                'avg_monthly_1x_fmt'     => $this->formatBytes($avgMonthlyBytes1x),
                'avg_monthly_2x_fmt'     => $this->formatBytes($avgMonthlyBytes2x),
                'monthly_rows'           => $monthlyRows,
            ],
            'providers' => $providers,
        ];

        @file_put_contents($cacheFile, json_encode($response, JSON_UNESCAPED_UNICODE));

        return $response;
    }

    /**
     * @param object $m
     * @return array<string, mixed>
     */
    private function inspectMovieFolder(object $m): array
    {
        $loc    = ((string)$m->storage_location !== '') ? (string)$m->storage_location : (string)MOVIES_DEFAULT_DIR;
        $folder = MOVIES_PATH . '/' . $loc . '/' . abs((int)$m->merchant_id) . '/' . abs((int)$m->id);

        $dbFilename          = trim((string)$m->filename);
        $expectedDesktopName = abs((int)$m->id) . '_' . $m->file_id . '.mp4';
        $desktopFilename     = ($dbFilename !== '') ? $dbFilename : $expectedDesktopName;
        $desktopFullPath     = $folder . '/' . $desktopFilename;

        $folderExists    = is_dir($folder);
        $desktopExists   = ($folderExists && is_file($desktopFullPath));
        $desktopSize     = $desktopExists ? (float)@filesize($desktopFullPath) : 0.0;
        $desktopRealpath = $desktopExists ? @realpath($desktopFullPath) : false;

        $protectedFiles      = [];
        $deletableFiles      = [];
        $thumbnailsCount     = 0;
        $thumbnailsBytes     = 0.0;
        $deletableTotalBytes = 0.0;

        if ($folderExists) {
            $items = @scandir($folder);
            if ($items !== false) {
                foreach ($items as $f) {
                    if ($f === '.' || $f === '..') {
                        continue;
                    }
                    $fpath = $folder . '/' . $f;
                    if (!is_file($fpath)) {
                        continue;
                    }

                    $fsize     = (float)@filesize($fpath);
                    $fRealpath = @realpath($fpath);
                    $lowerF    = strtolower($f);
                    $ext       = pathinfo($lowerF, PATHINFO_EXTENSION);

                    if (strpos($lowerF, 'thumb_') === 0) {
                        $thumbnailsCount++;
                        $thumbnailsBytes += $fsize;
                        $protectedFiles[] = [
                            'filename'       => $this->safeUtf8($f),
                            'full_path'      => $this->safeUtf8($fpath),
                            'size_bytes'     => $fsize,
                            'size_formatted' => $this->formatBytes($fsize),
                            'role'           => 'Vorschaubild / Cover (Geschützt)',
                        ];
                        continue;
                    }

                    $condStartsWithM    = (strpos($lowerF, 'm_') === 0);
                    $condValidExt       = in_array($ext, ['mp4', 'webm', 'ogv'], true);
                    $condRegexMatch     = (bool)preg_match('/^m_[0-9]+_[a-z0-9]+\.(mp4|webm|ogv)$/i', $f);
                    $condBelongsToId    = (strpos($lowerF, 'm_' . abs((int)$m->id) . '_') === 0);
                    $condNotDesktopName = (
                        $lowerF !== strtolower($desktopFilename) &&
                        $lowerF !== strtolower($expectedDesktopName)
                    );
                    $condDesktopIntact = (
                        $desktopExists === true &&
                        $desktopSize > 0 &&
                        $fRealpath !== false &&
                        $desktopRealpath !== false &&
                        $fRealpath !== $desktopRealpath
                    );
                    $condSizePlausible = ($ext !== 'mp4') ? true : ($fsize < $desktopSize);

                    if (
                        $condStartsWithM &&
                        $condValidExt &&
                        $condRegexMatch &&
                        $condBelongsToId &&
                        $condNotDesktopName &&
                        $condDesktopIntact &&
                        $condSizePlausible
                    ) {
                        $formatLabel = 'Mobile ' . strtoupper($ext);
                        if ($ext === 'mp4') {
                            $formatLabel = 'Mobile MP4 (640px, 1000k - ungenutzt)';
                        } elseif ($ext === 'webm') {
                            $formatLabel = 'Mobile WebM (VP8/Vorbis - veraltet)';
                        } elseif ($ext === 'ogv') {
                            $formatLabel = 'Mobile OGV (Theora/Vorbis - veraltet)';
                        }

                        $deletableFiles[] = [
                            'filename'       => $this->safeUtf8($f),
                            'full_path'      => $this->safeUtf8($fpath),
                            'extension'      => $ext,
                            'format_label'   => $formatLabel,
                            'size_bytes'     => $fsize,
                            'size_formatted' => $this->formatBytes($fsize),
                        ];
                        $deletableTotalBytes += $fsize;
                    } else {
                        $role = ($lowerF === strtolower($desktopFilename) || $lowerF === strtolower($expectedDesktopName))
                            ? 'Desktop-Hauptvideo (GESCHÜTZT - BLEIBT ERHALTEN)'
                            : 'Sonstige Datei (Geschützt)';
                        $protectedFiles[] = [
                            'filename'       => $this->safeUtf8($f),
                            'full_path'      => $this->safeUtf8($fpath),
                            'size_bytes'     => $fsize,
                            'size_formatted' => $this->formatBytes($fsize),
                            'role'           => $role,
                        ];
                    }
                }
            }
        }

        $onlineId = isset($m->online_id) && (int)$m->online_id > 0 ? (int)$m->online_id : 0;

        return [
            'movie_id'               => (int)$m->id,
            'online_id'              => $onlineId,
            'file_id'                => (string)$m->file_id,
            'merchant_id'            => (int)$m->merchant_id,
            'actor_id'               => (int)$m->actor_id,
            'actor_username'         => isset($m->actor_username) && (string)$m->actor_username !== '' ? $this->safeUtf8((string)$m->actor_username) : 'Unbekannt',
            'title'                  => $this->safeUtf8((string)$m->title),
            'status'                 => (string)$m->status,
            'resolution'             => (string)$m->resolution,
            'playtime_string'        => (string)$m->playtime_string,
            'folder_path'            => $this->safeUtf8($folder),
            'folder_exists'          => $folderExists,
            'desktop_filename'       => $this->safeUtf8($desktopFilename),
            'desktop_full_path'      => $this->safeUtf8($desktopFullPath),
            'desktop_exists'         => $desktopExists,
            'desktop_size_bytes'     => $desktopSize,
            'desktop_size_formatted' => $this->formatBytes($desktopSize),
            'thumbnails_count'       => $thumbnailsCount,
            'thumbnails_formatted'   => $this->formatBytes($thumbnailsBytes),
            'deletable_files'        => $deletableFiles,
            'deletable_count'        => count($deletableFiles),
            'deletable_total_bytes'  => $deletableTotalBytes,
            'deletable_total_fmt'    => $this->formatBytes($deletableTotalBytes),
            'protected_files'        => $protectedFiles,
            'can_delete_safely'      => ($desktopExists && $desktopSize > 0 && count($deletableFiles) > 0),
        ];
    }

    /**
     * @param object $m
     * @param array<string, mixed> $beforeInfo
     * @return array<string, mixed>
     */
    private function executeSafeMovieCleanup(object $m, array $beforeInfo): array
    {
        $desktopSizeBefore = (float)$beforeInfo['desktop_size_bytes'];
        $desktopRealpath   = @realpath((string)$beforeInfo['desktop_full_path']);

        $deletedLog = [];
        $failedLog  = [];
        $freedBytes = 0.0;

        foreach ($beforeInfo['deletable_files'] as $delItem) {
            $targetPath = (string)$delItem['full_path'];
            $targetName = (string)$delItem['filename'];
            $targetReal = @realpath($targetPath);

            if (
                strpos(strtolower(basename($targetPath)), 'm_') === 0 &&
                strtolower(basename($targetPath)) !== strtolower((string)$beforeInfo['desktop_filename']) &&
                $targetReal !== false &&
                $desktopRealpath !== false &&
                $targetReal !== $desktopRealpath &&
                is_file($targetPath)
            ) {
                if (@unlink($targetPath)) {
                    clearstatcache(true, $targetPath);
                    $stillExists  = file_exists($targetPath);
                    $deletedLog[] = [
                        'filename'       => $targetName,
                        'full_path'      => $targetPath,
                        'size_formatted' => $delItem['size_formatted'],
                        'size_bytes'     => $delItem['size_bytes'],
                        'verified_gone'  => !$stillExists,
                    ];
                    $freedBytes += (float)$delItem['size_bytes'];
                } else {
                    $failedLog[] = [
                        'filename'  => $targetName,
                        'full_path' => $targetPath,
                        'reason'    => 'unlink() fehlgeschlagen (Dateiberechtigungen prüfen)',
                    ];
                }
            } else {
                $failedLog[] = [
                    'filename'  => $targetName,
                    'full_path' => $targetPath,
                    'reason'    => 'Durch Sicherheitsbarriere blockiert',
                ];
            }
        }

        clearstatcache();
        $afterInfo = $this->inspectMovieFolder($m);

        $desktopVerifiedIntact = (
            $afterInfo['desktop_exists'] === true &&
            (float)$afterInfo['desktop_size_bytes'] === $desktopSizeBefore &&
            (float)$afterInfo['desktop_size_bytes'] > 0
        );

        return [
            'deleted_log'             => $deletedLog,
            'failed_log'              => $failedLog,
            'freed_bytes'             => $freedBytes,
            'after_info'              => $afterInfo,
            'desktop_verified_intact' => $desktopVerifiedIntact,
        ];
    }

    private function scanDirBytes(string $dir, int &$fileCount = 0): float
    {
        $size = 0.0;
        if (!is_dir($dir)) {
            if (is_file($dir)) {
                $fileCount++;
                return (float)@filesize($dir);
            }
            return 0.0;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return 0.0;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'lost+found') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $size += $this->scanDirBytes($path, $fileCount);
            } elseif (is_file($path)) {
                $fileCount++;
                $size += (float)@filesize($path);
            }
        }
        return $size;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDiskMetrics(string $diskCheckPath, float $fallbackUsedBytes = 0.0): array
    {
        $diskTotal = 0.0;
        $diskFree  = 0.0;
        $disabled  = array_map('trim', explode(',', strtolower((string)@ini_get('disable_functions'))));

        if (function_exists('disk_total_space') && !in_array('disk_total_space', $disabled, true)) {
            $t = @disk_total_space($diskCheckPath);
            if ($t === false || $t <= 0) {
                $rp = @realpath($diskCheckPath);
                if ($rp) {
                    $t = @disk_total_space($rp);
                }
            }
            if ($t !== false && $t > 0) {
                $diskTotal = (float)$t;
            }
        }

        if (function_exists('disk_free_space') && !in_array('disk_free_space', $disabled, true)) {
            $f = @disk_free_space($diskCheckPath);
            if ($f === false || $f <= 0) {
                $rp = @realpath($diskCheckPath);
                if ($rp) {
                    $f = @disk_free_space($rp);
                }
            }
            if ($f !== false && $f > 0) {
                $diskFree = (float)$f;
            }
        }

        if ($diskTotal <= 0 && function_exists('exec') && !in_array('exec', $disabled, true)) {
            $dfOut = [];
            $dfRet = 1;
            @exec('df -P -k ' . escapeshellarg($diskCheckPath) . ' 2>/dev/null', $dfOut, $dfRet);
            if ($dfRet === 0 && isset($dfOut[1])) {
                $parts = preg_split('/\s+/', trim($dfOut[1]));
                if (is_array($parts) && count($parts) >= 4 && is_numeric($parts[1]) && (float)$parts[1] > 0) {
                    $diskTotal = (float)$parts[1] * 1024.0;
                    if ($diskFree <= 0 && is_numeric($parts[3])) {
                        $diskFree = (float)$parts[3] * 1024.0;
                    }
                }
            }
        }

        if ($diskTotal <= 0 && $diskFree > 0 && $fallbackUsedBytes > 0) {
            $diskUsed  = $fallbackUsedBytes;
            $diskTotal = $diskUsed + $diskFree;
        } else {
            $diskUsed = ($diskTotal > 0) ? max(0.0, $diskTotal - $diskFree) : 0.0;
        }

        $diskUsedPercent = ($diskTotal > 0) ? round(($diskUsed / $diskTotal) * 100, 1) : 0.0;

        return [
            'path'            => $diskCheckPath,
            'total_formatted' => $this->formatBytes($diskTotal),
            'used_formatted'  => $this->formatBytes($diskUsed),
            'free_formatted'  => $this->formatBytes($diskFree),
            'used_percent'    => $diskUsedPercent,
        ];
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

    private function safeUtf8(string $str): string
    {
        if ($str === '') {
            return '';
        }
        if (!mb_check_encoding($str, 'UTF-8')) {
            return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1, WINDOWS-1252, ASCII');
        }
        return $str;
    }
}

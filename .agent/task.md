# 📋 Antigravity Task List - EroCloud

## 🚀 Active Project: Initial Setup, Video Management & System Maintenance Refactoring

- [x] **Project Initialization**
    - [x] Create `.agent/` directory structure
    - [x] Define master rules (`.agent/rules/master.md`)
    - [x] Define documentation standards (`.agent/rules/documentation-standards.md`)
    - [x] Define agent permissions (`.agent/rules/permissions.md`)
    - [x] Define GitHub auto-commit rules (`.agent/rules/github-auto-commit.md`)
    - [x] Establish knowledge directories (`.agent/knowledge/`)

- [x] **Video Management Redesign & Refactoring**
    - [x] Refactor Creator Movie Edit (`mcp/includes/movie.php`) to Bootstrap 5 responsive 2-column grid
    - [x] Refactor Creator Movie Upload (`mcp/includes/movie_upload.php`) to Bootstrap 5 responsive 2-column grid
    - [x] Implement dynamic green progress wizard (`bg-success text-white shadow-sm`) for upload & edit
    - [x] Fix wizard step badges to exact 24x24px perfect circles across all steps (`movie_upload.php` & `movie.php`)
    - [x] Redesign Step 2 and Step 3 of Movie Upload to Bootstrap 5
    - [x] Fix `submit_step1` form submit issue and enforce 1 subcategory minimum in consolidated Sie-Form alert box
    - [x] Implement Bootstrap Accordion category selection & system-detection yellow transparency highlighting (`#fff6d0`)
    - [x] Decouple ACP movie checking (`acp/includes/movie_checking.php`) from auto-categorization
    - [x] Add quality and rendering tips modals (`#modalMovieTips`, `#modalMovieRenderingTips`)
    - [x] Restrict max container width to 1600px (left-aligned) across MCP movie pages
    - [x] Remove obsolete "alle Partnerwebsites" option from website visibility dropdowns
    - [x] Update architecture documentation (`.agent/knowledge/architecture/video-management.md`)

- [x] **Error Log Cleanup & Image Processing Fixes**
    - [x] Analyze 322,500+ log lines and group error causes
    - [x] Fix GD image processing in `api/album_photo_thumb.php` (resolved `imagecolorat()` out of bounds and `altesBild` GD resource notices)
    - [x] Implement detailed error logging (`[EroCloud Photo Error]`) for missing/corrupted photo files without deleting any files
    - [x] Clean up HTML log dumps in `cronjobs/import_conversions.php` (strip HTML tags, truncate response to max 250 characters)
    - [x] Refactor image handlers from `Imagick` to GD library (`api/movie_poster.php`, `api/photo_album_poster.php`, `mcp/thumb.php`, `mcp/includes/show_album_photo.php`)
    - [x] Implement output buffer purging (`while (ob_get_level() > 0) ob_end_clean()`) and immediate `exit;` to guarantee clean binary image streams
    - [x] Fix SQL error in `photo_album_poster.php` (removed invalid `file_id` column, added `photo_albums_online` table fallback)
    - [x] Fix DataTables JSON parse error in `acp/includes/ajax/movies_checking.php` & `movies_online.php` with safe UTF-8 sanitization
    - [x] Resolve `headers_sent()` session warnings in `includes/functions.inc.php` and `mcp/common.inc.php`
    - [x] Fix undefined variable `$is_first_check` notice in `acp/includes/movie_checking.php`

- [x] **Deactivation of Legacy Services & Menu Cleanups**
    - [x] Deactivate Messenger (`https://erocloud.net/Messenger` -> `mcp/messenger/index.php`) with Bootstrap 5 shutdown notice (July 2026) and `exit;`
    - [x] Deactivate Gruppen (`/Groups`), Umsätze (`/Statistics/...`), Partnerprogramm (`/Webmaster/...`), and 09005-Hotline (`/Hotline`) with Bootstrap 5 shutdown notices in polite Sie-Form
    - [x] Clean up MCP sidebar navigation menu (`mcp/index.php`): removed links for Messenger, 09005-Hotline, Gruppen, Umsätze, and Partnerprogramm

- [x] **ACP Storage & Traffic Analytics (`/Speicher-Analyse`) & Conversion Architecture**
    - [x] Research physical storage structure (`MOVIES_PATH`, `MOVIES_DEFAULT_DIR`, `storage_location`), FFmpeg conversion formats (`cronjobs/convert_movie.php`), and player delivery (`api/get_movie_url.php`, `api/movie_player.php`)
    - [x] Add route `/Speicher-Analyse` (`acp/.htaccess`) and sidebar navigation entry *"Speicher & Traffic"* under *"Content Online"* (`acp/index.php`)
    - [x] Implement backend scanner `acp/includes/ajax/storage_analytics_scan.php` with 24h JSON cache, physical `filesize()` + local bitrate-fallback mode, video format breakdown (`desktop_mp4`, `mobile_mp4`, `mobile_webm`, `mobile_ogv`, `thumbnails`), and 6-month `movies_access` streaming/download traffic calculator
    - [x] Implement 3-stage server partition check (`sa_get_disk_metrics`) with `df -P -k` and physical sum + `disk_free_space()` fallback when `disk_total_space()` is disabled in `php.ini`
    - [x] Build ACP frontend `acp/includes/storage_analytics.php` with 3 jQuery-UI tabs (Speicher-Übersicht & Video-Formate, Streaming & Traffic-Analyse, Konvertierungs-Analyse & Cloud-Kalkulator) in polite Sie-Form
    - [x] Document storage architecture, FFmpeg conversion pipeline, and redundant mobile format findings in `.agent/knowledge/architecture/video-management.md` and `.agent/knowledge/architecture/storage-and-traffic-analytics.md`
    - [x] Remove redundant `m_*.mp4`, `m_*.webm`, and `m_*.ogv` FFmpeg conversion steps in `cronjobs/convert_movie.php`
    - [x] Align `api/get_movie_url.php` (EroCloud) and `mobi/modules/videothek/video.php` (EroCMS) to use Desktop-MP4 (`movie_url`)
    - [x] Implement 7-stage safe Single-Video & Controlled Batch Cleanup (`10`/`50`/`100`/Auto-Run) for `m_*.mp4`, `m_*.webm`, and `m_*.ogv` in ACP `/Speicher-Analyse` (`acp/includes/storage_analytics.php` & `acp/includes/ajax/storage_analytics_legacy_cleanup.php`)

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

- [x] **ACP Storage & Traffic Analytics (`/Speicher-Analyse`), Conversion & Streaming Modernization**
    - [x] Research physical storage structure (`MOVIES_PATH`, `MOVIES_DEFAULT_DIR`, `storage_location`), FFmpeg conversion formats (`cronjobs/convert_movie.php`), and player delivery (`api/get_movie_url.php`, `api/movie_player.php`)
    - [x] Add route `/Speicher-Analyse` (`acp/.htaccess`) and sidebar navigation entry *"Speicher & Traffic"* under *"Content Online"* (`acp/index.php`)
    - [x] Implement backend scanner `acp/includes/ajax/storage_analytics_scan.php` with 24h JSON cache, physical `filesize()` + local bitrate-fallback mode, video format breakdown (`desktop_mp4`, `mobile_mp4`, `mobile_webm`, `mobile_ogv`, `thumbnails`), and 6-month `movies_access` streaming/download traffic calculator
    - [x] Implement 3-stage server partition check (`sa_get_disk_metrics`) with `df -P -k` and physical sum + `disk_free_space()` fallback when `disk_total_space()` is disabled in `php.ini`
    - [x] Build ACP frontend `acp/includes/storage_analytics.php` with 3 jQuery-UI tabs (Speicher-Übersicht & Video-Formate, Streaming & Traffic-Analyse, Konvertierungs-Analyse & Cloud-Kalkulator) in polite Sie-Form
    - [x] Document storage architecture, FFmpeg conversion pipeline, and redundant mobile format findings in `.agent/knowledge/architecture/video-management.md` and `.agent/knowledge/architecture/storage-and-traffic-analytics.md`
    - [x] Remove redundant `m_*.mp4`, `m_*.webm`, and `m_*.ogv` FFmpeg conversion steps in `cronjobs/convert_movie.php`
    - [x] Align `api/get_movie_url.php` (EroCloud) and `mobi/modules/videothek/video.php` (EroCMS) to use Desktop-MP4 (`movie_url`)
    - [x] Implement 7-stage safe Single-Video & Controlled Batch Cleanup (`10`/`50`/`100`/Auto-Run) for `m_*.mp4`, `m_*.webm`, and `m_*.ogv` in ACP `/Speicher-Analyse` (`acp/includes/storage_analytics.php` & `acp/includes/ajax/storage_analytics_legacy_cleanup.php`)
    - [x] Modernize FFmpeg conversion (`cronjobs/convert_movie.php`) with resolution-adaptive Constrained CRF (`-crf 23`), 9:16 portrait video support, and fault-tolerant post-conversion metadata recovery
    - [x] Modernize `includes/klassen/VideoStream.inc.php`, `api/get_movie_url.php`, and `api/movie_player.php` with 512 KB chunk streaming, session lock release, 6h persistent tokens, and Smart-TV / AirPlay / Chromecast CORS & RFC 7233 support

- [ ] **Local AI Compliance & Subtitle Pipeline (Whisper.cpp + Llama.cpp)**
    - [x] Analyze server hardware (Dell PowerEdge R720, 2x Xeon E5-2690 v2, 64 GB RAM, Debian 10) and define resource-safe CPU inference architecture
    - [x] Document 4-phase concept, VTT normalizer, and translation architecture in `.agent/knowledge/architecture/ai-compliance-and-subtitle-pipeline.md`
    - [x] Document complete Debian 10 server setup, compiler patches & LiveConfig runbook in `.agent/knowledge/architecture/ai-server-installation-guide.md`
    - [x] **Phase 1: ACP Single-Video Test Lab (`/KI-Analyse`), BRAM Rulebook & Database Setup**
        - [x] Research Visa GBPP & Mastercard BRAM (AN 5196) + StGB compliance rules and build 2-stage AI rulebook (`includes/ai_rules/bram_system_prompt.md` & `includes/ai_rules/bram_keyword_rules.json`)
        - [x] Refine business logic: classify KV/Scat, NS/Watersports, Poppers, and Hypnose as **GELB (`warning`)** for partner portals without credit card billing, while keeping CSAM, Non-Consensual, Incapacitation/KO-Drops, Real Incest, Extreme Violence/Blood/Breathplay, and Bestiality as **ROT (`violation` / universal hard-block)**
        - [x] Auto-create database table `movies_ai_analysis` for tracking subtitle, transcript, text-compliance, and vision states across all 5,500+ movies
        - [x] Build ACP Test Cockpit (`acp/includes/ai_compliance.php` & `acp/includes/ajax/ai_compliance_action.php` under `/KI-Analyse`) to select a single Creator video and test audio extraction, Whisper subtitle/language detection, and BRAM text checking step by step
    - [x] **Phase 2A: Debian 10 Server Setup (`whisper.cpp`), VTT Quality Normalizer & Background Cronjob (`cronjobs/ai_compliance_worker.php`)**
        - [x] Configure Debian 10 Buster EOL archive repositories (`archive.debian.org`) and compile `whisper.cpp` natively (`-DGGML_NATIVE=ON -DBUILD_SHARED_LIBS=OFF` + `-lstdc++fs`) for Dual Xeon E5-2690 v2 in `/var/www/web1/priv/ai/`
        - [x] Download and verify `ggml-small-q5_1.bin` (~3.2s/chunk, 430 MB RAM) and `ggml-large-v3-turbo-q5_0.bin`
        - [x] Extract shared pipeline core (`includes/ai_compliance_core.inc.php`) with LiveConfig `open_basedir` fallback (`aic_file_or_exec_exists`), HTML entity decoding (`aic_clean_html_text`), and silent/missing-MP4 resilience
        - [x] Implement background worker (`cronjobs/ai_compliance_worker.php`), ACP Batch & Cronjob Control Center (Tab 2), 15-second Catalog Metadata Quick-Scan, Live Browser Batch with 2-second status poller, and History Filtering (Tab 4)
        - [x] Implement European Whisper language whitelist (`aic_get_allowed_whisper_languages`) with automatic fallback retry, VTT atmosphere tag normalizer (`[Musik]`, `[Stöhnen]`, `[Schweres Atmen]`, `[Lachen]`, `[Klatschen]`), anti-flicker consecutive range merging (`aic_clean_and_normalize_segments`), and Tab 5 (`.vtt` Transkript-Inspektor with clipboard/JSON export & 1-click bulk cleanup/requeue)
    - [x] **Phase 3: ACP Movie Checking (`acp/includes/movie_checking.php`), Movie Edit (`acp/includes/movie_edit.php`) & DataTables Integration**
        - [x] Add BRAM Risk-Score badges (`aic_render_bram_score_badge`, including `Nur Text` info badge when audio is still pending) to `/Filme-online` and `/Filme-pruefen` DataTables
        - [x] Integrate Top-Compliance-Banner and dedicated jQuery-UI tabs (`BRAM & KI-Analyse` and `Audio & Transkript (.vtt)`) into `/Film-pruefen/{id}` and `/Film-bearbeiten/{id}` with automatic recalculation on save
    - [ ] **Phase 2B: Optional Local Text LLM (`llama.cpp` Server + Uncensored GGUF Model) for Semantic Context Checking & Multi-Language Text/VTT Translation**
    - [ ] **Phase 4: Separate Video Frame / Sprite-Grid Vision AI Analysis**

- [x] **MCP Complete Modernization (`mcp_neu` -> KKC `app/` & `web/` Architecture)**
    - [x] Analyze Pay4Coins SSO login (`mcp/login.php`) and MCP routing (`mcp/.htaccess`, `mcp/index.php`)
    - [x] Document architecture blueprint (`app/` & `web/` separation, SPA fragment loading, Pay4Coins-Blue theme) in `.agent/knowledge/architecture/mcp-modernization-architecture.md`
    - [x] Build clickable interactive layout prototype in `mcp/mcp_neu/app/` and `mcp/mcp_neu/web/` with all current MCP sidebar menu entries, KKC tile design in Pay4Coins-Blue (`#0284C7` / `#4297D7`), and zero-reload SPA navigation
    - [x] Implement live Pay4Coins SSO login (`mcp/mcp_neu/web/login.php`), live MCP session authentication (`is_logged_in('mcp')`), and database-backed `Startseite` dashboard (`HomeController` & `views/dashboard.php`)
    - [x] Implement live `Profile / Actors` module (`ActorsController`, `views/list.php`, `views/form.php`) with live username check, Steckbrief editor, `actor_checksum()` sync, and AJAX FSK16/FSK18 profile image upload
    - [x] Implement `Movies` (`#/movies`, `#/movie`, `#/movie-upload`) and `Photo-Albums` (`#/photo-albums`, `#/photo-album`, `#/photo-album-upload`) modules in `mcp_neu`
    - [x] Implement live `FAQ` module (`#/faq`) and finalize `mcp_neu` production readiness
    - [x] **Performer & Co-Actor Verification + Digital Model Release System („Drehpartner & ID-Check“)**
        - [x] Implement 100% local ISO/IEC 18004 QR-Code SVG generator (`includes/klassen/QrCodeSvg.inc.php`)
        - [x] Implement `CoActorManager` (`includes/klassen/CoActorManager.inc.php`) with self-healing tables (`co_actors`, `content_co_actors`), AES encryption, mandatory owner self-entry (`is_owner = 1`), category plausibility checks, and printable/PDF consent certificate generator
        - [x] Build mobile-first QR self-service verification web app (`mcp/mcp_neu/web/verify_co_actor.php`) with dual selfie capture (`face_ref_image` for future Vision-AI + `id_shot_image`), ID front/back capture, client-side image compression, and resize-safe touch signature canvas
        - [x] Implement protected media & certificate proxy (`mcp/mcp_neu/web/co_actor_media.php`)
        - [x] Build dedicated MCP module `#/co-actors` (`CoActorsController`, `views/co_actors/index.php`, `views/co_actors/_quick_modal.php` with 4s live polling) and integrate into movie upload/edit (`views/movies/_performer_section.php` & `MoviesController`)
        - [x] Integrate ACP performer verification box, single-performer approve/reject, and publish protection into `acp/includes/movie_checking.php` and `acp/includes/movie_edit.php`
        - [x] Document complete architecture and workflows in `.agent/knowledge/architecture/co-actor-verification-and-model-release.md`

- [x] **ACP Complete Modernization (`acp_neu` -> Live Switch on `acp.erocloud.net` & PHP 8.4.27)**
    - [x] Build complete SPA ACP v2.0 (`acp/acp_neu/app/` & `acp/acp_neu/web/`) across all 14 core modules (`Home`, `MoviesChecking`, `PhotoAlbumsChecking`, `CoActorsChecking`, `AiCompliance`, `MoviesOnline`, `PhotoAlbumsOnline`, `ContentCleanup`, `StorageAnalytics`, `Actors`, `Merchants`, `Users`, `Websites`, `Affiliate`, `Employees`, `SystemTech`)
    - [x] Run 5-stage Deep-Logic-Audit (`Router`, `258` `p4c_query` calls, `208` `EXPLAIN` queries, Controller/View renders, JS handler cross-check) for 100% PHP 8.0.30 & PHP 8.4.27 compatibility
    - [x] Configure clean notice pages for `#/webcams` and `#/pointssystem` (`PreviewController`) and clean up sidebar badges (hide `0` badges; remove badges on *Content Online*, *Filme online*, *Fotoalben online*, and *Mitarbeiter & Admins*)
    - [x] Implement global Promise-based modal dialog system (`#ec-global-modal` with `App.dialog`, `App.confirm`, `App.prompt`, `App.alert` in variants `info`, `success`, `warning`, `danger`) in both ACP (`acp_neu`) and MCP (`mcp_neu`), replacing all native `confirm()`/`prompt()` dialogs (including optional `.errorlog.bak` backup checkbox on `#/errors`)
    - [x] Generate multi-resolution favicons (`favicon.ico`, `16x16`, `32x32`, `96x96`, `apple-touch-icon.png`) from `erocloud_logo.png` and integrate into ACP and MCP
    - [x] Switch `acp.erocloud.net` in LiveConfig to `/htdocs/acp/acp_neu/web` on **PHP `8.4.27`** (`php-8.4-opt`) with `.acp_htpasswd` & IP whitelist protection in `acp/acp_neu/web/.htaccess` and clean `/Startseite#/merchants` return redirect from MCP impersonation

- [ ] **MCP Live Switch (`erocloud.net` -> `/htdocs/mcp/mcp_neu/web` on PHP `8.4.27`) & Guest Landingpage Modernization**
    - [x] Implement self-contained PHP 8.4 media & streaming proxies (`mcp/mcp_neu/web/thumb.php` & `mcp/mcp_neu/web/media.php`) for `/thumb.php`, `/ProfilePicture/...`, `/AlbumPhoto/...`, `/Player/...`, `/PlayerPoster/...`, and `/amoredea/RedirectContent/...`
    - [x] Modernize `api/movie_downloader.php`, `api/photo_album_downloader.php`, and `api/messenger_image_info.php` to UTF-8 with self-contained inline styling (no legacy `mcp/fw/jquery-ui` dependency)
    - [x] Configure `mcp/mcp_neu/web/.htaccess` (Pay4Coins CORS + Credentials, 301 `/mcp_neu/web/*` QR/Magic-Link compatibility, root logo/favicon aliases, media rewrites, and legacy MCP bookmark redirects to SPA routes) plus fallback `mcp/.htaccess`
    - [x] Remove `/mcp_neu/web` subpaths from `CoActorManager`, `EroCloudMagicLink`, `EroCloudMailer`, `EroCloudNotificationCenter`, mail templates, `SystemTechController`, and `HomeController`
    - [x] Enable productive Creator email delivery (`MAIL_SANDBOX_MODE = false` in `config.inc.php`), add `SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'` zero-date DDL safeguard, and run 7-stage Deep-Logic-Audit on `mcp_neu`
    - [x] Switch `erocloud.net` in LiveConfig to `/htdocs/mcp/mcp_neu/web` on **PHP `8.4.27`** (`php-8.4-opt`)
    - [x] Fix Pay4Coins SSO account switching (`MCP_SKIP_AUTO_SESSION_RECOVERY` + `session_regenerate_id`), complete dual-cookie logout cleanup (`erocloud_mcp_auth` + `erocloud`), and explicit ID/PID precedence in `Merchant::__construct()`
    - [ ] Step 2: Redesign & modernize public Guest Landingpage on `erocloud.net` (`Home/views/guest.php` in Pay4Coins-Blue tile design & Du-Form)

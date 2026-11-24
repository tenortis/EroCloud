---
name: erocms-api-import
description: Vollstaendige Dokumentation ueber die EroCloud-API (api.erocloud.net) zur Anbindung, Synchronisation und Auslieferung von Filmen UND Fotoalben an externe EroCMS-Seiten.
usage: 'Referenz fuer Schnittstellen-Entwicklung, API-Debugging, Cache-Invalidierung und Fehleranalyse bei Film- und Fotoalben-Importen.'
---

# 🔌 EroCMS API & Content-Import (Filme & Fotoalben)

> **Kontext-Referenz:** Ergänzt `@master.md`, `@video-management.md`, `@movie-deletion-lifecycle.md` und `@photo-album-management-and-lifecycle.md`.

Dieses Dokument beschreibt die technische Funktionsweise der zentralen API-Schnittstelle unter `https://api.erocloud.net` ([`api/index.php`](file:///c:/xampp/htdocs/EroCloud/api/index.php)), über die externe **EroCMS**-Webseiten Creator-Videos und Fotoalben abfragen, synchronisieren, abrechnen und für Endkunden ausliefern.

---

## 1. 🛡️ Authentifizierung & Zeichenkodierung (`utf8mb4`)

* **API-Key-Validierung:** Der API-Key ist in `merchants.api_key` verschlüsselt gespeichert (`AES_DECRYPT(api_key, 'AES_KEY')`) und wird in `check_api_key_exists($api_key)` ([`api/index.php`](file:///c:/xampp/htdocs/EroCloud/api/index.php)) geprüft sowie zur DB-Entlastung in `$_SESSION['api_key']` gecached.
* **Ausgabeformat:** Standardmäßig XML über `print_xml($api)` aus [`includes/functions.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/functions.inc.php) (bzw. JSON wenn `output_format=json`, wie vom EroCMS über `get_erocloud_result()` in `EroCMS/erocloud_api/config.inc.php` angefordert).
* **Zwingende `utf8mb4`-Kette (4-Byte-Emojis):** Da MCP v2.0 & ACP v2.0 echte UTF-8-Emojis (z. B. `👠🔥`) in Titeln, Beschreibungen und Meta-Feldern speichern, liefert `api.erocloud.net` volle 4-Byte-UTF-8-Strings aus. Alle konsumierenden EroCMS-Skripte (`EroCMS/erocloud_api/*.php`, `EroCMS/includes/cronjobs/import_*.php`) **müssen** zwingend mit `SET NAMES 'utf8mb4'` arbeiten (niemals `SET NAMES 'utf8'`, da MySQL `utf8mb3` sonst mit `1366 Incorrect string value` abbricht!).

---

## 2. 🎥 Film-Endpunkte (`movies_online`)

### A. Filmliste abrufen (`get_movies`)
* **Datei:** [`api/includes/get_movies.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/get_movies.inc.php)
* **Request:** `GET /index.php?api_name=get_movies&api_key={KEY}&domain={DOMAIN}&actor_id={ACTOR_ID}`
* **Domain-neutraler Roh-Cache (`cache_v = 2`):**
  * Speichert die **ungefilterte Rohliste** des Darstellers (`raw_status` + `domain` = `visible_for_website`) für **50 Minuten** unter `api/temp/get_movies_by_actor_id/{ACTOR_ID}.tmp`. Legacy-Cache-Dateien ohne `cache_v === 2` werden beim nächsten Zugriff automatisch gelöscht (`@unlink`).
  * **KRITISCH (Kein Cross-Portal Cache-Poisoning):** Der effektive `status` in der API-Antwort darf **niemals** portalabhängig in die `.tmp`-Datei geschrieben werden, da derselbe Darsteller (`actor_id`) stündlich von mehreren EroCMS-Portalen abgefragt wird. Stattdessen wird bei **jedem** Request (auch aus warmem `.tmp`-Cache) dynamisch geprüft: Ist `visible_for_website === 'public'` oder entspricht die normalisierte `domain` (kleingeschrieben, ohne `www.` und ohne Trailing-Slash) exakt `visible_for_website`, wird `raw_status` ausgeliefert – andernfalls `'deleted'`.

### B. Filmdetails abrufen (`get_movie`)
* **Datei:** [`api/includes/get_movie.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/get_movie.inc.php)
* **Request:** `GET /index.php?api_name=get_movie&api_key={KEY}&domain={DOMAIN}&movie_id={FILE_ID}`
* **Cache & Pflicht-Invalidierung:**
  * Speichert den neutralen Rohdatensatz (inkl. `visible_for_website` und `raw_status`) für **50 Minuten** unter `api/temp/get_movie/{FILE_ID}.tmp` (Cleanup > 7 Stunden; Legacy-Dateien ohne `raw_status` werden verworfen) und löst `status` sowie `domain` bei jedem Aufruf dynamisch gegen die anfragende `$domain` auf.
  * **PFLICHT:** Jede Freigabe, Bearbeitung, Darsteller-Umhängung oder Löschung eines Online-Films **muss** `ec_invalidate_movie_api_cache($fileId, $actorId)` aus [`includes/functions.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/functions.inc.php) aufrufen, um sowohl `{FILE_ID}.tmp` als auch `{ACTOR_ID}.tmp` sofort zu invalidieren.
* **Checksummen-Funktion (`movie_checksum()`):** Berechnet in [`includes/functions.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/functions.inc.php) den MD5-Hash über Metadaten, Preise, Cover **sowie** (sofern `status != 'active'`) das Suffix `|{status}`, sodass reine Statusänderungen (`blocked` / `deleted`) sofort einen Delta-Sync auf externen EroCMS-Portalen auslösen (für `'active'` bleibt der Hash zu 100 % abwärtskompatibel).

### C. Film-Kauf & Streaming-/Download-Auslieferung
* **Kauf:** [`api/buy_movie_erocms.php`](file:///c:/xampp/htdocs/EroCloud/api/buy_movie_erocms.php) $\rightarrow$ schreibt `movies_access`.
* **Token & Player/Downloader:** [`api/get_movie_url.php`](file:///c:/xampp/htdocs/EroCloud/api/get_movie_url.php) validiert `user_streaming_key` gegen `movies_access` und generiert ein **30 Minuten** gültiges Einweg-`access_token` für `/Player/...` bzw. `/Downloader/...`.

---

## 3. 📸 Fotoalben-Endpunkte (`photo_albums_online`)

Vollständige Details zum Lebenszyklus siehe `@photo-album-management-and-lifecycle.md`.

### A. Fotoalben-Liste eines Darstellers (`get_photo_albums`)
* **Datei:** [`api/includes/get_photo_albums.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/get_photo_albums.inc.php)
* **Request:** `GET /index.php?api_name=get_photo_albums&api_key={KEY}&domain={DOMAIN}&actor_id={ACTOR_ID}`
* **Verhalten:** Liest **direkt und ohne Datei-Cache** `checksum, album_id, actor_id, online_at, status` aus `photo_albums_online`.
* **WICHTIGE LÖSCH-REGEL:** Damit externe EroCMS-Seiten erfahren, dass ein Album gelöscht oder gesperrt wurde (`<status>deleted</status>` bzw. `<status>blocked</status>`), **muss** die Zeile beim Soft-Delete (und als 30-Tage-Tombstone auch beim Admin-Hard-Delete) in `photo_albums_online` verbleiben!

### B. Fotoalbum-Details abrufen (`get_photo_album`)
* **Datei:** [`api/includes/get_photo_album.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/get_photo_album.inc.php)
* **Request:** `GET /index.php?api_name=get_photo_album&api_key={KEY}&domain={DOMAIN}&album_id={ALBUM_HASH}`
* **Cache & Pflicht-Invalidierung:**
  * Speichert das JSON-Ergebnis für **50 Minuten** unter `api/temp/get_photo_album/{ALBUM_HASH}.tmp`.
  * **PFLICHT:** Jede Änderung an einem Online-Fotoalbum (Freigabe, Bearbeitung, Statuswechsel auf `blocked`/`deleted`, Hard-Delete, Foto-Rotation) **muss** `ec_invalidate_photo_album_api_cache($albumIdHash)` aus [`includes/functions.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/functions.inc.php) aufrufen, damit EroCMS-Portale keine veralteten Status- oder Textdaten aus dem `.tmp`-Cache erhalten!
* **Checksummen-Funktion (`photo_album_checksum()`):** Berechnet den MD5-Hash über Metadaten, Preise, Cover, Fotoanzahl **sowie** (sofern `status != 'active'`) das Suffix `|{status}`, sodass reine Statusänderungen (`blocked` / `deleted`) sofort einen Sync auf allen EroCMS-Portalen auslösen.

### C. Verpixelte Vorschaubilder, Kauf & Kunden-Download
* **Vorschau-Liste & Thumbnails:** [`api/includes/get_photo_album_thumbs.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/get_photo_album_thumbs.inc.php) liefert alle `file_id`s in exakt derselben chronologischen Reihenfolge (`ORDER BY id ASC`) wie MCP & ACP (damit Bild `#1..#N` auf EroCMS-Portalen und in der EroCloud 1:1 übereinstimmt) inkl. `?v={mtime}` Browser-Cache-Buster auf `photo_url` (`/AlbumPhoto/{file_id}?v=...`) und `my_photo_url` (`/MyAlbumPhoto/.../{file_id}?v=...`). [`api/album_photo_thumb.php`](file:///c:/xampp/htdocs/EroCloud/api/album_photo_thumb.php) erzeugt `images/thumb_{filename}` (200px, verpixelt, vollständig EXIF-korrigiert für `Orientation = 2..8`, speicherschonend erst auf `200px` skaliert und dann gedreht, mit weißem Alpha-Hintergrund + `Imagick`-Fallback für HEIC/AVIF/CMYK) und besitzt eine **automatische Altbestand-Selbstheilung** (`ec_album_thumb_needs_regeneration()`): Ist das Originalbild neuer als `thumb_`, wurde ein Thumbnail mit EXIF `Orientation > 1` oder ein komplett schwarzes Alt-Thumbnail (`ec_thumb_is_solid_black()`) vor `EC_THUMB_EXIF_FIX_TS` (`1795520600`) erzeugt oder weicht bei `5/6/7/8` das Hoch-/Querformat ab, wird `thumb_` beim nächsten Abruf atomar neu generiert (Fehlerantworten in `no_image()` senden `Cache-Control: no-store`). Cover werden über [`api/photo_album_poster.php`](file:///c:/xampp/htdocs/EroCloud/api/photo_album_poster.php) (`/PhotoAlbumPoster/FSK16|FSK18/{album_id}`, inkl. EXIF `2..8` vor Raw-Bypass und `fast_pa_v2_`/`pa_v2_`-Cache) ausgeliefert.
* **Kauf-Registrierung:** [`api/buy_photo_album_erocms.php`](file:///c:/xampp/htdocs/EroCloud/api/buy_photo_album_erocms.php) legt den Kundenzugriff in `photo_albums_access` an.
* **Kunden-Abruf (ZIP & Einzelbild):** [`api/get_photo_album_url.php`](file:///c:/xampp/htdocs/EroCloud/api/get_photo_album_url.php) prüft `photo_albums_access`, vergibt ein 30-Minuten-`access_token` für den ZIP-Stream ([`api/photo_album_downloader.php`](file:///c:/xampp/htdocs/EroCloud/api/photo_album_downloader.php)) und autorisiert die unzensierte Einzelbild-Anzeige über [`api/my_album_photo.php`](file:///c:/xampp/htdocs/EroCloud/api/my_album_photo.php) (ebenfalls mit `?v={mtime}` Browser-Cache-Buster analog `get_photo_album_thumbs.inc.php`, sodass nachträgliche Foto-Rotationen aus dem MCP ohne erneute ACP-Prüfung live auf EroCMS-Seiten durchschlagen).

---

## 4. 🔗 Gegenstellen im EroCMS (`c:\xampp\htdocs\EroCMS`)

| Aufgabe im EroCMS | Zuständige Datei im Projekt `EroCMS` |
| :--- | :--- |
| **Zentraler API-Client & Update-Funktionen** | `erocloud_api/config.inc.php` (`get_erocloud_result()`, `update_erocloud_movie()`, `update_erocloud_photo_album()`) |
| **Stündlicher Fotoalben-Import & Selbstheilung** | `includes/cronjobs/import_new_photo_albums.php` (inkl. `try/catch`, Geister-Schutz bei leerer `headline` und `0-DDL` Käuferschutz via `online_ab = '999912312359'` für gekaufte `deleted`/`blocked`-Alben) |
| **Film-Import & Delta-Sync** | `includes/cronjobs/import_new_movies.php` & `includes/cronjobs/erocloud_sync_movies.php` |
| **Darsteller- & Kategorie-Import** | `includes/cronjobs/import_new_actors.php`, `update_actor_profiles.php`, `import_movie_categories.php` |

---

## 5. 🐘 PHP 8.0 / 8.4.27 (+ 8.5) Kompatibilität & Härtung

Alle 46 PHP-Dateien unter `api/` sowie die 18 mitgeladenen Shared-Dateien (`config.inc.php`, `includes/functions.inc.php`, `includes/klassen/*`, `includes/ai_compliance_core.inc.php`) sind dual für **PHP `8.0.30` und PHP `8.4.27` (+ 8.5)** gehärtet (bei 100 % unveränderter Wire-, Hash- und Checksum-Kompatibilität zum EroCMS):

* **Durchgängiges UTF-8 (ohne BOM):** Alle 14 historisch in `ISO-8859-1` gespeicherten API-/Messenger-Skripte wurden verlustfrei nach UTF-8 konvertiert; [`includes/klassen/ChatMessage.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/ChatMessage.inc.php) nutzt nun `SET NAMES 'utf8mb4'` mit `__FILE__, __LINE__` statt `SET NAMES "utf8"`.
* **Entfernung veralteter/abgekündigter APIs:** `FILTER_SANITIZE_STRING` (ersetzt durch explizite Whitelist-/Typprüfungen + `strip_tags()` in [`api/receive_member_info.php`](file:///c:/xampp/htdocs/EroCloud/api/receive_member_info.php) und [`api/includes/update_message.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/update_message.inc.php)), `curl_close()` (ersetzt durch `unset($ch)` in [`includes/functions.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/functions.inc.php), [`includes/klassen/ChatMessage.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/ChatMessage.inc.php), [`includes/ai_compliance_core.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/ai_compliance_core.inc.php) und [`api/usertracking/cronjob.php`](file:///c:/xampp/htdocs/EroCloud/api/usertracking/cronjob.php)) sowie `srand(make_seed())` (`randomString()` nutzt `random_int()`).
* **Null-, Typ- und Array-Input-Safety:** Explizite `is_scalar()`/`is_string()`-Prüfungen auf allen Request-Parametern (`$_GET`, `$_POST`, JSON-Body), `abs((int)...)`/`abs((float)...)`-Casts, sichere `strtotime()`- und `date()`-Guards, `filter_input_array(INPUT_GET)` statt fehlerhaftem `filter_input(INPUT_GET)` in [`api/includes/receive_message.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/receive_message.inc.php) sowie `array_filter()` ohne `strlen(null)`-Deprecation in `generate_user_streaming_key()`, `generate_user_album_key()` und `ChatMessage`.
* **Atomares Member-Upsert (Race-Condition-Schutz):** [`api/receive_member_info.php`](file:///c:/xampp/htdocs/EroCloud/api/receive_member_info.php) verwendet statt der früheren `SELECT` + `INSERT`/`UPDATE`-Logik ein atomares `INSERT INTO members ... ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)` inkl. Fallback-Lookup, sodass parallele EroCMS-Requests keinen `Duplicate entry '...' for key 'remote_member_id'` mehr auslösen.
* **Medien-, Archiv- & Tracking-Härtung:** Schutz vor `DivisionByZeroError`, `ValueError` (`max(1, (int)...)`) und `TypeError` in GD (`messenger_image.php`, `movie_poster.php`, `photo_album_poster.php`, `profile_picture.php`), `function_exists()`-Guards gegen `Cannot redeclare getRequestHeaders()` beim Album-Poster-Fallback in [`api/album_photo_thumb.php`](file:///c:/xampp/htdocs/EroCloud/api/album_photo_thumb.php), `Imagick`-/`Throwable`-Catch & korrekte JPEG-`Content-Length` in [`api/messenger_pdf_preview.php`](file:///c:/xampp/htdocs/EroCloud/api/messenger_pdf_preview.php), `ZipArchive`-Checks in [`api/photo_album_downloader.php`](file:///c:/xampp/htdocs/EroCloud/api/photo_album_downloader.php), atomarer Cache-Zugriff (`LOCK_EX` + `['allowed_classes' => false]`) in [`api/includes/get_actors_online_status.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/get_actors_online_status.inc.php) sowie CLI-PHP-8.4-Erkennung in [`api/ai_gateway.php`](file:///c:/xampp/htdocs/EroCloud/api/ai_gateway.php).

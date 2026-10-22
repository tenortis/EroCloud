---
name: acp-modernization-architecture
description: Architektur-Blaupause und Migrationsplan fuer den kompletten Neubau des EroCloud Admin-Control-Panels (ACP) unter /acp/acp_neu/ nach dem Vorbild des MCP (app/ & web/ Trennung, SPA-Fragment-Loading, Pay4Coins-Blau Theme).
usage: 'Wird automatisch herangezogen bei allen Arbeiten am Neubau des ACP unter /acp/acp_neu/ (spaeter /acp/).'
---

# ACP Modernisierungs-Architektur (`app/` & `web/` analog `@mcp-modernization-architecture.md`)

> **Kontext-Referenz:** Diese Dokumentation ergänzt `@master.md`, `@server-and-system-environment.md` sowie `@mcp-modernization-architecture.md` und definiert die verbindliche Softwarearchitektur für den kompletten Neubau des **EroCloud Admin-Control-Panels (ACP)**.

---

## 1. 🎯 Zielsetzung & Grundprinzipien

1. **Greenfield-Neubau analog `mcp_neu`:**
   Bestehende Legacy-Dateien in `/htdocs/acp/` (veraltetes `ISO-8859-15`, jQuery 1.12, Tabellen-Layouts) werden parallel unter `/htdocs/acp/acp_neu/` in einer modernen, modularen Architektur von Grund auf neu aufgebaut.
2. **Trennung von `app/` und `web/`:**
   * **`web/` (Öffentliches DocumentRoot):** Enthält ausschließlich den Einstiegspunkt (`index.php` mit App-Shell & integriertem Admin-Login), den AJAX-Dispatcher (`api.php`), den geschützten Medien-/Streaming-Proxy (`media.php`) sowie die lokalen statischen Assets (`assets/css`, `assets/js`, `assets/img`, `assets/fonts` – 100 % DSGVO-konform ohne externe CDNs).
   * **`app/` (Geschützter Anwendungskern):** Durch `.htaccess` (`Require all denied`) vollständig gegen direkten Browserzugriff gesperrt. Enthält `bootstrap.php`, Core-Klassen (`Autoloader`, `Csrf`, `Request`, `Response`, `Router`, `View`) und die fachlichen Module (`Home`, `MoviesChecking`, `Preview` sowie die schrittweise folgenden Fach-Controller).
3. **Dynamisches Nachladen ohne Seiten-Reload (SPA-Architektur):**
   Ein Klick in der linken Admin-Sidebar lädt die Ansicht verzögerungsfrei über Vanilla-JavaScript (`fetch('api.php?route=...')`) in `#app-content`, ohne die gesamte Seite neu zu laden.
4. **Look & Feel (Einheitliches Kacheldesign in Pay4Coins-Blau):**
   * Helles, übersichtliches Standard-Theme (**Light Mode Default** mit `#F8FAFC` Hintergrund und weißen Kacheln `#FFFFFF`, optionaler Dark-Mode-Toggle in der Topbar via `localStorage['ec-acp-theme']`).
   * Primärfarbe **Pay4Coins-Blau** (`#4297D7` / `#0284C7`).
5. **100 % Einhaltung von `@master.md` & PHP 8.4.27 Kompatibilität:**
   * `declare(strict_types=1);` und `define('SAFE_INC', 1);`
   * Datenbankzugriff ausschließlich über `p4c_query($sql, __FILE__, __LINE__)` und zentrales Error-Logging in `/var/www/web1/htdocs/log/.errorlog`.
   * Durchgehend **UTF-8** inkl. automatischer Konvertierung historischer `ISO-8859-1`-Einträge.
   * Alle UI-Texte ausnahmslos in der persönlichen **Du-Form** (`du`, `dein`, `deine`, `dir`, `dich`).

---

## 2. 📂 Ordnerstruktur & URL-Strategie

### 2.1 Parallelbetrieb während der Neuentwicklung (`/htdocs/acp/acp_neu/`)
* **Entwicklungs-URL:** `https://acp.erocloud.net/acp_neu/web/Startseite` (bzw. Kurzform `https://acp.erocloud.net/acp_neu/` oder `https://acp.erocloud.net/ACP_Neu/`).
* **HTTP Basic Auth & Session-Sharing:**
  * Da `/acp/acp_neu/` innerhalb von `/acp/` liegt, greift der bestehende `.htaccess` Verzeichnisschutz (`.acp_htpasswd`) automatisch.
  * Die PHP-Session (`$_SESSION['logged_id'] = 'acp'`, `$_SESSION['employee_id']`, `$_SESSION['employee_access_area']`) wird nahtlos zwischen dem alten ACP und `acp_neu` geteilt.
  * Zusätzlich schützt ein mit `AES_KEY` HMAC-signiertes 30-Minuten-Cookie (`erocloud_acp_auth`) vor vorzeitigem Session-Verlust durch den PHP Garbage Collector im geteilten `TEMP_DIR`.

### 2.2 Verzeichnisbaum

```text
acp/acp_neu/ (später: acp/)
├── index.php                     # Komfort-Weiterleitung auf web/Startseite
├── app/
│   ├── .htaccess                 # Require all denied
│   ├── bootstrap.php             # Lädt config.inc.php, functions.inc.php, Session, DB, Settings, Auto-Recovery, Autoloader
│   └── src/
│       ├── Core/
│       │   ├── Autoloader.php    # PSR-4 Autoloader für Namespace App\
│       │   ├── Csrf.php          # CSRF-Token-Generator & Prüfer ($_SESSION['acp_csrf_token'])
│       │   ├── Request.php       # HTTP Request Wrapper
│       │   ├── Response.php      # HTML-Fragmente & JSON-Antworten (inkl. Gzip)
│       │   ├── Router.php        # Zentrales Routing & Berechtigungsprüfung (acp_has_access)
│       │   └── View.php          # Template-Renderer & XSS-Escaping View::e()
│       └── Modules/
│           ├── Home/
│           │   ├── HomeController.php
│           │   └── views/
│           │       └── dashboard.php          # Echtes Admin-Dashboard (#/startseite)
│           ├── MoviesChecking/
│           │   ├── MoviesCheckingController.php
│           │   └── views/
│           │       ├── list.php               # Prüf-Warteschlange (#/movies-checking)
│           │       └── detail.php             # Einzelprüfung, Player, BRAM, Co-Actor & Freigabe (#/movie-checking)
│           └── Preview/
│               ├── PreviewController.php      # Modul-Platzhalter für den schrittweisen Ausbau
│               └── views/
│                   └── section_placeholder.php
└── web/
    ├── .htaccess                 # RewriteEngine (/Startseite -> index.php), Security-Header
    ├── index.php                 # App-Shell (Sidebar, Topbar mit 30-Min-Timer & Schnellsuche, Login-Portal)
    ├── api.php                   # Zentraler AJAX-Dispatcher für SPA-Fragmente
    ├── media.php                 # Geschützter Video-Stream-, WebVTT- & Szenen-Thumbnail-Proxy
    └── assets/
        ├── css/
        ├── js/
        ├── fonts/
        └── img/
```

---

## 3. 🔐 Berechtigungskonzept (`employee_access_area`) & Auto-Logout

1. **Granulare Rechteprüfung (`acp_has_access(string $area)`):**
   Sowohl beim Rendern der Sidebar in `web/index.php` als auch bei jedem API-Aufruf in `App\Core\Router::dispatch()` wird geprüft, ob der eingeloggte Mitarbeiter `'all'` oder den jeweiligen Bereichsschlüssel (`content_check`, `content_online`, `actors`, `websites`, `affiliate_program`, `webcams`, `merchants`, `users`, `point_system`, `error_log`) in `$_SESSION['employee_access_area']` besitzt.
   *(Hinweis: Die veralteten Bereiche **0900-Rufnummern / erocall** sowie **Chats / Alle Nachrichten** wurden im neuen ACP bewusst und vollständig entfernt.)*
2. **30-Minuten Auto-Logout Countdown (`#acp-logout-timer`):**
   * In der Topbar läuft ein sekundengenauer Countdown von `30:00` herunter (analog `ACP_SESSION_DURATION`).
   * Jede SPA-Navigation oder Interaktion setzt den Countdown automatisch wieder auf `30:00` zurück.
   * Bei Erreichen von `00:00` erfolgt der automatische Logout über `index.php?logout=auto` inkl. Protokollierung in `employee_history`.

---

## 4. 🎬 Module „Filme prüfen“ & „Fotoalben prüfen“

* **`App\Modules\MoviesChecking` (`#/movies-checking` & `#/movie-checking?id=...`):**
  * Prüf-Warteschlange inkl. KPI-Kacheln, Echtzeit-Filterung (BRAM-Status, 18+ ID-Check, Hauptkategorie), FSK16/FSK18-Vorschau und automatischer BRAM-Erstberechnung.
  * 3-Tab-Prüfcockpit mit HTML5-Videoplayer (inkl. WebVTT-Untertiteln, Schnell-Spul- & Speed-Steuerung), `getID3`-Metadaten-Modal, 1-Klick-Prüfung für mitwirkende Personen (`CoActorManager`), FSK16/FSK18-Szenenbild-Wähler (`1..12`), Live-Streamingpreis-Rechner, SEO-Generator, Kategorie-Suche mit Texterkennungs-Hervorhebung, Freigabe (`POST ?route=movies-checking/approve` inkl. `movies_online`-Sync & `PointsSystem`) und Ablehnung (`POST ?route=movies-checking/reject` inkl. Historie).
* **`App\Modules\PhotoAlbumsChecking` (`#/photo-albums-checking` & `#/photo-album-checking?id=...`):**
  * Fotoalben-Warteschlange mit KPI-Kacheln, Echtzeit-Filterung, FSK16/FSK18-Cover-Lightbox, BRAM-Textanalyse und 18+ ID-Shot-Status.
  * Einzelprüfung mit FSK16/FSK18-Cover-Großansicht, 1-Klick-Verifizierung für mitwirkende Personen (`CoActorManager`), interaktivem Einzelfoto-Grid (inkl. 1-Klick-Ablehnung einzelner Fotos per AJAX `photo-albums-checking/toggle-photo` & durchblätterbarer Galerie-Lightbox), Duplikat-Titel-Erkennung, Kategorie-Hervorhebung (aus Text & Darsteller-Profil), Freigabe (`photo-albums-checking/approve` inkl. `photo_albums_online`-Sync & `PointsSystem`) und Ablehnung (`photo-albums-checking/reject` inkl. Historie).

---

## 5. 📚 Module „Film-/Fotoalben-Gesamtübersicht“ (`MoviesOnline` & `PhotoAlbumsOnline`)

* **Modulübergreifende Content-Switcher-Leiste (`MoviesOnlineController::getContentSwitcherCounts()`):**
  * Verbindet alle 6 Content-Listen (`#/movies-online`, `#/movies-drafts`, `#/movies-blocked`, `#/movies-checking`, `#/photo-albums-online`, `#/photo-albums-checking`) mit Live-Zählern für nahtlosen 1-Klick-Wechsel.
* **`App\Modules\MoviesOnline` (`MoviesOnlineController.php`):**
  * **`#/movies-online` (`views/list.php`):** Gesamtübersicht aller veröffentlichten Filme (`movies_online`) inkl. 4 KPI-Kacheln, gecachter & per 1-Klick neu berechenbarer Speicherplatz-Anzeige (`POST ?route=movies-online/recalc-size`), Server-Suche, Status-/Kategorie-/Qualitäts-/Sprach-/BRAM-Filterung, Sortierung und Paginierung.
  * **`#/movie-edit?id=...` (`views/edit.php`):** 5-Tab-Bearbeitungs-Cockpit (*1. Bearbeiten*, *2. BRAM & KI-Analyse*, *3. Audio & Transkript (.vtt)*, *4. Käufe & Zugriffe (`movies_access`)*, *5. Technische Details*) inkl. synchronem Speichern in `movies_online` + `movies`, `movie_checksum()`-Neuberechnung, Shop-Kategorien-Bereinigung bei Löschung und physischem Datei-Cleanup (`POST ?route=movies-online/save`).
  * **`#/movies-drafts` (`views/drafts.php`):** Übersicht aller Filme in Planung (`released = '0' AND status != 'deleted'`) mit Konvertierungsstatus (`convert_status`), Ordnergröße und Echtzeit-Filterung.
  * **`#/movies-blocked` (`views/blocked.php`):** Übersicht aller abgelehnten, gesperrten und gelöschten Filme (`convert_status > '1' AND (released = '2' OR status != 'active')`) inkl. letzter Ablehnungsbegründung aus `rejection_reason_movie_history`.
* **`App\Modules\PhotoAlbumsOnline` (`PhotoAlbumsOnlineController.php`):**
  * **`#/photo-albums-online` (`views/list.php`):** Gesamtübersicht aller veröffentlichten Fotoalben (`photo_albums_online`) inkl. 4 KPI-Kacheln, Speicherplatz-Neuberechnung (`POST ?route=photo-albums-online/recalc-size`), FSK16/FSK18-Cover-Lightbox, Live-BRAM-Badges, Aufruf-/Verkaufszahlen, Filterung, Sortierung und Paginierung.
  * **`#/photo-album-edit?id=...` (`views/edit.php`):** Vollständiges Bearbeitungs-Cockpit für veröffentlichte Fotoalben inkl. FSK16/FSK18-Großansicht, 18+ Mitwirkenden-Prüfung (`CoActorManager`), interaktivem Einzelfoto-Grid (`photo_albums_photos`) mit 1-Klick-Foto-Sperre & Galerie-Lightbox, Duplikat-Titel-Prüfung, SEO-Editor und synchronem Speichern in `photo_albums_online` + `photo_albums` inkl. `photo_album_checksum()` (`POST ?route=photo-albums-online/save`).
* **`App\Modules\ContentCleanup` (`ContentCleanupController.php` & `views/index.php`):**
  * **`#/content-cleanup` (`GET ?route=content-cleanup`):** 3-Tab-Cockpit für die 5-Regel-Bereinigungs-Simulation (`rule_1`..`rule_5`), die Lösch-Historie (`movies_deleted`) sowie den Cloud-Storage-Dateileichen-Scanner (`GET ?route=content-cleanup/orphan-scan`).
  * **N+1-Query-Eliminierung:** Aggregiert `movies_access`, `movies_online` und `actors` in 3 gebündelten Batch-Abfragen statt einzelner Schleifen-Queries.
* **`App\Modules\StorageAnalytics` (`StorageAnalyticsController.php` & `views/index.php`):**
  * **`#/storage-analytics` (`GET ?route=storage-analytics` & `GET ?route=storage-analytics/scan`):** 3-Tab-Analyse-Cockpit für Cloud-Storage-Belegung, Dateiformat-Aufschlüsselung, 6-Monats-Streaming-/Download-Traffic (`movies_access` mit 1x Basis & 2x Praxis-Faktor) und Cloud-Provider-Kostenkalkulation (Backblaze B2, OVHcloud, Wasabi, Bunny.net).
  * **7-Stufen-Sicherheitsbereinigung (`GET/POST ?route=storage-analytics/legacy-cleanup`):** Unterstützt die Einzel-Video-Vorschau (`action=preview`), die bestätigte Einzel-Löschung (`action=delete_single`) und die kontrollierte Stapel-Bereinigung (`action=delete_batch`) veralteter Mobil-Dateien (`m_*.mp4`, `m_*.webm`, `m_*.ogv`) mit Nachprüfung der Desktop-MP4-Hauptdatei und automatischem Not-Aus-Schutz.

---

## 6. 🪪 Modul „Drehpartner & ID-Check“ (`CoActorsChecking`)

* **`App\Modules\CoActorsChecking` (`CoActorsCheckingController.php` & `views/list.php`):**
  * **`#/co-actors-checking`:** Zentrales Prüfcockpit für alle Account-Inhaber („Ich“, `is_owner = 1`) und Drehpartner (`is_owner = 0`) in `co_actors`.
  * **KPI-Kacheln & Filterung:** Filter nach Prüfstatus (`under_review`, `verified`, `rejected`, `pending_qr`, `all`), Rolle (`owner` vs. `co_actor`), Nachweis-Methode (`qr_live` vs. `manual_legacy`), Sortierung und AES-entschlüsselter Volltextsuche.
  * **Lokaler Medien- & Zertifikats-Proxy (`media.php?type=co_actor`):** Liefert `face_ref`, `id_shot`, `id_front`, `id_back`, `consent_raw` sowie das druckfähige HTML/PDF-Einverständnis-Zertifikat (`certificate`) direkt über die aktive ACP-Session aus.
  * **4-fach Vergleichs-Inspektor (`#cacInspectorModal`):** Zeigt KI-Referenz-Selfie, ID-Shot sowie Ausweis Vorder- & Rückseite nebeneinander mit den entschlüsselten Ausweisdaten (Name, Geburtsdatum, Alter, Ausweisnummer, Ablaufwarnung) und direkten Bestätigen-/Ablehnen-Buttons.
  * **Direkte Ausweisdaten-Korrektur (`POST ?route=co-actors-checking/update-data`):** Erlaubt das Korrigieren von Tippfehlern in den AES-verschlüsselten Personendaten ohne erneuten Smartphone-Scan.
  * **Verknüpfte Inhalte (`content_co_actors`):** Zeigt sowohl verknüpfte Filme (`movie`) als auch verknüpfte Fotoalben (`photo_album`) mit Direktlink in die jeweilige Prüf- oder Bearbeitungsansicht.
  * **Smartphone-QR-Modal (`POST ?route=co-actors-checking/action`):** Zeigt den lokalen SVG-QR-Code und Direktlink inkl. Token-Erneuerung (`regenerate_qr`).

---

## 7. 🤖 Modul „KI-Analyse & BRAM“ (`AiCompliance`)

* **`App\Modules\AiCompliance` (`AiComplianceController.php` & `views/index.php`):**
  * **`#/ai-compliance` (`GET ?route=ai-compliance` & `GET/POST ?route=ai-compliance/ajax`):** Zentrales 5-Tab-Cockpit für die Whisper-Audio-Transkription (`.vtt`) sowie die Visa/Mastercard BRAM- & Rechts-Compliance-Prüfung (`movies_ai_analysis`).
  * **Top-KPI-Kacheln & System-Readiness-Leiste:** Zeigt konvertierte Videos (`convert_status = 2`), Whisper-Untertitel-Fortschritt, BRAM-Prüfquote, klickbare Compliance-Bilanz (`ok` / `warning` / `violation`) und den Live-Status von FFmpeg, `whisper.cpp`, `llama.cpp` und `cronjobs/ai_compliance_worker.php`.
  * **Tab 1 – Einzel-Video-Testlabor:** Video-Schnellauswahl, editierbare Test-Eingaben (Titel, Beschreibung, gesprochenes Transkript), Schritt 1 (Whisper Audio-Extraktion & `.vtt`-Vorschau inkl. Simulationsszenarien) und Schritt 2 (BRAM- & Rechts-Prüfung mit Risiko-Score und Zitat-Fundstellen).
  * **Tab 2 – Batch- & Cronjob-Steuerung:** Fortschrittsbalken für Stufe 1 & 2, Katalog-Schnellscan (`run_bulk_text_precheck` in 250er-Paketen), Live-Whisper-Batch im Browser (`run_batch_step` + `poll_worker_status`) und Konfiguration des 24/7 Server-Cronjobs (`save_batch_config`).
  * **Tab 3 – Visa/Mastercard BRAM-Regelwerk & Prompt:** Übersicht der aktiven Kategorien & Schlagwörter sowie Live-Editoren für `includes/ai_rules/bram_system_prompt.md` und `includes/ai_rules/bram_keyword_rules.json` (`save_rules`).
  * **Tab 4 – Analyse-Historie & Filter:** Echtzeit-Filterung über `movies_ai_analysis` nach BRAM-Status, Untertitel-Status und Volltext/ID (`filter_history`).
  * **Tab 5 – Transkript- & VTT-Inspektor:** Gesamtauswertung aller `.vtt`-Transkripte auf Klammer-Tags, Sprachverteilung, Filme ohne Dialog und Phrasen-Wiederholungen (`inspect_transcripts`) inkl. 1-Klick-Bestandsbereinigung (`clean_all_vtt_transcripts`) und Re-Queue exotischer Sprachen (`requeue_exotic_lang_movies`).

---

## 8. 👤 Modul „Profile“ (`Actors`)

* **`App\Modules\Actors` (`ActorsController.php`, `views/list.php`, `views/detail.php`):**
  * **`#/actors` (`GET ?route=actors`):** Gesamtübersicht aller Darsteller-Profile (`actors`) mit 4 KPI-Kacheln (Aktive Profile & Neu 5T, Hauptkategorie & Cam, Profilbilder FSK16/FSK18, Status-Übersicht), gebündelter Vorab-Aggregation von Filmen und Fotoalben pro Profil (0 N+1 Queries), Filterung nach Status (`active`, `blocked`, `deleted`, `new_5d`), Hauptkategorie (`porn`, `fetish`, `none`), Anzeigegruppe (`female`..`trans`), Profilbild-Status, Händler-ID (`merchant_id`), AES-entschlüsselter Volltextsuche, Sortierung und Paginierung.
  * **`#/actor?id=...` (`GET ?route=actor&id=...`):** 3-Tab-Bearbeitungs-Cockpit (*1. Profil-Daten & Kategorisierung*, *2. Zugeordnete Videos*, *3. Zugeordnete Fotoalben*) inkl. Vorheriges/Nächstes-Profil-Navigation. Lädt alle Profildaten, Kategorien, Videos (`LEFT JOIN movies_online`) und Fotoalben (`LEFT JOIN photo_albums_online`) in nur 7 gebündelten Queries statt ~60+ Einzel-Queries der Legacy-Klasse `Actor`.
  * **Speichern, Profilbild-Löschung & Händler-Wechsel:**
    * `POST ?route=actors/save`: Speichert alle Profilfelder, Kategorien (`categorys[]`), Sonder-Provisionen (`amoredea_provision_content`, `amoredea_provision_obolus`), `obolus_type`, Steckbrief-Merkmale + `check_*`-Schalter, aktualisiert `actor_checksum($actorId)`, synchronisiert `group_actors.is_displayed_as` und kaskadiert `status = 'deleted'` auf `movies`, `movies_online`, `photo_albums` und `photo_albums_online`.
    * `POST ?route=actors/delete-image`: Löscht FSK16- oder FSK18-Profilbilder inkl. `actor_checksum($actorId)`.
    * `POST ?route=actors/reassign-merchant`: Reiht einen kompletten Profil-Inhaberwechsel (`actor_reassign`) in die Hintergrund-Warteschlange ein.
    * *(Hinweis: Die veralteten Bereiche **0900-Rufnummern / erocall** sowie **Chats / Neue-User-Nachrichten** wurden gemäß Vorgabe vollständig entfernt.)*

---

## 9. 💼 Modul „P4C-Merchants“ (`Merchants`)

* **`App\Modules\Merchants` (`MerchantsController.php`, `views/list.php`, `views/detail.php`):**
  * **`#/merchants` (`GET ?route=merchants`):** Gesamtübersicht aller Pay4Coins-Händler (`merchants`) mit 4 KPI-Kacheln (P4C-Merchants Gesamt & Aktiv 30T, Richtlinien & Multi-Profil, Angelegte Darsteller-Profile, Veröffentlichter Content & Prüfung).
  * **N+1-Query-Eliminierung:** Statt 3 Einzel-Queries pro Händler in einer Schleife (~1.500 Queries im Legacy-AJAX `acp/includes/ajax/merchants.php`) werden alle Zähler für `actors`, `movies_online`, `movies`, `photo_albums_online` und `photo_albums` vorab in gebündelten `GROUP BY`-Abfragen geladen.
  * **Filter & AES-Volltextsuche:** Filter nach Richtlinien-Status (`accepted` / `pending`), Aktivität & Bestand (`active_30d`, `with_actors`, `without_actors`, `with_checking`, `multi_profile`), Sortierung, Seitengröße sowie Volltextsuche über `id`, `partner_id`, `AES_DECRYPT(username/email/firstname/surname/last_ip)` und zugeordnete Profilnamen.
  * **`#/merchant?id=...` (`GET ?route=merchant&id=...`):** Unterstützt sowohl die numerische `merchants.id` (`MID`) als auch die 10-stellige `merchants.partner_id` (`PID`), lädt alle entschlüsselten Stammdaten in 1 Query (statt 11 Einzel-Queries der Legacy-Klasse `Merchant`) und bietet ein 4-Tab-Cockpit (*1. Händler-Infos & Einstellungen*, *2. Darsteller-Profile*, *3. Filme & 18+ Nachweise*, *4. Login- & Aktivitäts-Historie aus `merchant_history`*) inkl. Speichern des Profil-Limits (`POST ?route=merchants/save-settings`).

---

## 10. ⚙️ Modul „System & Technik“ (`SystemTech`)

* **`App\Modules\SystemTech` (`SystemTechController.php`, `views/settings.php`, `views/errors.php`):**
  * **`#/system-settings` (`GET ?route=system-settings`):** 3-Tab-Cockpit für **1. Impressums- & Support-Stammdaten** (inkl. Live-E-Mail-Footer-Vorschau beim Tippen & `POST ?route=system-settings/save-imprint`), **2. SMTP-Konfiguration, HTML-/Plaintext-Vorlagen-Labor & Testversand** (`POST ?route=system-settings/send-test-mail`) sowie **3. Versand-Protokoll (`notification_log`) & PHP-8.0/8.4-Server-Umgebung**.
  * **`#/errors` (`GET ?route=errors`):** Speicherschonender Tail-Parser (1,5 MB Stream-Fenster) für `/var/www/web1/htdocs/log/.errorlog` mit automatischer Klassifizierung nach Schweregrad (`Fatal / Exception`, `SQL / Datenbank`, `Warning / Fehler`, `Deprecated / Notice`), Trennung nach `Web/HTTP` vs. `CLI/Cronjob`, 3 Ansichten (*1. Chronologische Fehlerliste*, *2. Gruppiert nach Häufigkeit*, *3. Original `.errorlog` Konsole*) sowie Wartungs-Aktionen (`POST ?route=errors/action`: `clear_log` mit `.errorlog.bak`-Backup, `restore_backup` und `write_test_entry`).

---

## 11. 👥 Modul „Cloud-User“ (`Users`)

* **`App\Modules\Users` (`UsersController.php`, `views/list.php`, `views/detail.php`):**
  * **`#/users` (`GET ?route=users`):** Serverseitig paginierte Gesamtübersicht aller Cloud-User (`members`) mit 4 KPI-Kacheln (Registrierte Cloud-User Aktiv/Gesperrt, Coins-Guthaben im Umlauf, Endgeräte `Mobile` vs. `Desktop`, Herkunfts-Domains / Shops), Filterung nach Herkunfts-Shop (`p4c_shop_id`), Coin-Guthaben, Gerät, Status, Sortierung und Volltextsuche.
  * **`#/user?id=...` (`GET ?route=user&id=...`):** Lädt alle Mitglieds-Stammdaten + Shop-Domain in 1 Query (statt 9 Einzel-Queries der Legacy-Klasse `Member`) und bietet ein 3-Tab-Cockpit (*1. Benutzerdaten & Konto-Status* inkl. Sperren/Entsperren via `POST ?route=users/toggle-lock`, *2. Gekaufte Filme aus `movies_access`*, *3. Gekaufte Fotoalben aus `photo_albums_access`*).
  * *(Hinweis: Alle veralteten Chat- und Nachrichten-Spalten/Tabs wurden gemäß Vorgabe vollständig entfernt.)*

---

## 12. 🌐 Modul „Webseiten & Partner-Shops“ (`Websites`)

* **Ersetzte Legacy-Dateien:**
  * `acp/includes/sites.php` + `acp/includes/ajax/sites.php`
  * `acp/includes/site.php`
  * `acp/includes/get_p4c_data/get_sites.php`
* **Implementierte Dateien:**
  * `acp/acp_neu/app/src/Modules/Websites/WebsitesController.php`
  * `acp/acp_neu/app/src/Modules/Websites/views/list.php` (`#/sites`)
  * `acp/acp_neu/app/src/Modules/Websites/views/detail.php` (`#/site?id=...`)
  * `acp/acp_neu/web/media.php` (Erweiterung um `type=banner&file_id=...`)
* **Warum & Wie (Architektur-Entscheidungen & Verbesserungen gegenüber Legacy):**
  1. **Kontrollierte Pay4Coins-Synchronisation (`POST ?route=sites/sync-p4c`) statt blindem Auto-Sync beim Seitenaufruf:**
     * Im alten `acp/includes/sites.php` wurde bei jedem Öffnen der Seite ungefragt `get_p4c_data/get_sites.php` per cURL gegen `https://api.pay4coins.com/remote/index.php` ausgeführt. Das führte zu Ladeverzögerungen und potenziellen Timeouts beim bloßen Betrachten der Liste.
     * Im neuen ACP ist die Synchronisation als expliziter Button **„Mit Pay4Coins synchronisieren“** (`App.Websites.syncFromP4c()`) mit CSRF-Schutz, cURL-Timeout-Handling (`CONNECTTIMEOUT = 8s`, `TIMEOUT = 20s`), automatischer Domain-Umbenennungs-Kaskade (`sites`, `actor_member_info`, `messenger_sync`), Bereinigung inaktiver `user_tracking_sites` / `user_tracking_website_access` und `employee_history`-Audit-Log umgesetzt.
  2. **Eliminierung von N+1-Queries in `#/sites` (`WebsitesController::list()`):**
     * Statt Einzel-Abfragen pro Zeile werden Banner-Zähler (`ads_media` gruppiert nach `site_id`), Cloud-User-Zähler (`members` gruppiert nach `p4c_shop_id`) sowie Shop-Verkäufe & Umsätze (`movies_access` + `photo_albums_access` gruppiert nach `p4c_shop_id`) in 4 schnellen Batch-Queries vorab aggregiert.
  3. **8 klickbare KPI-Kacheln & kombinierbare Echtzeit-Filter:**
     * *Webseiten Gesamt*, *Aktive Shops (`status = 1`)*, *Inaktive Shops (`status = 0`)*, *EroAds Aktiv (`is_eroads_active = 1`)*, *EroCMS-Shops (`shop_system = 'erocms'`)*, *Eigene Shops (`shop_system = 'own'`)*, *Shops mit Werbebannern* und *Offene Banner-Prüfungen*.
  4. **Flexible Detailansicht `#/site?id=...` (`WebsitesController::detail()`):**
     * Akzeptiert als Parameter `id` sowohl die interne `sites.id`, die 5-stellige `sites.p4c_shop_id` als auch den Domainnamen (`sites.domain`) – dadurch funktionieren sämtliche Querverweise aus anderen Modulen nahtlos.
     * Bietet ein **4-Tab-Cockpit**:
       * *Tab 1 – Shop-Stammdaten & EroAds-Konfiguration:* Domain, P4C-Shop-ID, Partner-ID, verknüpfter Händler (`#/merchant?id=...`), Shop-System, Webmaster-Provision (`webmaster_commision`), Artikel-/Film-/Fotoalben-Bestand, `eroads_url` und Zeitstempel des letzten Conversion-Imports.
       * *Tab 2 – Werbebanner der Webseite (`ads_media`):* Vorschau aller Banner dieser Domain über `media.php?type=banner&file_id=...` inkl. Status-Badge, Maßen (`width × height px`), Dateityp und Direktlink zur Banner-Moderation.
       * *Tab 3 – Kampagnen & Conversions (`ads_campaigns` & `ads_conversions`):* Alle Kampagnen für diese Webseite inkl. Klickzahlen sowie die 50 neuesten Conversions (mit `join`, `reg`, `first_payment`, `follow_payment`, `again_credited_payment`, `canceled_payment`).
       * *Tab 4 – Letzte Shop-Verkäufe (`movies_access` & `photo_albums_access`):* Die neuesten Film- und Fotoalben-Käufe über diesen Shop mit Käufer-Link (`#/user?id=...`), Content-Link (`#/movie-edit?id=...` / `#/photo-album-edit?id=...`) und Preis in Coins/Euro.

---

## 13. 📢 Modul „Partnerprogramm / EroAds“ (`Affiliate`)

* **Ersetzte Legacy-Dateien:**
  * `acp/includes/webmaster_partner.php`
  * `acp/includes/banners.php` + `acp/includes/ajax/banners.php`
  * `acp/includes/banners_new.php` + `acp/includes/ajax/banners_new.php`
* **Implementierte Dateien:**
  * `acp/acp_neu/app/src/Modules/Affiliate/AffiliateController.php`
  * `acp/acp_neu/app/src/Modules/Affiliate/views/webmaster_partner.php` (`#/webmaster-partner`)
  * `acp/acp_neu/app/src/Modules/Affiliate/views/banners.php` (`#/banners`)
  * `acp/acp_neu/app/src/Modules/Affiliate/views/banners_new.php` (`#/banners-new`)
* **Warum & Wie (Architektur-Entscheidungen & Bugfixes gegenüber Legacy):**
  1. **Bugfix bei Jahresauswahl (`YYYY`) & Impressionstabellen (`ads_impressions_YYYYMM`):**
     * **Legacy-Bug:** In `acp/includes/webmaster_partner.php` enthielt das Dropdown zwar Jahreszahlen (`2025`, `2024`, …), doch bei Auswahl eines Jahres versuchte der Code `SELECT ... FROM ads_impressions_2025` auszuführen (Tabelle existiert nicht -> Fataler SQL-Fehler) und übergab `"2025"` an `strtotime("2025")`, was PHP als Uhrzeit `20:25` des heutigen Tages fehlinterpretierte.
     * **Lösung in `AffiliateController`:** `normalizePeriod()` validiert strikt zwischen Monatsformat (`YYYY-MM` ab `2018-09`) und Jahresformat (`YYYY` ab `2018`). `loadImpressionsByPartner()` ermittelt einmalig per `SHOW TABLES LIKE 'ads_impressions_%'` alle tatsächlich existierenden Monatstabellen und summiert bei Jahresauswahl automatisch alle vorhandenen Monatstabellen `ads_impressions_YYYY01`..`ads_impressions_YYYY12` des gewählten Jahres.
  2. **Eliminierung von N+1-Queries in `#/webmaster-partner` (`AffiliateController::webmasters()`):**
     * **Legacy-Problem:** Für jeden Webmaster aus `ads_campaigns` wurden in einer `while`-Schleife eine eigene `ads_conversions`-Query, eine `ads_impressions`-Query und `new Merchant($mysql, $pid)` (~11 Einzel-Queries pro Merchant) abgesetzt.
     * **Lösung:** Alle Daten werden in **3 gebündelten Abfragen** geladen (1x `ads_conversions` mit Zeitraum-Range `>= start AND <= end` inkl. Summe von Registrierungen und Netto-Umsatz aus `first_payment + follow_payment + again_credited_payment - canceled_payment`, 1x `ads_impressions_YYYYMM` per `GROUP BY p4c_partner_id`, 1x `ads_campaigns LEFT JOIN merchants` mit `AES_DECRYPT(m.username)`).
  3. **Manueller Conversion-Crawler (`POST ?route=affiliate/import-conversions`):**
     * Startet `cronjobs/import_conversions.php --date=YYYY-MM-DD` über die Server-PHP-CLI (`/opt/php-8.4/bin/php` → `/opt/php-8.0/bin/php` → `PHP_BINARY`), protokolliert den Abruf in `employee_history` und gibt die Gesamtzahl der für diesen Tag gespeicherten Conversions als Toast-Meldung zurück.
  4. **Banner-Verwaltung (`#/banners`) & Banner-Freigabe (`#/banners-new` + `POST ?route=banners/action`):**
     * `#/banners` bietet 4 klickbare KPI-Kacheln (*Banner Gesamt*, *Aktiv*, *Abgelehnt / Inaktiv*, *Offene Prüfungen*) sowie Filter nach Status, Domain (`site_id`), Format (`width × height px`), Dateityp (`JPG`, `GIF`, `PNG`) und Volltextsuche.
     * `#/banners-new` zeigt für jedes Banner mit `new_filename != '' AND rejected = 0` eine **Side-by-Side-Vergleichskarte** (*Aktuelles Banner* via `media.php?type=banner&file_id=...` vs. *Neues Banner* via `media.php?type=banner&file_id=...&variant=new`).
     * `POST ?route=banners/action` verarbeitet alle 3 Moderations-Aktionen per AJAX ohne Seiten-Reload:
       * `unlock` (Freischalten): Prüft Existenz der neuen Datei auf dem Storage, setzt `filename = new_filename`, `new_filename = ''`, `upload_datetime = NOW()`, `rejected = '0'` und löscht die alte Datei vom Dateisystem.
       * `reject` (Ablehnen): Setzt `rejected = '3'` in `ads_media` (exakt kompatibel zur Legacy-Logik für Merchant-Benachrichtigungen).
       * `delete` (Löschen): Löscht den Datensatz aus `ads_media` und entfernt sowohl `filename` als auch `new_filename` vom Dateisystem.

---

## 14. 🛡️ Modul „Mitarbeiter, Administratoren & Moderatoren“ (`Employees`)

* **Implementierte Dateien:**
  * `acp/acp_neu/app/src/Modules/Employees/EmployeesController.php`
  * `acp/acp_neu/app/src/Modules/Employees/views/list.php` (`#/employees`)
  * `acp/acp_neu/app/src/Modules/Employees/views/detail.php` (`#/employee?id=...` & `#/employee?id=0`)
* **Warum & Wie (Architektur-Entscheidungen & Sicherheitslogik):**
  1. **Gesamtübersicht (`#/employees`) ohne N+1-Queries:**
     * Bietet 4 KPI-Kacheln (*ACP Team-Accounts*, *Berechtigungs-Umfang `all` vs. Bereichs-Rollen*, *Geprüfte & freigegebene Inhalte*, *Team-Aktivität aus `employee_history`*) sowie Filter nach System-Rolle (`write` / `read`), Zugriffs-Bereich (`access_area`), Sortierung und Volltextsuche.
     * Alle Leistungs- und Aktivitätszähler pro Mitarbeiter (`employee_history`, `movies.released_from`, `photo_albums.released_from`, `co_actors.checked_by_employee`) werden in gebündelten `GROUP BY`-Abfragen vorab geladen.
  2. **Anlegen (`#/employee?id=0`) & Bearbeiten (`#/employee?id=...`) im 3-Tab-Cockpit:**
     * **Tab 1 – Zugangsdaten, Rolle & Berechtigungs-Matrix:**
       * Prüft den Benutzernamen strikt auf 2–25 alphanumerische Zeichen (`A-Za-z0-9`) und Eindeutigkeit (`UNIQUE username`), passend zur Login-Filterung.
       * Integrierter **Krypto-Passwort-Generator** (`window.crypto.getRandomValues`) mit Sichtbarkeits-Toggle und 1-Klick-Zwischenablage; Passwort-Hashing erfolgt über `hash_password()` (`sha512($pass.$salt) . ':' . $salt`).
       * Unterscheidet klar zwischen **Administrator (`rule = 'write'`)** (inkl. endgültigem Löschen `deleted` und Team-Verwaltung) und **Moderator / Prüfer (`rule = 'read'`)** (Moderations- & Prüfrechte).
       * **4 Schnell-Vorlagen (1-Klick Role Presets):** *Super-Admin (Vollzugriff)*, *Senior-Moderator (Prüfung & Löschrecht)*, *Content-Moderator / Prüfer (Standard)* und *Support, Profile & User-Betreuung*.
       * **Granulare ACP-Bereichs-Matrix (`access_area`):** Master-Switch für Vollzugriff (`all`) sowie 11 einzeln schaltbare Bereichs-Kacheln (`content_check`, `content_online`, `actors`, `merchants`, `users`, `websites`, `affiliate_program`, `webcams`, `point_system`, `error_log`, `employees`).
     * **Tab 2 – Geprüfte Inhalte:** Zeigt die zuletzt von diesem Mitarbeiter geprüften/bearbeiteten Filme (`movies`), Fotoalben (`photo_albums`) und 18+ Ausweise (`co_actors`).
     * **Tab 3 – Login- & Aktivitäts-Historie (`employee_history`):** Zeigt die letzten 300 Aktionen inkl. IP-Adresse, Kategorie-Badge, Echtzeit-Suchfilter und Bereinigungs-Funktion (`POST ?route=employees/clear-history`).
  3. **Selbstschutz- & Live-Session-Synchronisation:**
     * Ein eingeloggter Administrator kann seinen eigenen Account weder löschen (`POST ?route=employees/delete`) noch sich selbst die Schreibberechtigung (`write`) oder den Zugriff auf die Mitarbeiterverwaltung (`employees` / `all`) entziehen.
     * Ebenso wird das Löschen des letzten verbleibenden Administrators (`rule = 'write'`) im System verhindert.
     * Werden am eigenen Account Benutzername oder Berechtigungen geändert, aktualisiert `EmployeesController::save()` sofort `$_SESSION` sowie das HMAC-signierte Auth-Cookie (`acp_neu_sync_auth_cookie()`).

---

## 15. 🔍 Deep-Logic-Audit & Qualitätssicherung (PHP 8.0.30 & PHP 8.4.27)

Um sicherzustellen, dass das gesamte neue ACP unter **PHP 8.0.30** und **PHP 8.4.27** ohne jegliche Laufzeitfehler, SQL-Abstürze oder tote Frontend-Handler läuft, wurden 5 spezialisierte Prüf-Pipelines entwickelt und über die gesamte Codebasis ausgeführt:

1. **Methoden- & Router-Reflection-Audit:**
   * Prüft alle `65` in `Router.php` registrierten Routen per PHP Reflection darauf, ob Controller-Klasse und `public`-Methode existieren, sowie alle `Core`-/`$this->`-/`self::`-Methodenaufrufe in sämtlichen PHP-Dateien.
2. **SQL-Tokenizer & Schema-Audit (`258` `p4c_query()`-Aufrufe):**
   * Prüft mit `token_get_all()`, dass ausnahmslos jeder einzelne der `258` `p4c_query()`-Aufrufe `__FILE__, __LINE__` übergibt, und gleicht jede referenzierte Tabelle sowie jede `UPDATE`/`INSERT`-Spalte gegen das Live-MySQL-Schema (`INFORMATION_SCHEMA.COLUMNS`) ab.
3. **MySQL `EXPLAIN` Query-Audit (`208` `SELECT`-Queries):**
   * Extrahiert alle `208` `SELECT`-Abfragen aller Controller und führt sie per `EXPLAIN` direkt gegen die MySQL-Datenbank aus, um Tippfehler in Spaltennamen, Aliasen oder `GROUP BY`-Klauseln zu 100 % auszuschließen.
4. **Controller-Render- & JSON/Media-Endpunkt-Audit:**
   * Rendert alle Views mit echten Datenbank-Datensätzen unter `error_reporting(E_ALL)` und prüft alle JSON-Endpunkte sowie `media.php` (`0 Warnings`, `0 Notices`, `0 Deprecations`).
5. **Frontend-JS ↔ View Cross-Check (`140` `App.*`-Methoden):**
   * Prüft alle `onclick`/`onchange`/`onsubmit`-Handler in sämtlichen Views gegen die in `web/assets/js/app.js` definierten Modul-Objekte (`App.Home`, `App.MoviesChecking`, `App.PhotoAlbumsChecking`, `App.CoActorsChecking`, `App.AiCompliance`, `App.MoviesOnline`, `App.PhotoAlbumsOnline`, `App.ContentCleanup`, `App.StorageAnalytics`, `App.Actors`, `App.Merchants`, `App.Users`, `App.SystemTech`, `App.Websites`, `App.Affiliate`).

### Beim Deep-Logic-Audit gefundene & behobene Fehler (Chronologie)

| # | Datei & Stelle | Gefundener Fehler (Was & Warum) | Durchgeführte Behebung (Wie) |
| :--- | :--- | :--- | :--- |
| **1** | `app/src/Core/Request.php` | `SystemTechController` rief `$this->request->csrfToken()` auf; Methode fehlte in `Request`, was bei POST-Aktionen in `SystemTech` zum `Error: Call to undefined method` geführt hätte. | `public function csrfToken(): string` in `Request.php` ergänzt (liest `_csrf` aus POST oder `X-CSRF-Token` aus HTTP-Header). |
| **2** | `app/src/Core/Response.php` | `SystemTechController` rief `Response::success($msg, $data)` auf; in `Response` existierten nur `json()`, `html()`, `error()`. | `public static function success(...)` in `Response.php` ergänzt (flexibel für `(string, array)` und `(array, string)`). |
| **3** | `ActorsController::detail()` | SQL-Abfrage für Fotoalben eines Darstellers nutzte `pa.release_datetime` und `pa.status`, obwohl `photo_albums` die Spalte `status` besitzt, das Freigabedatum aber nur in `photo_albums_online.release_date_time` bzw. `photo_albums.check_date_time` liegt. | Query auf `COALESCE(pao.release_date_time, pa.check_date_time)` korrigiert. |
| **4** | `MerchantsController::detail()` | SQL-Abfrage für die Filme eines Händlers selektierte `m.created_datetime` auf `movies` (Spalte existiert nur in `actors`, in `movies` heißt sie `upload_datetime`). | Auf `m.upload_datetime` korrigiert. |
| **5** | `UsersController::detail()` & `HomeController::globalSearch()` | `UsersController::detail()` selektierte `pao.title` statt `pao.name` auf `photo_albums_online`; `HomeController::globalSearch()` selektierte `m.account_status` auf `merchants` (Spalte existiert nicht in `merchants`). | Auf `pao.name AS album_title` bzw. ohne `m.account_status` korrigiert (inkl. selbigem Check in `AffiliateController::webmasters()`). |
| **6** | `web/assets/js/app.js` | In `MoviesChecking/views/detail.php` riefen Zeitstempel-Badges `App.seekMoviePlayerTo(sec)` auf, während die Methode nur als `App.MoviesChecking.seekPlayer(sec)` existierte. | Top-Level-Alias `App.seekMoviePlayerTo(seconds)` in `app.js` ergänzt. |


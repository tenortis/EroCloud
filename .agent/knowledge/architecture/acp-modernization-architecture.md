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

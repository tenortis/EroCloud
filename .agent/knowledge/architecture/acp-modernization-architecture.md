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
   * **`web/` (Öffentliches DocumentRoot):** Enthält ausschließlich den Einstiegspunkt (`index.php` mit App-Shell & integriertem Admin-Login), den AJAX-Dispatcher (`api.php`) sowie die lokalen statischen Assets (`assets/css`, `assets/js`, `assets/img`, `assets/fonts` – 100 % DSGVO-konform ohne externe CDNs).
   * **`app/` (Geschützter Anwendungskern):** Durch `.htaccess` (`Require all denied`) vollständig gegen direkten Browserzugriff gesperrt. Enthält `bootstrap.php`, Core-Klassen (`Autoloader`, `Csrf`, `Request`, `Response`, `Router`, `View`) und die fachlichen Module (`Home`, `Preview` sowie die schrittweise folgenden Fach-Controller).
3. **Dynamisches Nachladen ohne Seiten-Reload (SPA-Architektur):**
   Ein Klick in der linken Admin-Sidebar lädt die Ansicht verzögerungsfrei über Vanilla-JavaScript (`fetch('api.php?route=...')`) in `#app-content`, ohne die gesamte Seite neu zu laden.
4. **Look & Feel (Einheitliches Kacheldesign in Pay4Coins-Blau):**
   * Helles, übersichtliches Standard-Theme (**Light Mode Default** mit `#F8FAFC` Hintergrund und weißen Kacheln `#FFFFFF`, optionaler Dark-Mode-Toggle in der Topbar via `localStorage['ec-acp-theme']`).
   * Primärfarbe **Pay4Coins-Blau** (`#4297D7` / `#0284C7`).
5. **100 % Einhaltung von `@master.md` & PHP 8.4.27 Kompatibilität:**
   * `declare(strict_types=1);` und `define('SAFE_INC', 1);`
   * Datenbankzugriff ausschließlich über `p4c_query($sql, __FILE__, __LINE__)` und zentrales Error-Logging in `/var/www/web1/htdocs/log/.errorlog`.
   * Durchgehend **UTF-8** inkl. automatischer Konvertierung historischer `ISO-8859-1`-Einträge (`HomeController::decodeLegacyText()`).
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
│           └── Preview/
│               ├── PreviewController.php      # Modul-Platzhalter für den schrittweisen Ausbau
│               └── views/
│                   └── section_placeholder.php
└── web/
    ├── .htaccess                 # RewriteEngine (/Startseite -> index.php), Security-Header
    ├── index.php                 # App-Shell (Sidebar, Topbar mit 30-Min-Timer & Schnellsuche, Login-Portal)
    ├── api.php                   # Zentraler AJAX-Dispatcher für SPA-Fragmente
    └── assets/
        ├── css/
        ├── js/
        ├── fonts/
        └── img/
```

---

## 3. 🔐 Berechtigungskonzept (`employee_access_area`) & Auto-Logout

1. **Granulare Rechteprüfung (`acp_has_access(string $area)`):**
   Sowohl beim Rendern der Sidebar in `web/index.php` als auch bei jedem API-Aufruf in `App\Core\Router::dispatch()` wird geprüft, ob der eingeloggte Mitarbeiter `'all'` oder den jeweiligen Bereichsschlüssel (`content_check`, `content_online`, `actors`, `websites`, `affiliate_program`, `webcams`, `merchants`, `users`, `point_system`, `chats`, `all_messages`, `error_log`) in `$_SESSION['employee_access_area']` besitzt.
2. **30-Minuten Auto-Logout Countdown (`#acp-logout-timer`):**
   * In der Topbar läuft ein sekundengenauer Countdown von `30:00` herunter (analog `ACP_SESSION_DURATION`).
   * Jede SPA-Navigation oder Interaktion setzt den Countdown automatisch wieder auf `30:00` zurück.
   * Bei Erreichen von `00:00` erfolgt der automatische Logout über `index.php?logout=auto` inkl. Protokollierung in `employee_history`.

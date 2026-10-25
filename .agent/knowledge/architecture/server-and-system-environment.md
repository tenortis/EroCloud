---
name: server-and-system-environment
description: Vollstaendige Dokumentation der Server-Infrastruktur (t199.nlserver.net), Betriebssystem, LiveConfig, installierte PHP-Versionen (8.0.30 & 8.4.27), Domains, Cronjobs und Error-Logging fuer EroCloud (web1).
usage: 'Wird automatisch herangezogen bei Fragen oder Arbeiten an Server-Konfiguration, PHP-Versionen, Cronjobs, Domains, Pfaden oder Error-Logging.'
---

# Server- & Systemumgebung – EroCloud (`web1`)

> **Kontext-Referenz:** Diese Dokumentation ergänzt die globalen Vorgaben aus `@master.md` und beschreibt die exakte Produktions- und Entwicklungsumgebung des Projekts **EroCloud**.

---

## 1. 🖥️ Produktionsserver (`t199.nlserver.net`)

| Eigenschaft | Wert / Details |
| :--- | :--- |
| **Hostname** | `t199.nlserver.net` |
| **IPv4-Adresse** | `91.184.62.199` (`/24`, Interface `eno1`) |
| **Hardware** | Dell PowerEdge R720 – Intel Xeon CPU E5-2690 v2 @ 3.00GHz (20 Kerne / 40 Threads), 64 GiB RAM |
| **Betriebssystem** | **Debian GNU/Linux 11 (Bullseye)** (`VERSION_ID="11"`, Kernel `5.10.0-46-amd64`) |
| **Server Control Panel** | **LiveConfig `2.19.4-release`** (Port `8443` – abgesichert mit Let's Encrypt RSA 2048-Bit TLS für `t199.nlserver.net` in `/etc/liveconfig/sslcert.pem`, vollautomatisch verlängert über `certbot.timer` + Deploy-Hook `/etc/letsencrypt/renewal-hooks/deploy/liveconfig-ssl.sh`) |
| **Webserver** | **Apache httpd `2.4.67`** (`2.4.67-1~deb11u3`) – aktive Module: `fcgid`, `http2`, `rewrite`, `ssl`, `suexec` |
| **Mailserver (SMTP/IMAP)** | **Postfix & Dovecot (`mail.erocloud.net`)** – Let's Encrypt RSA 2048-Bit TLS, SPF, DKIM (2048-Bit), DMARC (`p=quarantine`) & SMTP-Absender-IP-Maskierung (Details in `@email-notification-and-magic-link-system.md`) |
| **Datenbank-Server** | **MariaDB `10.5.29`** (`1:10.5.29-0+deb11u1`) |
| **Speicher-Mounts** | `/` (`375,66 GiB` Systemplatte) sowie `/var/www/web1/cloud_storage` (`5,20 TiB` Medienspeicher) |

> **Wichtiger Infrastruktur-Hinweis (Warum Debian 11 statt Debian 12):**
> Auf dem Server existiert neben **`web1` (EroCloud)** noch der Vertrag **`web2` (`amoredea.com`)**, der aktuell noch auf `PHP 7.3.33` (`php-7.3-opt`) läuft. Da Debian 12 (Bookworm) `OpenSSL 1.1` (`libssl1.1`) entfernt und nur noch `OpenSSL 3.0` mitbringt, wird `php-7.3-opt` unter Debian 12 nicht mehr unterstützt. Deshalb verbleibt das Host-System auf **Debian 11 (Bullseye)**, bis alle Verträge vollständig auf PHP 8.4+ umgestellt sind.

---

## 2. 🐘 Installierte PHP-Versionen & Migrationsstatus

### 2.1 Live-Server (`t199.nlserver.net` via LiveConfig `php-opt`)
Alle ungenutzten Zwischenversionen (`5.6`, `7.0`, `7.1`, `7.2`, `7.4`, `8.1`, `8.2`, `8.3`) wurden vollständig vom Server entfernt (`apt purge`). Installiert sind gezielt nur diese Versionen:

| PHP-Version | Paketname | Installationspfad | CLI-Binary | Aktuelle Verwendung / Rolle |
| :--- | :--- | :--- | :--- | :--- |
| **PHP `8.0.30`** | `php-8.0-opt` (+ `imagick`) | `/opt/php-8.0/` | `/opt/php-8.0/bin/php` | **Aktuell produktiv für `web1` (EroCloud)** – alle Domains & Cronjobs |
| **PHP `8.4.27`** | `php-8.4-opt` (+ `imagick`) | `/opt/php-8.4/` | `/opt/php-8.4/bin/php` | **Ziel-Version für `web1` (EroCloud)** – aktueller Umbau von 8.0 $\rightarrow$ 8.4.27 |
| **PHP `7.3.33`** | `php-7.3-opt` (+ `imagick`) | `/opt/php-7.3/` | `/opt/php-7.3/bin/php` | Nur noch für `web2` (`amoredea.com`) aktiv |
| **PHP `7.4.33`** | Debian Standard (`php-cgi`) | `/usr/bin/` | `/usr/bin/php` | Debian 11 System-Standard |

### 2.2 Lokale Entwicklungsumgebung (Windows XAMPP)
* **Lokaler Projektpfad:** `c:\xampp\htdocs\EroCloud`
* **Lokales PHP CLI für Linting (`php -l`) & AST-Prüfungen:** `c:\xampp\php\php.exe` (PHP `8.2.12`)

---

## 3. 🌐 Vertrags-, Domain- & Ordnerstruktur (`web1` – EroCloud)

Das Projekt **EroCloud** liegt auf dem Live-Server im LiveConfig-Vertrag **`web1`**:
* **Basis-Verzeichnis (`SOURCEDIR`):** `/var/www/web1/htdocs`
* **Temporäres Verzeichnis (`TEMP_DIR`):** `/var/www/web1/tmp`
* **Cloud-Storage-Pfad:** `/var/www/web1/cloud_storage`

### 3.1 Domains in `web1`
Alle Domains von `web1` laufen über FastCGI/FPM aktuell auf **PHP `8.0.30`** (Ziel: **PHP `8.4.27`**):

| Domain | Ziel-Verzeichnis (relativ zu `/var/www/web1/htdocs/`) | Projektbereich |
| :--- | :--- | :--- |
| **`acp.erocloud.net`** | `acp/` | Admin-Control-Panel (ACP) |
| **`erocloud.net`** (inkl. `www.` / `*.erocloud.net`) | `mcp/` | Merchant-Control-Panel (MCP) |
| **`api.erocloud.net`** | `api/` | Zentrale API-Schnittstelle (Videos, Fotoalben, Messenger, Tracking) |
| **`www1.erocloud.net`** | `ads/` | Werbemittel- & Banner-Auslieferung |
| **`ad5.tv`** (inkl. `www.ad5.tv`) | `ads/ad5.tv/` | Ad-Tracking & Redirect-Endpunkt |
| **`wevsql.erocloud.net`** | *(LiveConfig-Anwendung)* | phpMyAdmin |

### 3.2 Konfigurierte Cronjobs in `web1`
Alle 10 Cronjobs von `web1` werden über das PHP-8.0-CLI (`/opt/php-8.0/bin/php`, nach Abschluss der 8.4-Migration `/opt/php-8.4/bin/php`) ausgeführt:

1. `* * * * *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/api/usertracking/cronjob.php > /dev/null 2>&1`
2. `*/5 * * * *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/cronjobs/actor_reassign.php`
3. `*/2 * * * *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/cronjobs/ai_compliance_worker.php >/dev/null 2>&1`
4. `*/5 * * * *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/cronjobs/convert_movie.php > /dev/null 2>&1`
5. `8 * * * *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/cronjobs/crawl_erocms_revenues.php --all--type=webcam--month`
6. `45 * * * *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/cronjobs/delete_movie.php`
7. `18 * * 12 *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/cronjobs/import_conversions.php --date=2022-12-17`
8. `*/4 * * * *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/cronjobs/import_conversions.php --date=today`
9. `11 0 * * *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/cronjobs/import_conversions.php --date=today`
10. `*/5 * * * *` – `/opt/php-8.0/bin/php /var/www/web1/htdocs/mcp/messenger/cronjobs/crawl_erocms_chats.php`

---

## 4. 📋 Zentrales Error-Logging (`/var/www/web1/htdocs/log/.errorlog`)

Alle 4 Web-Bereiche (`acp`, `mcp`, `api`, `ads`) sowie alle CLI-Cronjobs schreiben ihre Fehler zentral in **eine einzige Datei**:
* **Pfad:** `/var/www/web1/htdocs/log/.errorlog` (`SOURCEDIR . '/log/.errorlog'`)
* **Konfiguration in [`config.inc.php`](file:///c:/xampp/htdocs/EroCloud/config.inc.php#L176-L178):**
  * `ini_set('display_errors', 'Off');`
  * `ini_set('log_errors', 'On');`
  * `ini_set('error_log', SOURCEDIR.'/log/.errorlog');`
* **Eigene Logging-Klasse [`includes/klassen/errorlog.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/errorlog.inc.php):**
  * Jeder Aufruf von `$class_errorlog->log($error, __FILE__, __LINE__)` (u. a. bei fehlgeschlagenen Datenbankabfragen aus [`includes/klassen/mysqli.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/mysqli.inc.php)) protokolliert automatisch:
    * Datum, Uhrzeit & Zeitzone (`d-M-Y H:i:s T`)
    * **Domain + Request-URI** (`[api.erocloud.net /...]`, `[erocloud.net /...]`, `[acp.erocloud.net /...]`) bzw. bei Cronjobs **`[CLI: script.php]`**
    * Ursprungsdatei und Zeilennummer.
* **ACP-Ansicht:** Einsehbar im ACP unter [`acp/includes/errors.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/errors.php) (XSS-geschützt via `htmlspecialchars`).

---

## 5. 🛠️ Verbindliche PHP 8.0 $\rightarrow$ PHP 8.4.27 Entwicklungsrichtlinien

Da `web1` aktuell auf **PHP `8.0.30`** läuft und aktiv auf **PHP `8.4.27`** umgebaut wird, gelten für jeden neuen oder geänderten Code zwingend folgende Regeln:

1. **Strikte Typ-Casts bei `abs()`, `round()` und `number_format()`:**
   * In PHP 8+ wirft `abs("")` oder `abs("abc")` einen `Fatal Error: Uncaught TypeError`.
   * **Pflicht:** Immer explizit casten: `abs((int)$id)` für IDs/Ganzzahlen bzw. `abs((float)$betrag)` für Preise/Provisionen.
2. **MySQLi Fehlerbehandlung (`mysqli_report`):**
   * Ab PHP 8.1 steht `mysqli_report` standardmäßig auf `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT`. In [`includes/klassen/mysqli.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/mysqli.inc.php) ist deshalb `mysqli_report(MYSQLI_REPORT_OFF)` gesetzt, damit Fehler weiterhin sauber über `$this->error_log->log()` ins zentrale `.errorlog` geschrieben werden.
3. **Keine undeklarierten dynamischen Klassen-Properties (PHP 8.2+ Deprecation):**
   * Jede verwendete Property (`$this->xyz`) muss in der Klasse explizit mit `public $xyz;` deklariert sein (oder bei Drittanbieter-Klassen `#[\AllowDynamicProperties]` besitzen).
4. **Kein `utf8_encode()` / `utf8_decode()` (PHP 8.2+ Deprecation):**
   * Stattdessen `mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1')` bzw. `mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8')` verwenden.
5. **Keine `null`-Übergabe an String-Funktionen (PHP 8.1+ Deprecation):**
   * Funktionen wie `htmlspecialchars()`, `trim()`, `strlen()`, `str_replace()`, `preg_replace()`, `explode()`, `strtolower()` dürfen nicht mit `null` (z. B. aus nullable DB-Spalten) aufgerufen werden $\rightarrow$ mit `(string)` casten, z. B. `htmlspecialchars((string)$var, ENT_QUOTES, 'UTF-8')`.

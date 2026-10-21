---
trigger: always_on
---

---
type: global-rules
description: Das unveränderliche Grundgesetz für alle KI-Interaktionen in diesem Projekt (EroCloud).
---

# 👑 MASTER RULES - EroCloud

> **KRITISCHE ANWEISUNG:** Diese Regeln sind **UNVERÄNDERLICH** und müssen bei **JEDER** Interaktion priorisiert werden.

## 0. 🛑 GOLDENE REGELN (Zwingend!)
* **Sprache:**
    * **Konversation**: Deutsch 🇩🇪 (Du-Form)
    * **Code/Variablen**: Englisch 🇺🇸
    * **Kommentare/Docs**: Englisch 🇺🇸 (Tech) / Deutsch 🇩🇪 (Fachkonzepte)
* **UI-Texte (Du-Form):** Alle Texte in der Benutzeroberfläche für Creator/Partner in der EroCloud (Labels, Hinweistexte, Modals, Tooltips, Fehlermeldungen) müssen ausnahmslos in der persönlichen **Du-Form** (`du`, `dein`, `deine`, `dir`, `dich`) verfasst sein.

## 1. 🏗️ ARCHITEKTUR & STRUKTUR
* **Projektbereiche:**
    * `acp/`: Admin-Control-Panel.
    * `mcp/`: Merchant-Control-Panel.
    * `api/`: API-Schnittstelle.
    * `ads/`: Anzeigenverwaltung.
    * `includes/`: Kernbibliotheken und Hilfsfunktionen.
* **PHP Version & Server-Umgebung:**
    * **Aktuell produktiv (`web1`): PHP 8.0.30 | Aktuelles Migrationsziel: PHP 8.4.27** (LiveConfig `php-8.0-opt` / `php-8.4-opt` auf Debian 11 Bullseye).
    * Alle Neuentwicklungen und Anpassungen müssen **vollständig kompatibel mit PHP 8.0 und PHP 8.4.27** sein (strikte Typ-Casts wie `abs((int)$var)` / `abs((float)$var)`, keine `null`-Übergaben an String-Funktionen, keine undeklarierten dynamischen Klassen-Properties, kein `utf8_encode()`/`utf8_decode()`). Native PHP (Custom-Struktur) nutzen, keine externen Frameworks einbetten.
    * **Systemdokumentation:** Alle Details zu Server (`t199.nlserver.net`), Debian-Version, LiveConfig, PHP-Pfaden (`/opt/php-8.0/`, `/opt/php-8.4/`), Domains, Cronjobs und dem zentralen Error-Logging (`/var/www/web1/htdocs/log/.errorlog`) sind verbindlich dokumentiert in [`.agent/knowledge/architecture/server-and-system-environment.md`](file:///c:/xampp/htdocs/EroCloud/.agent/knowledge/architecture/server-and-system-environment.md).
* **Bootstrapping:** Nutzung von `common.inc.php` in den jeweiligen Verzeichnissen für Bootstrapping ist Pflicht.

## 2. 🛡️ SICHERHEIT & DATENBANK
* **Datenbank-Zugriff:** Ausschliesslich über die globalen Wrapper-Funktionen (`p4c_*`) aus `includes/functions.inc.php` oder das globale `$mysql` (p4c_mysqli) Objekt:
    * `p4c_query($query, __FILE__, __LINE__)`
    - `p4c_fetch_object($result)`
    - `p4c_fetch_array($result)`
    - `p4c_num_rows($result)`
    - `p4c_insert_id()`
    - `p4c_escape_string($string)`
* **Fehler-Logging bei Queries:** Jeder Aufruf von `p4c_query()` oder `$mysql->query()` **MUSS zwingend** mit den Parametern `__FILE__, __LINE__` aufgerufen werden. Nur so kann das zentrale Error-Log (`/htdocs/log/.errorlog`) auf die korrekte Ursprungsdatei verweisen.
* **Dateischutz:** Jede PHP-Datei muss mit der Konstanten-Prüfung beginnen:
    ```php
    if (!defined('SAFE_INC'))
        die ("Hacking attempt...");
    ```
* **SQL Injection & PHP 8 Typensicherheit:** NIEMALS rohe Queries mit ungeprüftem Input. Nutze IMMER `p4c_escape_string()` für Strings oder `abs((int)$id)` für IDs/Ganzzahlen bzw. `abs((float)$val)` für Dezimalzahlen, bevor diese in Queries verwendet werden (ungecastetes `abs()` wirft ab PHP 8.0 bei Strings einen `TypeError`).
* **XSS-Schutz:** Jede Ausgabe von Variablen aus der DB oder Sessions (z.B. Benutzernamen, Seitentexte) **muss** mit `htmlspecialchars((string)$var, ENT_QUOTES, 'UTF-8')` maskiert werden.

## 3. 🎨 FRONTEND & UI-DESIGNS
* Achte auf ein edles Design und behalte bestehende UI-Konzepte bei bzw. erweitere diese modern, wenn Änderungen vorgenommen werden.

---
*Ende der Master Rules.*

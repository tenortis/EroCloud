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
* **UI-Texte (Sie-Form):** Alle Texte in der Benutzeroberfläche (Labels, Hinweistexte, Modals, Tooltips) müssen ausnahmslos in der höflichen **Sie-Form** verfasst sein.

## 1. 🏗️ ARCHITEKTUR & STRUKTUR
* **Projektbereiche:**
    * `acp/`: Admin-Control-Panel.
    * `mcp/`: Merchant-Control-Panel.
    * `api/`: API-Schnittstelle.
    * `ads/`: Anzeigenverwaltung.
    * `includes/`: Kernbibliotheken und Hilfsfunktionen.
* **PHP Version:**
    * Native PHP (Legacy & Custom-Struktur). Keine externen Frameworks einbetten, sondern vorhandene Struktur nutzen.
* **Bootstrapping:** Nutzung von `common.inc.php` in den jeweiligen Verzeichnissen für Bootstrapping ist Pflicht.

## 2. 🛡️ SICHERHEIT & DATENBANK
* **Datenbank-Zugriff:** Ausschliesslich über die globalen Wrapper-Funktionen (`p4c_*`) aus `includes/functions.inc.php` oder das globale `$mysql` (p4c_mysqli) Objekt:
    * `p4c_query($query, __FILE__, __LINE__)`
    - `p4c_fetch_object($result)`
    - `p4c_fetch_array($result)`
    - `p4c_num_rows($result)`
    - `p4c_insert_id()`
    - `p4c_escape_string($string)`
* **Fehler-Logging bei Queries:** Jeder Aufruf von `p4c_query()` oder `$mysql->query()` **MUSS zwingend** mit den Parametern `__FILE__, __LINE__` aufgerufen werden. Nur so kann das Error-Log auf die korrekte Ursprungsdatei verweisen.
* **Dateischutz:** Jede PHP-Datei muss mit der Konstanten-Prüfung beginnen:
    ```php
    if (!defined('SAFE_INC'))
        die ("Hacking attempt...");
    ```
* **SQL Injection:** NIEMALS rohe Queries mit ungeprüftem Input. Nutze IMMER `p4c_escape_string()` für Strings oder `abs()` für IDs/Zahlen, bevor diese in Queries verwendet werden.
* **XSS-Schutz:** Jede Ausgabe von Variablen aus der DB oder Sessions (z.B. Benutzernamen, Seitentexte) **muss** mit `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` maskiert werden.

## 3. 🎨 FRONTEND & UI-DESIGNS
* Achte auf ein edles Design und behalte bestehende UI-Konzepte bei bzw. erweitere diese modern, wenn Änderungen vorgenommen werden.

---
*Ende der Master Rules.*

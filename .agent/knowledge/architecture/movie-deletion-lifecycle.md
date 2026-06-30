---
name: movie-deletion-lifecycle
description: Umfassende Dokumentation und Analyse des Lösch-Lebenszyklus von Filmen in EroCloud sowie Aufzählung von Logikkonflikten.
usage: 'Referenz für Arbeiten an der Löschlogik im MCP, ACP und in Cronjobs.'
---

# 🗑️ Lösch-Lebenszyklus von Filmen (Movie Deletion Lifecycle)

Dieses Dokument beschreibt die Mechanismen, Regeln und Abläufe beim Löschen von Filmen im gesamten EroCloud-System. Es unterteilt sich in die verschiedenen Löschpfade, die physische Bereinigung per Cronjob und eine Analyse von identifizierten Logikkonflikten im aktuellen System.

---

## 1. 📂 Datenbank-Tabellen & Felder
Das Löschen von Filmen interagiert mit folgenden Datenbank-Tabellen:
* **`movies`**: Haupttabelle für Filme (Creator-Entwürfe und Historie).
  * Spalten: `status` (`'active'`, `'inactive'`, `'blocked'`, `'deleted'`), `deleted_datetime` (Zeitpunkt der Soft-Löschung).
* **`movies_online`**: Liste der aktuell auf den Portalen sichtbaren und aktiven Filme.
* **`movies_access`**: Kundenzugriffe/Käufe für die jeweiligen Filme.
* **`movies_deleted`**: Log-/Archivtabelle für gelöschte Filme (wird nur vom Cronjob befüllt).
  * Zusätzliche Spalten: `folder_size_bytes`, `deleted_by`.

---

## 2. 🛣️ Die drei Löschpfade (Delete Pathways)

Im System existieren drei unterschiedliche Wege, wie Filme gelöscht werden:

### Pfad A: Soft-Deletion bei Darsteller-Löschung (Admin - ACP)
* **Datei**: `acp/includes/actor.php`
* **Trigger**: Wenn ein Administrator den Status eines Darsteller-Profils auf `'deleted'` setzt.
* **Ablauf**: 
  Alle dem Darsteller zugeordneten Filme werden in den Tabellen `movies` und `movies_online` auf `status = 'deleted'` und `deleted_datetime = NOW()` gesetzt:
  ```php
  p4c_query("UPDATE `movies` SET `status`='deleted', `deleted_datetime`='".date("Y-m-d H:i:s")."' WHERE `actor_id`='".abs($actor_id)."';");
  p4c_query("UPDATE `movies_online` SET `status`='deleted', `deleted_datetime`='".date("Y-m-d H:i:s")."' WHERE `actor_id`='".abs($actor_id)."';");
  ```

### Pfad B: Manuelle Soft-Deletion eines Filmes (Admin - ACP)
* **Dateien**: `acp/includes/movie_edit.php` und `acp/includes/movie_checking.php`
* **Trigger**: Ein Administrator setzt den Status eines einzelnen Filmes explizit auf `'deleted'`.
* **Ablauf**:
  * Setzt das Feld `deleted_datetime` auf den aktuellen Zeitstempel.
  * Aktualisiert den Status des Filmes in `movies` (und falls vorhanden in `movies_online`) auf `'deleted'`.

### Pfad C: Sofortige Hard-Deletion durch den Creator (Merchant - MCP)
* **Dateien**: `mcp/includes/movie.php` (beim Bearbeiten) und `mcp/includes/movie_upload.php` (beim Abbrechen des Uploads).
* **Trigger**: Der Creator löscht seinen Film direkt im Merchant Control Panel.
* **Ablauf**:
  * Es wird die lokale Funktion `delete_directory()` aufgerufen.
  * Diese löscht **sofort physisch** alle Videodateien (Desktop-MP4, Mobil-MP4, WebM, OGV sowie alle Thumbnails und Custom Covers) im Verzeichnis:
    `MOVIES_PATH/storage_location/merchant_id/movie_id/`
  * Anschließend werden die Einträge in der Datenbank über ein direktes SQL-`DELETE` gelöscht:
    ```php
    p4c_query("DELETE FROM `movies` WHERE `id`='...' AND `merchant_id`='...' LIMIT 1;");
    p4c_query("DELETE FROM `movies_online` WHERE `id`='...' AND `merchant_id`='...' LIMIT 1;");
    ```

---

## 3. 🧹 Asynchrones Aufräumen & Archivieren (Cronjob)
Der Cronjob `cronjobs/delete_movie.php` läuft im Hintergrund und übernimmt das physische Löschen für Soft-deleted und inaktive Filme.

### Ablauf:
1. **Zielgruppen-Bestimmung**:
   * **Gruppe 1**: Filme mit `status = 'deleted'` und (`deleted_datetime = '0000-00-00 00:00:00'` ODER `deleted_datetime < 1 Jahr`).
   * **Gruppe 2 (Bei Inaktivität)**: Wenn keine Filme aus Gruppe 1 vorhanden sind, greift die automatische Speicherplatz-Optimierung. Es werden bis zu 10 Filme gelöscht, die älter als 6 Jahre sind (`online_at < -6 years`) und auf die in den letzten 6 Jahren kein Zugriff/Kauf stattfand (`movies_access.access_token_datetime < -6 years`).
2. **Archivierung & Löschung**:
   * Die Gesamtgröße des Ordners wird ermittelt (`getFolderSize`).
   * Der Ordner unter `MOVIES_PATH/...` wird rekursiv gelöscht.
   * Der Datensatz wird in die Logtabelle `movies_deleted` verschoben (INSERT SELECT):
     ```sql
     INSERT INTO movies_deleted (..., folder_size_bytes, deleted_by)
     SELECT m.*, [folder_size] AS folder_size_bytes, 'cronjob' AS deleted_by
     FROM movies m WHERE m.id = [movie_id]
     ```
   * Die Datensätze werden aus `movies_access`, `movies` und `movies_online` gelöscht.

---

## 4. 🛑 Identifizierte Logikkonflikte & Risiken

Bei der Code-Analyse wurden folgende Logikkonflikte und potenzielle Probleme identifiziert:

### ⚠️ Konflikt 1: MCP-Löschung umgeht den Kundenschutz für bereits gekaufte Filme
* **Problem**: Wenn ein Admin einen Film oder ein Profil löscht, wird dies als **Soft-Delete** durchgeführt. Die Begründung im UI lautet: *"Bereits gekaufte Filme werden nicht gelöscht und stehen dem Kunden weiterhin zur Verfügung."* Der Cronjob stellt sicher, dass diese Filme erst nach 1 Jahr gelöscht werden (Karenzzeit für Downloads/Streaming).
* **Konflikt**: Löscht ein Merchant (Creator) seinen Film über das MCP (`mcp/includes/movie.php`), wird das Verzeichnis **sofort physisch gelöscht** und die DB-Einträge entfernt. Kunden, die diesen Film bereits rechtmäßig erworben haben, verlieren augenblicklich und ohne Vorwarnung den Zugriff auf ihren bezahlten Content.

### ⚠️ Konflikt 2: Fehlendes Archiv-Log bei MCP-Löschung
* **Problem**: Der Cronjob verschiebt gelöschte Filme vor dem Löschen in die Tabelle `movies_deleted`, um den Vorgang zu protokollieren.
* **Konflikt**: Wenn der Händler den Film im MCP löscht, wird der Eintrag direkt per SQL-`DELETE` entfernt. Es findet **kein Eintrag in `movies_deleted`** statt. Bei Support-Anfragen von Kunden bezüglich verschwundener Filme fehlt jegliche Historie, wann und von wem der Film gelöscht wurde.

### ⚠️ Konflikt 3: Sofortiges Löschen bei Standard-Timestamp (`0000-00-00 00:00:00`)
* **Problem**: Die Cronjob-Bedingung löscht Filme sofort, wenn `deleted_datetime` den Standardwert `'0000-00-00 00:00:00'` aufweist.
* **Konflikt**: Sollte bei einer Soft-Löschung (z. B. durch ein Update-Statement oder DB-Standardwerte) `deleted_datetime` nicht korrekt befüllt werden, greift die 1-jährige Schutzfrist nicht. Der Film wird sofort beim nächsten Cron-Durchlauf vernichtet.

### ⚠️ Konflikt 4: Code-Duplizierung
* **Problem**: Die Funktion `delete_directory()` ist in `mcp/includes/movie.php` und `mcp/includes/movie_upload.php` redundant deklariert.
* **Konflikt**: Änderungen an der Löschlogik (z. B. Behebung des Konflikts 1) müssen an mehreren Stellen nachgezogen werden, was die Fehleranfälligkeit erhöht.

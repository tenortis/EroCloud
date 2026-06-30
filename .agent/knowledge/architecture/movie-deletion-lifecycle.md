---
name: movie-deletion-lifecycle
description: Umfassende Dokumentation und Analyse des Lösch-Lebenszyklus von Filmen in EroCloud sowie Aufzählung von Logikkonflikten und Bereinigungseinstellungen.
usage: 'Referenz für Arbeiten an der Löschlogik im MCP, ACP und in Cronjobs.'
---

# 🎥 Lösch-Lebenszyklus von Filmen (Movie Deletion Lifecycle)

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

## 2. 🔀 Die drei ursprünglichen Löschpfade (Delete Pathways)

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

## 3. 🛡️ Die neue optimierte Löschlogik (Best Practice)
Um den Speicherplatz effizient zu bereinigen und gleichzeitig die erworbenen Nutzungsrechte von Kunden zu schützen, wurde eine differenzierte Schutzfristen-Logik entwickelt. Diese wird in der **Lösch-Vorschau** (`acp/includes/content_cleanup.php`) simuliert:

### Regel 1: Nie gekauft
* **Zielgruppe**: Filme im Status `deleted`, die **nie gekauft** wurden (`movies_access`-Einträge = 0).
* **Frist**: **Sofortige physische Löschung** beim nächsten Cronjob-Lauf (0 Tage Wartezeit).

### Regel 2: Inaktiv (> 2 Jahre)
* **Zielgruppe**: Filme im Status `deleted`, die zwar gekauft wurden, deren letzter Kauf und letzter Video-View jedoch **über 2 Jahre zurückliegen**.
* **Frist**: **30 Tage Wartezeit** nach der Vormerkung (Soft-Delete) als Sicherheitskarenzzeit.

### Regel 3: Aktiv (< 2 Jahre)
* **Zielgruppe**: Filme im Status `deleted`, bei denen innerhalb der letzten 2 Jahre ein Kauf oder ein View stattfand.
* **Frist**: **365 Tage Wartezeit** ab dem Soft-Delete-Datum (1 Jahr Download- & Streaming-Garantie für aktive Kunden).

### Regel 4: Alt & Abgelehnt (> 180 Tage)
* **Zielgruppe**: Filme im Status *Abgelehnt* (`released = 2`) oder *Gesperrt* (`status = 'blocked'`), die nicht gelöscht wurden, aber seit **über 180 Tagen (6 Monaten) inaktiv** sind.
* **Frist**: **Sofortige physische Löschung** (da sie nie online waren und nie gekauft werden konnten).
* **Kaskadierende Altersprüfung**: Da bei älteren abgelehnten Filmen das Prüfdatum (`movie_checked`) manchmal den Standardwert `'0000-00-00 00:00:00'` besitzt, ermittelt das System das Alter dynamisch über den ersten befüllten Wert aus folgender Kaskade:
  1. `movie_checked` (Prüfdatum)
  2. `online_at` (Geplantes Online-Datum)
  3. `create_datetime` (Erstellungsdatum)
  4. `last_updated_datetime` (Letzte Bearbeitung)

---

## 4. 🖥️ Benutzeroberfläche & Bereinigungssimulation (ACP)

### A. Lösch-Vorschau-Modul (`/Content-Bereinigung`)
* **Pfad**: `acp/includes/content_cleanup.php`
* **Features**:
  * **Statistikbox**: Zeigt live die exakte Anzahl der Filme an, die beim nächsten Cronjob-Lauf gelöscht werden, sowie den exakten, freizugebenden Speicherplatz auf dem Server (in MB/GB/TB).
  * **Interaktive Filter-Pills**: Schnelle Filterung der Datensätze nach Regel 1, Regel 2, Regel 3 und Regel 4 ohne Seiten-Reload (mittels DataTables `fnFilter`).
  * **Präzise Datumssortierung**: Umgehung von DataTables-Sortierungsproblemen bei deutschen Datumsformaten durch Vorschalten eines unsichtbaren Unix-Timestamps:
    `'<span style="display:none;">' . $timestamp . '</span>' . $formatted_date`
  * **Händler-/Darsteller-Zuordnung**: Auflistung des Profilnamens (Darsteller) inklusive Direktlink in das ACP-Händlerprofil.

### B. Überarbeitung der Blockierten Filme (`/gesperrte-Filme`)
* **Pfad**: `acp/includes/movies_blocked.php` (und AJAX-Schnittstelle)
* **Features**:
  * **Statustext-Badges**: Eindeutige Kennzeichnung des Filmstatus per Badge direkt neben dem roten Deaktivierungs-Icon:
    * `Löschung` (Rot) für soft-gelöschte Filme.
    * `Abgelehnt` (Orange) für abgelehnte Filme.
    * `Gesperrt` (Grau) für manuell gesperrte Filme.
  * **Bearbeitungsdatum-Spalte**: Zeigt über die neue Spalte "Bearbeitet am" das genaue Datum der letzten Statusänderung an (gelöscht am / geprüft am).

---

## 5. ⚠️ Identifizierte Logikkonflikte & Risiken

Bei der Code-Analyse wurden folgende Logikkonflikte und potenzielle Probleme identifiziert:

### ⚡ Konflikt 1: MCP-Löschung umgeht den Kundenschutz für bereits gekaufte Filme
* **Problem**: Wenn ein Admin einen Film oder ein Profil löscht, wird dies als **Soft-Delete** durchgeführt. Der Cronjob stellt sicher, dass diese Filme erst nach Ablauf der Schutzfristen gelöscht werden.
* **Konflikt**: Löscht ein Merchant (Creator) seinen Film über das MCP (`mcp/includes/movie.php`), wird das Verzeichnis **sofort physisch gelöscht** und die DB-Einträge entfernt. Kunden, die diesen Film bereits rechtmäßig erworben haben, verlieren augenblicklich den Zugriff auf ihren bezahlten Content.

### ⚡ Konflikt 2: Fehlendes Archiv-Log bei MCP-Löschung
* **Problem**: Der Cronjob verschiebt gelöschte Filme vor dem Löschen in die Tabelle `movies_deleted`, um den Vorgang zu protokollieren.
* **Konflikt**: Wenn der Händler den Film im MCP löscht, wird der Eintrag direkt per SQL-`DELETE` entfernt. Es findet **kein Eintrag in `movies_deleted`** statt. Bei Support-Anfragen von Kunden bezüglich verschwundener Filme fehlt jegliche Historie.

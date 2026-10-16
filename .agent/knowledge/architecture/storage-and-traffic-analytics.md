---
name: storage-and-traffic-analytics
description: Detaillierte Architektur- und Funktionsdokumentation der ACP-Analyse-Seite „Speicher & Traffic“ (/Speicher-Analyse), des Dateisystem-Scanners, der Traffic-Berechnung und des Cloud-Kostenrechners.
usage: 'Wird automatisch angewendet bei Fragen oder Erweiterungen zur Speicherplatz-Analyse, Streaming-Traffic-Berechnung, Server-Partitionsprüfung oder Cloud-Storage-Kalkulation.'
---

# 📊 Speicher- & Traffic-Analyse (`/Speicher-Analyse`)

> **Kontext-Referenzen:** Dieses Dokument ergänzt die allgemeinen Projektvorgaben aus `@master.md` sowie die Video-Lebenszyklus-Dokumentation in `@video-management.md`.

Dieses Dokument beschreibt den technischen Aufbau, die Speicherorte und die Berechnungslogik der Admin-Übersichtsseite **„Speicher & Traffic“** im Admin Control Panel (ACP) von EroCloud.

---

## 1. 📍 Verknüpfte Dateien & Routing im System

Damit jederzeit nachvollziehbar ist, an welchen Stellen die Speicher- und Traffic-Analysen im Code verbaut sind, sind hier alle beteiligten Dateien aufgelistet:

1. **URL-Rewrite (`acp/.htaccess`)**:
   * Route: `RewriteRule ^Speicher-Analyse$ index.php?mod=storage_analytics&%{QUERY_STRING}`
   * Öffentliche URL im ACP: `https://acp.erocloud.net/Speicher-Analyse`
2. **ACP-Hauptcontroller & Sidebar-Navigation (`acp/index.php`)**:
   * **Menüpunkt**: In der linken Sidebar unter **Content Online -> Speicher & Traffic** (`$navStorageAnalytics`).
   * **Submenü-Steuerung**: Das Untermenü `submenu_online` klappt bei aktivem Modul `storage_analytics` automatisch auf (`open_submenu("submenu_online")`).
   * **Modul-Einbindung**: `case "storage_analytics": include("./includes/storage_analytics.php"); break;`
3. **ACP-Frontend (`acp/includes/storage_analytics.php`)**:
   * Rendert die Benutzeroberfläche im jQuery-UI-Tab-Layout (`#storage_analytics_tabs`) in höflicher **Sie-Form** gemäß `@master.md`.
   * Ruft beim Laden der Seite asynchron per AJAX den Backend-Scanner `acp/includes/ajax/storage_analytics_scan.php` auf (bzw. mit `?force_refresh=1` beim Klick auf den Button **„Jetzt live neu berechnen“**).
4. **AJAX-Scanner & Berechnungs-Backend (`acp/includes/ajax/storage_analytics_scan.php`)**:
   * Führt den physischen Dateisystem-Scan, die Datenbank-Auswertung, die Partitionsprüfung sowie die Traffic- und Cloud-Kostenberechnung durch und liefert ein strukturiertes JSON-Objekt zurück.
5. **Ergänzende Schnellanzeige unter „Filme online“ (`acp/includes/movies_online.php`)**:
   * Zeigt oberhalb der Tabelle unter `/Filme-online` eine kompakte Zusammenfassung des reinen Online-Video-Speicherplatzes an (Cache-Datei: `sys_get_temp_dir() . '/erocloud_movies_online_size.cache'`).

---

## 2. ⚙️ Funktionsweise des Scanners (`acp/includes/ajax/storage_analytics_scan.php`)

### 2.1 24-Stunden-JSON-Cache & Auto-Healing
* **Cache-Pfad**: `sys_get_temp_dir() . '/erocloud_storage_analytics_cache.json'` (auf Linux i. d. R. `/tmp/erocloud_storage_analytics_cache.json`).
* **Gültigkeit**: `86400` Sekunden (24 Stunden).
* **Manuelle Neuberechnung**: Wird der Parameter `?force_refresh=1` übergeben (Button *„Jetzt live neu berechnen“*), wird der Cache ignoriert, alle Verzeichnisse live neu gescannt (Dauer auf dem Live-Server bei ~130.000 Dateien: ca. 10 Sekunden) und der Cache überschrieben.
* **Auto-Healing für Partitionsdaten**: Falls eine ältere Cache-Datei `disk.total_formatted === '0 Bytes'` enthält, aktualisiert das Skript beim Auslesen des Caches automatisch die `disk`-Sektion über `sa_get_disk_metrics()` und speichert den korrigierten Cache sofort zurück, ohne dass ein kompletter Verzeichnis-Scan nötig ist.

### 2.2 Physischer Verzeichnis-Scan vs. Lokale Bitraten-Simulation
Das Skript liest alle Filme aus der Tabelle `movies` und gleicht sie mit `movies_online` ab:
* **Pfadauflösung pro Film**:
  ```php
  $storage_loc = !empty($m->storage_location) ? $m->storage_location : MOVIES_DEFAULT_DIR;
  $movie_dir = MOVIES_PATH . '/' . $storage_loc . '/' . $m->merchant_id . '/' . $m->id;
  ```
* **Auf dem Live-Server (`$is_local_simulation = false`)**:
  * Jede Datei im Film-Verzeichnis wird per `@scandir()` und `@filesize()` exakt in Bytes erfasst und anhand ihres Dateinamens einer der 6 Format-Kategorien zugeordnet:
    1. `desktop_mp4`: `{id}_{file_id}.mp4` (bzw. `$m->filename`)
    2. `mobile_mp4`: `m_{id}_{file_id}.mp4`
    3. `mobile_webm`: `m_{id}_{file_id}.webm`
    4. `mobile_ogv`: `m_{id}_{file_id}.ogv`
    5. `thumbnails`: Alle `.jpg`-, `.jpeg`-, `.png`-, `.gif`-Dateien (`thumb_*`)
    6. `originals_other`: Temporäre Dateien während der Konvertierung (`neu_*`, `neu_qt_*`) oder noch nicht gelöschte Rohdateien
  * Zusätzlich werden rekursiv über `sa_scan_dir_bytes()` folgende Systemverzeichnisse physisch vermessen:
    * `MOVIES_PATH . '/cloud_storage/photo_albums'` (Fotoalben)
    * `MOVIES_PATH . '/cloud_storage/ads'` (Werbebanner)
    * `PROFILE_IMAGE_PATH . '/' . MERCHANT_DEFAULT_DIR` (`priv/merchant_files` – Händler-Ausweise, Verifizierungen, Profilbilder)
* **Auf der lokalen Entwicklungsumgebung (`$is_local_simulation = true`)**:
  * Wenn lokal keine physischen Film-Ordner vorhanden sind (`$physical_movie_folders_found == 0`), schaltet das Skript automatisch in den **Bitraten-Simulationsmodus** und blendet im Frontend einen blauen Hinweisbanner (`#sa-sim-notice`) ein.
  * Die Dateigrößen werden dabei anhand der echten Filmlängen (`movies.length` in Sekunden) und der FFmpeg-Parameter aus `cronjobs/convert_movie.php` berechnet:
    * Desktop MP4 (`8000k` Video + `196k` Audio): `1.024.500 Bytes/Sek.`
    * Mobile MP4 (`1000k` Video + `196k` Audio): `149.500 Bytes/Sek.`
    * Mobile WebM (`1000k` Video + `128k` Audio): `141.000 Bytes/Sek.`
    * Mobile OGV (`1000k` Video + `128k` Audio): `141.000 Bytes/Sek.`
    * 10 Thumbnails: pauschal `650.000 Bytes` pro Film
  * *Praxis-Validierung*: Auf dem Live-Server betrug die physisch gemessene Größe aller 78.938 Videodateien **4,23 TB**, während die Bitraten-Formel **4,25 TB** errechnete (Abweichung < 0,5 %).

### 2.3 Server-Partitionsprüfung (`sa_get_disk_metrics`)
Da auf produktiven Linux-Webservern (z. B. unter `open_basedir` oder gehärteter `php.ini`) die PHP-Funktion `disk_total_space()` häufig in `disable_functions` gesperrt ist, während `disk_free_space()` weiterhin funktioniert, arbeitet `sa_get_disk_metrics($disk_check_path, $fallback_used_bytes)` dreistufig:
1. **Stufe 1 (Native PHP-Funktionen)**: Prüft `disk_total_space()` und `disk_free_space()` auf `$disk_check_path` (`/var/www/web1/cloud_storage`) sowie `realpath($disk_check_path)`.
2. **Stufe 2 (Linux `df -P -k` Fallback)**: Liefert `disk_total_space()` `0` oder `false` und ist `exec()` nicht in `disable_functions` gesperrt, wird `df -P -k /var/www/web1/cloud_storage` ausgewertet.
3. **Stufe 3 (Physische Summen-Rekonstruktion)**: Sind sowohl `disk_total_space()` als auch `exec()` deaktiviert, liegt aber `disk_free_space()` (`$disk_free > 0`) und das Ergebnis des physischen Verzeichnis-Scans (`$fallback_used_bytes = $total_storage_bytes_now`) vor, wird berechnet:
   * `Belegt ($disk_used)` = `$total_storage_bytes_now`
   * `Gesamt ($disk_total)` = `$disk_used + $disk_free`
   * Fortschrittsbalken-Farbe im Frontend: Blau (`< 70 %`), Orange (`70 % – 84,9 %`), Rot (`>= 85 %`).

---

## 3. 📈 Streaming- & Download-Traffic-Berechnung (Tab 2)

Um den realen monatlichen Bandbreitenbedarf (Egress-Traffic) für externe Storage-Anbieter zu bestimmen, wertet Abschnitt 4 in `storage_analytics_scan.php` die Tabelle `movies_access` der **letzten 6 Monate** aus:

1. **Datenbank-Abfrage**:
   * Selektiert alle Zugriffe aus `movies_access`, bei denen `buy_timestamp >= (Heute - 6 Monate)` oder `access_token_datetime >= (Heute - 6 Monate)` liegt.
   * Als Aktivitätsdatum (`activity_date`) wird `GREATEST(IFNULL(buy_timestamp, ...), IFNULL(access_token_datetime, ...))` verwendet und nach Kalendermonat (`YYYY-MM`) gruppiert.
2. **Zuordnung der Dateigröße**:
   * Da `api/movie_player.php` und `api/movie_downloader.php` immer die Desktop-MP4 (`{id}_{file_id}.mp4`) ausliefern, wird für jeden Zugriff exakt die physische Dateigröße der jeweiligen Desktop-MP4 aus `$movie_desktop_sizes[$fid]['actual']` herangezogen (bzw. lokal der Bitraten-Schätzwert).
   * Unterscheidung über die Spalte `buy_as`:
     * `buy_as === 'download'` -> zählt als **Download-Traffic**
     * alle anderen Werte (`'stream'` etc.) -> zählen als **Streaming-Traffic**
3. **1x Basis-Wert vs. 2x Realistischer Praxis-Wert**:
   * **1x Basis-Wert**: Entspricht dem einmaligen vollständigen Abruf der jeweiligen Videodatei pro Kauf/Freischaltung (`$avg_monthly_bytes_1x = $traffic_6m['total_bytes'] / 6.0`).
   * **2x Realistischer Praxis-Wert (`$avg_monthly_bytes_2x`)**: Da Kunden gekaufte Filme innerhalb ihres Zugriffszeitraums oft mehrfach starten oder im Player vor- und zurückspulen (HTTP-Range-Requests), wird für die Kostenkalkulation der Sicherheitsfaktor **2,0x** angesetzt.

---

## 4. ☁️ Cloud-Storage-Kostenrechner (Tab 3)

Abschnitt 5 in `storage_analytics_scan.php` berechnet anhand des aktuellen Speichervolumens (`$tb_now`), des bereinigten Speichervolumens ohne `.webm`/`.ogv` (`$tb_cleaned`) und des realistischen monatlichen Traffics (`$tb_traffic`) die voraussichtlichen Monatskosten bei 4 großen Anbietern, die legalen Adult-Content (BRAM-konform) erlauben:

| Anbieter | Preismodell im Kalkulator | Besonderheit |
| :--- | :--- | :--- |
| **Backblaze B2** | `6,00 $ / TB` Speicher; Traffic bis `3x` Speichervolumen kostenlos (danach `10,00 $ / TB`) | Preis-Leistungs-Sieger für S3-kompatiblen Object Storage |
| **OVHcloud Object Storage** | `7,50 € / TB` Speicher; ausgehender Traffic (`Egress`) `0,00 €` (inklusiv) | Europäische Rechenzentren (DE/FR), DSGVO-konform |
| **Wasabi Hot Cloud Storage** | `6,99 $ / TB` Speicher; ausgehender Traffic kostenlos (Fair-Use bis `1x` Speichervolumen) | 90 Tage Mindest-Speicherdauer für gelöschte Objekte |
| **Bunny.net (Storage + CDN)** | `10,00 $ / TB` Speicher + `5,00 $ / TB` Streaming-Traffic | Auf Adult-Streaming spezialisiert, natives SFTP + Edge-CDN |

---

## 5. 📌 Gemessene Live-Server-Eckdaten (Referenzstand: Oktober 2026)

Beim ersten Live-Scan auf dem Produktionsserver (`/var/www/web1/cloud_storage`) wurden folgende reale Kennzahlen ermittelt:
* **Server-Partition (`/var/www/web1/cloud_storage`)**: **4,26 TB belegt** von **4,93 TB** (**690,24 GB frei** | **86,3 % Auslastung**).
* **Videos gesamt (`cloud_storage/{merchant_id}/{movie_id}/`)**: **4,23 TB** (5.549 Filme in DB, 5.512 online, **78.938 physische Dateien**).
* **Fotoalben (`cloud_storage/photo_albums/`)**: **32,71 GB** (842 Alben, 825 online, 26.564 Fotos, **51.230 physische Dateien**).
* **Werbebanner (`cloud_storage/ads/`)**: **15,09 MB** (226 Banner in DB, **235 physische Dateien**).
* **Sofortiges Einsparpotenzial durch ungenutzte Formate**:
  * Nur `.webm` + `.ogv` (`m_*.webm` & `m_*.ogv`): **819,16 GB**
  * Inklusive ungenutzter Mobile-MP4 (`m_*.mp4`): **1,24 TB**
  * **Verbleibender Speicherbedarf nach Bereinigung (.webm & .ogv)**: **3,46 TB** (wodurch auf dem bestehenden Server sofort wieder über **1,5 TB** freier Speicherplatz zur Verfügung stehen).

---
name: video-management
description: Detaillierte Dokumentation über den Video-Lebenszyklus von Upload, Konvertierung, Freigabe, Speichernutzung bis Archivierung.
usage: 'Referenz für Arbeiten am Videoupload, FFmpeg-Konvertierungen, UI-Standards und der ACP-Videoprüfung.'
---

# 📹 Video-Verwaltung (Video Lifecycle)

Dieses Dokument beschreibt im Detail den gesamten Lebenslauf eines Videos (Filmes) in EroCloud: vom Upload durch den Creator über die Konvertierung und Admin-Prüfung bis hin zur Archivierung und physischen Löschung.

---

## 1. 📂 Speicherort, Multi-Storage-Vorbereitung und Speicher-Analyse
* **Hauptverzeichnis (`MOVIES_PATH`)**: `dirname(dirname(__FILE__))` in `config.inc.php` (eine Ebene über dem Projektordner `htdocs`, auf dem Live-Server z. B. `/var/www/web1`).
* **Standard-Unterverzeichnis (`MOVIES_DEFAULT_DIR`)**: `cloud_storage` (wird in `config.inc.php` definiert).
* **Dynamische Datenbank-Spalte (`storage_location`)**:
  * In den Tabellen `movies`, `movies_online`, `movies_deleted` sowie `photo_albums` besitzt jeder Datensatz die Spalte `storage_location`.
  * Beim Anlegen eines neuen Films (`mcp/includes/movie_upload.php`) wird dort der Wert aus `MOVIES_DEFAULT_DIR` (`'cloud_storage'`) gespeichert.
  * Beim Streaming (`api/movie_player.php`), Download (`api/movie_downloader.php`), Konvertieren (`cronjobs/convert_movie.php`) und Löschen (`cronjobs/delete_movie.php`) wird der Dateipfad immer dynamisch über `$movie_ary->storage_location` aufgelöst:
    `MOVIES_PATH . '/' . $movie_ary->storage_location . '/' . $movie_ary->merchant_id . '/' . $movie_ary->id . '/' . $movie_ary->filename`
  * **Architektur-Vorteil für externe Fileserver**: Durch diese Struktur können externe Storage-Systeme (z. B. per `rclone mount`, `s3fs` oder `SSHFS`) als zusätzliches Verzeichnis (z. B. `cloud_storage_2`) eingebunden und über `MOVIES_DEFAULT_DIR` für neue Uploads aktiviert werden, ohne dass bestehende Filme unter `cloud_storage` ihre Gültigkeit verlieren.

### Speicherplatz- & Traffic-Analyse im ACP (`/Speicher-Analyse` & `/Filme-online`)
> **Detaillierte technische Referenz:** Alle Details zu den beteiligten Dateien, der 24h-Cache-Logik, dem dreistufigen Partitions-Fallback (`sa_get_disk_metrics`) und den gemessenen Live-Server-Kennzahlen sind in `@storage-and-traffic-analytics.md` (`.agent/knowledge/architecture/storage-and-traffic-analytics.md`) dokumentiert.

* **Schnellübersicht unter `/Filme-online` (`acp/includes/movies_online.php`)**:
  * Zeigt den belegten Speicherplatz aller veröffentlichten Videos über einen **24-Stunden-Cache** an (mit Echtzeit-Neuberechnung über `[Größe neu berechnen]` / `?recalc_size=1`).
* **Detaillierte Analyse-Seite „Speicher & Traffic“ unter `/Speicher-Analyse` (`acp/includes/storage_analytics.php` & `acp/includes/ajax/storage_analytics_scan.php`)**:
  * **Tab 1 (Speicherplatz & Dateiformate)**: Analysiert den gesamten Cloud-Speicherbedarf aufgeschlüsselt nach Systembereichen (*Videos: 4,23 TB*, *Fotoalben: 32,71 GB*, *Werbebanner: 15,09 MB*, *Händler-/Profilbilder*), nach Freigabe-Status (*Online*, *Entwürfe*, *Abgelehnt*, *Soft-Gelöscht*) sowie exakt nach den erzeugten **Video-Dateitypen** (`{id}_{file_id}.mp4`, `m_*.mp4`, `m_*.webm`, `m_*.ogv`, `thumb_*.jpg`). Weist zudem das sofortige Einsparpotenzial durch veraltete Videoformate (**819,16 GB** für `.webm`/`.ogv` bzw. **1,24 TB** inkl. `m_*.mp4`) und die aktuelle Server-Partitionsauslastung aus.
  * **Tab 2 (Streaming- & Download-Traffic der letzten 6 Monate)**: Wertet alle Kunden-Zugriffe aus `movies_access` (`buy_timestamp` und `access_token_datetime`) der letzten 6 Monate monatsweise aus und berechnet das durchschnittliche monatliche Streaming- und Download-Datenvolumen (sowohl als 1x Basiswert als auch mit realistischem 2x Faktor für Mehrfach-Ansichten und Spulen).
  * **Tab 3 (Konvertierungs-Check & Cloud-Kostenrechner)**: Dokumentiert die technischen Befunde der FFmpeg-Pipeline und kalkuliert live die voraussichtlichen monatlichen Kosten bei externen, Adult-freundlichen Cloud-Storage-Anbietern (*Backblaze B2*, *OVHcloud Object Storage*, *Wasabi*, *Bunny.net*).

---

## 2. 📤 Video-Upload & Bearbeitung (MCP)
Der Creator (Händler/Merchant) lädt ein Video über das **Merchant Control Panel (MCP)** hoch (`mcp/includes/movie_upload.php`) oder bearbeitet ein bestehendes Video (`mcp/includes/movie.php`). Beide Seiten basieren auf einem einheitlichen **Bootstrap 5 2-Spalten Desktop Grid** (linksbündig mit `max-width: 1600px;`).

### UI- & Layout-Standard (Upload & Bearbeitung)
* **Dynamischer Fortschritts-Wizard**:
  * 4-Schritte-Navigation (*Schritt 1: Filminfos angeben*, *Schritt 2: Film hochladen*, *Schritt 3: Film konvertiert*, *Schritt 4: Veröffentlichen*).
  * **Grünes Status-Design (`bg-success text-white shadow-sm`)**: Sowohl der aktuelle Schritt als auch alle bereits beendeten Schritte leuchten in grün und zeigen im runden Badge ein Häkchen-Icon (`<i class="bi bi-check-lg"></i>`). Inaktive Schritte bleiben grau. Die Badges werden mittels `d-inline-flex align-items-center justify-content-center p-0 rounded-circle` mit exakter Abmessung (`24px x 24px`) auf allen Geräten exakt kreisrund gerendert.
* **Qualitäts- & Render-Hinweise**:
  * Linksbündige Modals (*"Hinweise zur Qualität deiner Filme"* & *"So renderst du deine Filme richtig"*), aufrufbar über Bootstrap 5 Modals (`#modalMovieTips` & `#modalMovieRenderingTips`).
* **Symmetrisches 3-Zeilen Desktop-Grid**:
  * **Zeile 1**: Links *Angaben zum Film* (Titel, Beschreibung, Veröffentlichungsdatum) | Rechts *Film kategorisieren* (Hauptkategorie & Accordion).
  * **Zeile 2**: Links *Preise & Download* (Stream-Preis in Coins, Trailer-Option, Download-Optionen) | Rechts *Darsteller & Sichtbarkeit* (Darstellerzuweisung & Partnerwebsite-Dropdown).
  * **Zeile 3**: Links *Suchmaschinenoptimierung (SEO)* (Meta Description, Meta Title, SEO-URL).
* **Partnerwebsite-Zuordnung (Legacy Syndication & Sunset)**:
  * **Hintergrund**: Das pauschale Verteilen von Content auf alle Partnerseiten (`visible_for_website = 'public'`) ist historisch gewachsen (Webmaster-/Affiliate-Syndication). Da diese alten Webmaster-Funktionen schrittweise abgelöst und bereinigt werden sollen, ist die Option *"alle Partnerwebsites"* im MCP standardmäßig für Creator deaktiviert.
  * **Temporäre Whitelist (Übergangsphase)**: Für bestimmte aktive Bestandspartner, die diese Funktionalität während der Sunset-Phase weiterhin zwingend benötigen (aktuell Partner-ID `CCRVWMVD67` / Merchant-ID `10061`), wird eine temporäre Whitelist in `mcp/includes/movie_upload.php` und `mcp/includes/movie.php` gepflegt.
  * **Endgültige Bereinigung**: Sobald alle verbleibenden Webmaster-/Syndication-Prozesse eingestellt werden, kann diese Whitelist mitsamt der `public`-Option im Creator-Portal vollständig entfernt werden. Filme werden dann ausschließlich expliziten Partnerdomains zugeordnet.

### Workflow bei der Neuerstellung:
* **Schritt 1 (Metadaten & Kategorisierung)**: Eingabe aller Filminfos, Festlegen von `category_master` (*Porno* / *Fetisch*) und `category_slave` (Kommagetrennte Kategorien-IDs), Darsteller-Zuweisung, Preise und SEO.
* **Schritt 2 (Datei-Upload)**:
  * **Validierung**: Erlaubte Formate sind `avi`, `flv`, `m4v`, `mkv`, `mov`, `mp4`, `mpg`, `wmv`. Maximale Größe beträgt 2,0 bis 4,0 GB.
  * **Analyse (getID3)**: Die PHP-Bibliothek `getID3` analysiert die temporäre Datei bezüglich Abspieldauer (`playtime_seconds`) und Auflösung (mindestens 640x480).
  * **Berechnung des Preises**: Der Gesamtpreis des Videos (`amount_own`) wird auf Basis der Dauer in Sekunden und des Sekundenpreises berechnet (`round(playtime_seconds * amount_second)`).
  * **Speicherung**: Die Datei wird als `[movie_id]_[file_id].[extension]` in den Händler-Ordner verschoben.
  * **Datenbank-Status**: Setzt `convert_status = '0'` (bereit für Konvertierung).
* **Schritt 3 (Konvertierungsbestätigung)**: Bestätigung für den Creator, dass das Video in Kürze konvertiert wird.

---

## 3. 🏷️ Kategorien-System & Transparenz-Logik

### 1. Accordion & Gruppierung (MCP)
Die Kategorien-Auswahl wird in einem **Bootstrap 5 Accordion** (`#categoryAccordion`) in 4 übersichtlichen Gruppen dargestellt:
1. *Anzahl der Personen & sexuelle Orientierung* (`number_of_people`)
2. *Körper und Aussehen* (`look_and_body`)
3. *Fetisch* (`fetish`)
4. *Sonstige* (`porn`)

Jedes Accordion-Element nutzt ein kompaktes **2-Spalten-Grid** (`row-cols-md-2`) und zeigt aktiven Badges (`X ausgewählt`) an.

### 2. Gelbe Systemerkennungs-Markierung (Transparenz für Creators)
* **Logik**: Das System analysiert den Titel und die Beschreibung des Films per Keyword-Suche (`search_text`) sowie das verknüpfte Darstellerprofil (`actor_categories`).
* **Visuelle Unterscheidung**:
  * Vom System automatisch vorausgewählte Kategorien werden mit einem **gelben Hintergrund** (`style="background-color: #fff6d0; border: 1px solid #ffe8a1;"`) hervorgehoben.
  * Vom Creator manuell gewählte bzw. bereits gespeicherte Kategorien erscheinen mit normalem weißem Hintergrund (`class="bg-white border-primary shadow-sm"`).
  * Ein Hinweistext-Banner mit exakt derselben Gelb-Farbe (`#fff6d0`) informiert den Creator transparent über diese Automatik.

### 3. Entkopplung bei der Admin-Prüfung (ACP `acp/includes/movie_checking.php`)
* Bei der Admin-Prüfung im ACP ist die automatische Keyword-Erkennung **vollständig deaktiviert**.
* Der Admin sieht exakt 1:1 die vom Creator gespeicherten Kategorien (`category_slave`). Dadurch wird verhindert, dass Kategorien, die vom Creator bewusst abgewählt wurden, bei der Erst- oder Folgeprüfung ungewollt wieder aktiviert werden.

---

## 4. ⚙️ Asynchrone Video-Konvertierung (`cronjobs/convert_movie.php`) & High-Performance-Auslieferung
Ein Hintergrund-Cronjob kümmert sich um die automatische Aufbereitung der hochgeladenen Rohvideos:

* **Pfad**: `cronjobs/convert_movie.php` (maximale parallele Instanzen gesteuert über `FFMPEG_SIMULTANEOUS_CONV = 2` in `config.inc.php`).
* **Timeout-Schutz**: Läuft eine Konvertierung (`convert_status = '1'`) länger als **4 Stunden** ohne Abschluss (`convert_endtime == '0000-00-00 00:00:00'`), bricht der Cronjob diese automatisch ab und setzt `convert_status = '3'` (Fehler).
* **Ablauf & erzeugte Dateien im Ordner `MOVIES_PATH/{storage_location}/{merchant_id}/{movie_id}/`**:
  * Liest den ältesten Film mit `convert_status = '0'` und setzt ihn auf `convert_status = '1'`.
  * **1. Einzige Streaming-MP4 (`{id}_{file_id}.mp4`) mit auflösungsabhängigem Constrained CRF (`-crf 23`)**:
    * **Erhalt von Original-Auflösung (inkl. 4K & 9:16 Hochkant):** Über `-vf "scale=trunc(iw/2)*2:trunc(ih/2)*2"` bleibt die Original-Auflösung (4K, Full-HD, HD, SD sowie Smartphone-Hochkant-Reels) erhalten.
    * **Dynamische Bitraten-Deckelung (`max($pre_width, $pre_height)`):**
      * **2K / 4K Ultra-HD:** `-crf 23 -maxrate 6500k -bufsize 13000k -preset medium`
      * **Full-HD 1080p *(inkl. Fallback bei fehlenden Metadaten)*:** `-crf 23 -maxrate 3200k -bufsize 6400k -preset slow`
      * **HD 720p:** `-crf 23 -maxrate 2000k -bufsize 4000k -preset slow`
      * **SD (`< 720p`):** `-crf 23 -maxrate 1200k -bufsize 2400k -preset slow`
    * **Maximale Fehlertoleranz:** `-fflags +genpts+igndts` (repariert defekte Zeitstempel), `-pix_fmt yuv420p` (konvertiert 10-Bit-iPhone-Videos in universelles 8-Bit), `-acodec aac -strict -2 -b:a 128k -ac 2 -ar 44100` (normiert jede Tonspur und ignoriert fehlende Tonspuren bei Stummvideos), `-max_muxing_queue_size 4096` und `-movflags +faststart`.
    * **Post-Conversion-Re-Analyze:** Nach Abschluss liest `getID3` Auflösung und Spieldauer frisch aus der fertigen `.mp4`-Datei aus und berechnet bei Bedarf `amount_own` nach, falls die Rohdatei beim Upload keine Header-Metadaten hatte.
  * **2. Vorschaubilder (`thumb_{id}_{file_id}_1..10.jpg`)**:
    * Sobald die fertige MP4-Datei (`{id}_{file_id}.mp4`) existiert, wird `convert_status = '2'` gesetzt und FFmpeg extrahiert 10 gleichmäßig über die Laufzeit verteilte Screenshots mit max. `1024px` Breite (`scale=min(1024\,iw):-2`).
  * **3. Datenbereinigung**:
    * Sofern die hochgeladene Originaldatei einen anderen Dateinamen/Endung als `{id}_{file_id}.mp4` hatte, wird sie per `unlink($org_file)` gelöscht.
  * *(Historischer Hinweis: Bis Oktober 2026 wurden zusätzlich `m_{id}_{file_id}.mp4`, `m_{id}_{file_id}.webm` und `m_{id}_{file_id}.ogv` konvertiert. Diese Konvertierungsschritte wurden aus `cronjobs/convert_movie.php` entfernt, da `api/movie_player.php` ausschließlich die Haupt-MP4 streamt und alle modernen Mobilgeräte diese direkt per HTTP-Range-Streaming abspielen).*

### 📺 High-Performance-Streaming & TV-Übergabe (Apple AirPlay & Google Cast / Chromecast)
* **`includes/klassen/VideoStream.inc.php`**:
  * **Exakte Byte-Zählung (`strlen($data)`):** Verhindert abgeschnittene Datenpakete und Frame-Drops bei Short-Reads auf Storage-Mounts.
  * **512 KB Aligned Chunk-Buffer (`524288` Bytes):** Deaktiviert PHPs internen 8-KB-Puffer (`stream_set_read_buffer(..., 0)`), schaltet jegliche Gzip-/Output-Bufferung ab und liefert sofortige Reaktionszeiten beim Spulen.
  * **RFC 7233 Range-Konformität & CORS-Support für Smart-TVs:** Sendet `Accept-Ranges: bytes`, `ETag`, `Last-Modified`, unterstützt `If-Range`, `304 Not Modified`, `HEAD`- und `OPTIONS`-Preflight-Requests sowie `Access-Control-Allow-Origin: *` und `Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges, ETag` (zwingend für Chromecast / Android TV).
* **`api/get_movie_url.php` & `api/movie_player.php`**:
  * **Persistentes 6-Stunden-Token:** `get_movie_url.php` verwendet ein bestehendes, noch gültiges `access_token` wieder (statt es bei jedem Seiten-Reload zu überschreiben) und verlängert `access_token_datetime` auf **6 Stunden (`+6 hours`)**. Dadurch bricht ein bereits auf dem Fernseher laufender AirPlay-/Chromecast-Stream nicht ab, wenn der Kunde das Video auf dem Handy neu lädt.
  * **Session-Stream-Cache & gedrosselte Token-Verlängerung:** `api/movie_player.php`, `acp/includes/movie_player.php` und `mcp/includes/movie_player.php` cachen den aufgelösten Dateipfad für 30 Minuten in `$_SESSION['erocloud_stream_cache']`, geben das PHP-Session-Lock (`session_write_close()`) sowie die DB-Verbindung (`p4c_close(DB_HOST)`) **vor** dem Streaming-Start frei und aktualisieren `access_token_datetime` höchstens 1× pro Stunde statt bei jedem einzelnen Range-Request.

---

## 5. 🖼️ Vorschaubilder und Custom Covers (MCP)
Nach der Konvertierung kann der Händler das Video bearbeiten (`mcp/includes/movie.php`):
* **Thumbnail-Auswahl**: Der Händler wählt aus den extrahierten Thumbnails (1-10) jeweils eines für die FSK16- und FSK18-Vorschau aus (gespeichert in `preview_image_fsk16`/`preview_image_fsk18`). Alle Thumbnails werden mit festem Seitenverhältnis (`16/9; object-fit: cover`) dargestellt.
* **Custom Poster Upload**: Lädt der Händler eigene Vorschaubilder hoch (`mcp/includes/uploader/upload_movie_poster.php`), werden diese als `thumb_[movie_id]_[file_id]_11.[ext]` (FSK16) bzw. `_12.[ext]` (FSK18) gespeichert. In der UI sind sie transparent als *eigenes FSK16* und *eigenes FSK18* gelabelt.
* **Freigabe**: Das Speichern setzt `released = '1'` (zur Prüfung freigegeben) und `movie_checked = '0000-00-00 00:00:00'`, wodurch das Video dem Administrator zur Prüfung vorgelegt wird.

---

## 6. 🔍 Prüfung, BRAM-Compliance und Freischaltung (ACP)
> **Detaillierte KI- & BRAM-Referenz:** Alle Details zur lokalen Whisper-Untertitel-Pipeline, BRAM-Regel-Engine und dem `/KI-Analyse`-Cockpit sind in `@ai-compliance-and-subtitle-pipeline.md` dokumentiert.

Ein Administrator prüft das Video im **Admin Control Panel (ACP)**:

* **Listenansichten (`/Filme-pruefen` & `/Filme-online`)**:
  * `acp/includes/movies_checking.php` (`acp/includes/ajax/movies_checking.php`) filtert nach `movie_checked = '0000-00-00 00:00:00' AND released = '1' AND convert_status > '1'`.
  * Beide Tabellen (`movies_checking.php` und `movies_online.php`) zeigen in einer eigenen Spalte **„BRAM-Score“** das farbige Compliance-Badge (`aic_render_bram_score_badge()`):
    * **Rot (`violation`)**: Kritischer Visa/Mastercard BRAM- oder Rechts-Verstoß (`#dc3545`).
    * **Orange (`warning`)**: Prüfhinweis / Spezialfetisch ohne Kreditkarte (`#e0a800`).
    * **Grün (`ok` + Audio geprüft)**: Text & gesprochenes Video-Audio unbedenklich (`#28a745`).
    * **Info-Blau/Türkis (`ok` + Audio noch offen)**: `5 / 100 (Nur Text)` – Vorab-Textcheck unauffällig, Audio-Transkription durch den Whisper-Cronjob steht noch aus (`#17a2b8`).
* **Prüfungs- & Bearbeitungsseite (`/Film-pruefen/{id}` -> `acp/includes/movie_checking.php` & `/Film-bearbeiten/{id}` -> `acp/includes/movie_edit.php`)**:
  * **Top-Compliance-Banner & KI-Reiter:** Direkt über dem Bearbeitungsformular erscheint das farbige BRAM-Compliance-Banner (`aic_get_movie_bram_and_ai_tabs_data()`) inkl. Fundstellen-Pills. Zudem stehen die beiden Reiter **„BRAM & KI-Analyse“ (`#tab-bram`)** und **„Audio & Transkript (.vtt)“ (`#tab-transcript`)** bereit, über die das gesprochene Audio jederzeit live per `whisper.cpp` transkribiert und auf BRAM-Verstöße geprüft werden kann. Beim Speichern von Änderungen wird der BRAM-Score automatisch neu berechnet (`$force_recalc = true`).
  * Der Admin kann alle Metadaten anpassen, die FSK16/18 Covers ändern und die Vorschau-Länge definieren.
  * **Freischaltung (Status active)**: Setzt `status = 'active'`, `movie_checked = current_datetime` und fügt das Video in die Tabelle `movies_online` ein. Erst hierdurch ist das Video auf den Frontend-Webseiten sichtbar.
  * **Ablehnung (Rejection)**: Der Admin wählt einen Ablehnungsgrund aus.
    * Der Status `released` wird auf `2` (abgelehnt) gesetzt.
    * Der Grund wird in der Tabelle `rejection_reason_movie_history` geloggt.
* **Fehlersuche & Archivierung (`/gesperrte-Filme` & `/Filme-in-Planung`)**:
  * Abgelehnte, blockierte und gelöschte Filme werden gesammelt aufgelistet (mit Badges wie `Löschung`, `Abgelehnt`, `Gesperrt`).
  * Die Seite `/Filme-in-Planung` listet ungesendete Entwürfe (`released = 0`) inklusive Konvertierungsstatus und belegtem Speicherplatz auf.

---

## 7. 📋 Händler-Filmliste (MCP)
Die Übersicht aller hochgeladenen Filme des Händlers im Merchant Control Panel (MCP):
* **Neues MCP (`mcp/mcp_neu/` -> Route `#/movies`)**:
  * **Controller & View**: `mcp/mcp_neu/app/src/Modules/Movies/MoviesController.php` & `views/list.php`.
  * **Wichtige Produktregel (Keine Umsätze in der EroCloud)**: Da die Partner ihre echten Umsätze und Abrechnungen direkt in ihrer EroCMS-Seite oder im Pay4Coins-Account einsehen, werden im neuen EroCloud MCP **keine Umsatzzahlen, Provisionen oder Verkaufszahlen** (Käufe Streaming/Download) angezeigt.
  * **KPI-Kacheln**: *Filme gesamt*, *Filme Online*, *In Prüfung / Konvertierung*, *Abgelehnt / Korrektur nötig*.
  * **Tabellenspalten**: *Status*, *ID*, *Vorschaubilder (FSK16 / FSK18)*, *Filmtitel (inkl. Laufzeit & Auflösung)*, *Darsteller*, *Online ab (`d.m.Y H:i`)* und *Aktion (Bearbeiten)*.
* **Legacy-MCP (`mcp/includes/movies.php` / `mcp/includes/ajax/movies.php` über Route `/Movies`)**:
  * Listet im Altsystem alle hochgeladenen Filme tabellarisch auf (jQuery DataTables).

---

## 8. 🗑️ Löschen und Archivieren
Um alte, nicht mehr genutzte Inhalte zu bereinigen, greifen nach Freigabe des neuen Cronjobs folgende Schutzfristen (simuliert unter `/Content-Bereinigung`):
* **Regel 1 (Nie gekauft)**: Sofortige Löschung nach Soft-Delete.
* **Regel 2 (Inaktiv > 2 Jahre)**: Löschung nach 30 Tagen Karenzzeit ab Soft-Delete.
* **Regel 3 (Aktiv < 2 Jahre)**: Löschung nach 365 Tagen ab Soft-Delete (Kundenrechte-Schutz).
* **Regel 4 (Alt & Abgelehnt > 180 Tage)**: Sofortige Löschung von ungenutzten abgelehnten Film-Entwürfen, die seit 6 Monaten nicht mehr bearbeitet wurden.
* **Regel 5 (Inaktive Entwürfe > 180 Tage)**: Sofortige Löschung von unbestätigten Creator-Entwürfen, die seit 6 Monaten nicht mehr bearbeitet wurden.

Bei der physischen Bereinigung durch den Cronjob (`cronjobs/delete_movie.php`):
* Wird die Gesamtgröße des Video-Ordners ermittelt.
* Wird der physische Ordner gelöscht.
* Wird ein Eintrag in `movies_deleted` mit der freigegebenen Byte-Größe zur Protokollierung geschrieben.
* Werden alle Bezüge in `movies`, `movies_online` und `movies_access` bereinigt.

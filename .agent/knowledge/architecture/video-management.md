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

## 4. ⚙️ Asynchrone Video-Konvertierung (`cronjobs/convert_movie.php`) & Auslieferung
Ein Hintergrund-Cronjob kümmert sich um die automatische Aufbereitung der hochgeladenen Rohvideos:

* **Pfad**: `cronjobs/convert_movie.php` (maximale parallele Instanzen gesteuert über `FFMPEG_SIMULTANEOUS_CONV = 2` in `config.inc.php`).
* **Timeout-Schutz**: Läuft eine Konvertierung (`convert_status = '1'`) länger als **4 Stunden** ohne Abschluss (`convert_endtime == '0000-00-00 00:00:00'`), bricht der Cronjob diese automatisch ab und setzt `convert_status = '3'` (Fehler).
* **Ablauf & erzeugte Dateien im Ordner `MOVIES_PATH/{storage_location}/{merchant_id}/{movie_id}/`**:
  * Liest den ältesten Film mit `convert_status = '0'` und setzt ihn auf `convert_status = '1'`.
  * **1. Desktop MP4 (`{id}_{file_id}.mp4`)**:
    * Befehl: `ffmpeg -y -i {org_file} -b:v 8000k -vf "scale={width}:trunc(ow/a/2)*2" -vcodec libx264 -preset veryslow -g 30 neu_{id}_{file_id}.mp4`
    * Optimierung: Anschließend wird `qt-faststart` ausgeführt (`neu_qt_{id}_{file_id}.mp4`), als finale `{id}_{file_id}.mp4` kopiert und der Dateiname in `movies.filename` gespeichert.
    * Speicherbedarf: ca. **1,01 MB pro Sekunde** (~60 MB pro Minute).
  * **2. Mobile MP4 (`m_{id}_{file_id}.mp4`)**:
    * Befehl: `ffmpeg -y -i {org_file} -b:v 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libx264 -b:a 196k -g 30 m_{id}_{file_id}.mp4`
    * Optimierung: Auch hier wird anschließend `qt-faststart` ausgeführt.
    * Speicherbedarf: ca. **0,15 MB pro Sekunde** (~8,9 MB pro Minute).
  * **3. Mobile WebM (`m_{id}_{file_id}.webm`)**:
    * Befehl: `ffmpeg -y -i {org_file} -b:v 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libvpx -acodec libvorbis -f webm -g 30 m_{id}_{file_id}.webm`
    * Speicherbedarf: ca. **0,14 MB pro Sekunde** (~8,4 MB pro Minute).
  * **4. Mobile OGV (`m_{id}_{file_id}.ogv`)**:
    * Befehl: `ffmpeg -y -i {org_file} -b:v 1000k -vf "scale=640:trunc(ow/a/2)*2" -vcodec libtheora -acodec libvorbis -g 30 m_{id}_{file_id}.ogv`
    * Speicherbedarf: ca. **0,14 MB pro Sekunde** (~8,4 MB pro Minute).
  * **5. Vorschaubilder (`thumb_{id}_{file_id}_1..10.jpg`)**:
    * Sobald alle 3 Mobil-Dateien (`mp4`, `webm`, `ogv`) existieren, wird `convert_status = '2'` gesetzt und FFmpeg extrahiert 10 gleichmäßig über die Laufzeit verteilte Screenshots mit `1024px` Breite (`scale="1024:-1"`).
  * **6. Datenbereinigung**:
    * Sofern die hochgeladene Originaldatei einen anderen Dateinamen/Endung als `{id}_{file_id}.mp4` hatte, wird sie per `unlink($org_file)` gelöscht.

### 🔍 Technische Befunde & Optimierungspotenzial bei Konvertierung & Auslieferung
Bei der Analyse des Zusammenspiels zwischen `cronjobs/convert_movie.php`, `api/get_movie_url.php` und `api/movie_player.php` wurden folgende wichtige Punkte festgestellt:

1. **Veraltete Formate `m_*.webm` und `m_*.ogv` sind ungenutzter Ballast**:
   * Obwohl `api/get_movie_url.php` die URLs `movie_url_mobil_webm` (`...&mobile=webm`) und `movie_url_mobil_ogg` (`...&mobile=ogg`) im XML/JSON ausgibt, wertet `api/movie_player.php` den Parameter `$_GET['mobile']` **überhaupt nicht aus**.
   * `api/movie_player.php` streamt über die Klasse `VideoStream` (`includes/klassen/VideoStream.inc.php`) **immer ausschließlich die Desktop-MP4** (`$movie_ary->filename`).
   * Da moderne Desktop- und Mobilbrowser (iOS, Android, Chrome, Safari, Firefox, Edge) zu 100 % nativ H.264/MP4 unterstützen, werden `.webm` und `.ogv` weder vom Player ausgeliefert noch von Browsern benötigt. Sie belegen bei ~5.680 Filmen rund **850–950 GB Speicherplatz** und verdoppeln unnötig die FFmpeg-Konvertierungszeit.
2. **Mobile MP4 (`m_*.mp4`) wird aktuell ebenfalls vom Player ignoriert**:
   * Auch bei Aufruf von `...&mobile=mp4` liefert `api/movie_player.php` derzeit die große Desktop-MP4 aus, da keine Weiche für `$_GET['mobile'] === 'mp4'` implementiert ist.
   * Hier kann zukünftig entschieden werden, ob `m_*.mp4` ebenfalls komplett eingespart wird (weitere ~470 GB Ersparnis) oder ob `api/movie_player.php` für mobile Endgeräte auf `m_ . $movie_ary->filename` zugreifen soll.
3. **Encoding-Optimierung für zukünftige Desktop-MP4s**:
   * Statt einer festen Bitrate von `-b:v 8000k -preset veryslow` und separatem `qt-faststart`-Kopierlauf empfiehlt sich zukünftig ein qualitätsbasiertes CRF-Encoding (`-crf 22 -maxrate 5000k -bufsize 10000k -preset slow -pix_fmt yuv420p -c:a aac -b:a 128k -movflags +faststart`). Dies spart ~35–40 % Dateigröße und Streaming-Traffic bei gleichbleibender visueller Qualität.

---

## 5. 🖼️ Vorschaubilder und Custom Covers (MCP)
Nach der Konvertierung kann der Händler das Video bearbeiten (`mcp/includes/movie.php`):
* **Thumbnail-Auswahl**: Der Händler wählt aus den extrahierten Thumbnails (1-10) jeweils eines für die FSK16- und FSK18-Vorschau aus (gespeichert in `preview_image_fsk16`/`preview_image_fsk18`). Alle Thumbnails werden mit festem Seitenverhältnis (`16/9; object-fit: cover`) dargestellt.
* **Custom Poster Upload**: Lädt der Händler eigene Vorschaubilder hoch (`mcp/includes/uploader/upload_movie_poster.php`), werden diese als `thumb_[movie_id]_[file_id]_11.[ext]` (FSK16) bzw. `_12.[ext]` (FSK18) gespeichert. In der UI sind sie transparent als *eigenes FSK16* und *eigenes FSK18* gelabelt.
* **Freigabe**: Das Speichern setzt `released = '1'` (zur Prüfung freigegeben) und `movie_checked = '0000-00-00 00:00:00'`, wodurch das Video dem Administrator zur Prüfung vorgelegt wird.

---

## 6. 🔍 Prüfung und Freischaltung (ACP)
Ein Administrator prüft das Video im **Admin Control Panel (ACP)**:

* **Listenansicht**: `acp/includes/movies_checking.php` (AJAX-Quelle: `acp/includes/ajax/movies_checking.php`).
  * Filtert nach: `movie_checked = '0000-00-00 00:00:00' AND released = '1' AND convert_status > '1'`.
* **Prüfungsseite**: `acp/includes/movie_checking.php`.
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
* **Pfad**: `mcp/includes/movies.php` (AJAX-Quelle: `mcp/includes/ajax/movies.php` über Route `/Movies`).
* **Funktion**: Listet alle hochgeladenen Filme tabellarisch auf (unter Verwendung von jQuery DataTables).
* **Anzeigefelder**:
  * **Status**: Zeigt über Icons an, ob der Film z. B. konvertiert wird, online ist oder abgelehnt wurde.
  * **ID, Vorschaubilder, Filmtitel, Darsteller**.
  * **Online ab (`online_at`)**: Zeigt an, wann der Film veröffentlicht werden soll (deutsches Format `d.m.Y H:i` mit zeitlich korrekter Sortierung über `title-string`).
  * **Käufe (Streaming/Download) & Provision**.

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

---
name: video-management
description: Detaillierte Dokumentation über den Video-Lebenszyklus von Upload, Konvertierung, Freigabe, Speichernutzung bis Archivierung.
usage: 'Referenz für Arbeiten am Videoupload, FFmpeg-Konvertierungen, UI-Standards und der ACP-Videoprüfung.'
---

# 📹 Video-Verwaltung (Video Lifecycle)

Dieses Dokument beschreibt im Detail den gesamten Lebenslauf eines Videos (Filmes) in EroCloud: vom Upload durch den Creator über die Konvertierung und Admin-Prüfung bis hin zur Archivierung und physischen Löschung.

---

## 1. 📂 Speicherort und Verzeichnisstruktur
* **Hauptverzeichnis (`MOVIES_PATH`)**: `c:\xampp\htdocs` (das Web-Root-Verzeichnis).
* **Unterverzeichnis (`MOVIES_DEFAULT_DIR`)**: `cloud_storage` (wird in `config.inc.php` definiert).
* **Physischer Speicherpfad**: 
  `c:\xampp\htdocs\cloud_storage\[merchant_id]\[movie_id]/`
* **Hintergrund**: Die Ablage im Webroot (`cloud_storage/`) ermöglicht eine direkte Auslieferung und das Streaming der optimierten Mediendateien über den Webserver (Apache/Nginx).

### Speicherplatz-Auslesung bei Online-Filmen (`/Filme-online`)
* Um die Serverauslastung zu minimieren, wird der gesamte belegte Speicherplatz aller veröffentlichten Videos über einen **24-Stunden-Cache** zwischengespeichert.
* Administratoren können eine Echtzeit-Neuberechnung über die Schaltfläche **`[Größe neu berechnen]`** erzwingen (Parameter `?recalc_size=1`).

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

## 4. ⚙️ Asynchrone Video-Konvertierung (Cronjob)
Ein Hintergrund-Cronjob kümmert sich um die automatische Aufbereitung der Videos:

* **Pfad**: `cronjobs/convert_movie.php` (läuft alle paar Minuten).
* **Ablauf**:
  * Liest alle Filme mit `convert_status = '0'`.
  * Setzt den Status temporär auf `1` (Konvertierung läuft) und speichert den Startzeitpunkt (`convert_starttime`).
  * **FFmpeg-Konvertierungen**:
    1. **Desktop MP4**: Konvertiert das Video in H.264 (`-vcodec libx264 -preset veryslow`) in der Originalauflösung. 
    2. **Mobile MP4** (Dateiname beginnt mit `m_`): Konvertiert mit einer festen Breite von 640 Pixeln und einer niedrigeren Bitrate für mobile Geräte.
    3. **Mobile WebM** (Dateiname beginnt mit `m_` und `.webm` Endung): Konvertiert unter Verwendung von `libvpx` (Video) und `libvorbis` (Audio).
    4. **Mobile OGV** (Dateiname beginnt mit `m_` und `.ogv` Endung): Konvertiert unter Verwendung von `libtheora` (Video) und `libvorbis` (Audio).
    5. **Streaming-Optimierung (qt-faststart)**: Nach der Generierung von Desktop- und Mobil-MP4s wird das Tool `qt-faststart` aufgerufen. Dies verschiebt die Metadaten (moov atom) an den Anfang der Datei, wodurch das Video gestreamt werden kann, noch während es herunterlädt.
  * **Vorschaubilder (Thumbnails)**: Nach erfolgreicher Konvertierung extrahiert FFmpeg 10 Screenshots aus dem Video, die gleichmäßig über die Gesamtlaufzeit verteilt sind (`thumb_[movie_id]_[file_id]_%d.jpg`). Nicht existierende Slots werden in der UI sauber ausgeblendet.
  * **Datenbereinigung**: Die ursprüngliche, hochgeladene Videodatei wird gelöscht, um Speicherplatz zu sparen.
  * **Datenbank-Status**: Setzt `convert_status = '2'` (Konvertierung erfolgreich beendet). Bei Fehlern wird `convert_status = '3'` gesetzt.

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

---
name: video-management
description: Detaillierte Dokumentation über den Video-Lebenszyklus von Upload, Konvertierung, Freigabe, Speichernutzung bis Archivierung.
usage: 'Referenz für Arbeiten am Videoupload, FFmpeg-Konvertierungen und der ACP-Videoprüfung.'
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

## 2. 📤 Video-Upload (MCP)
Der Creator (Händler/Merchant) lädt ein Video über das **Merchant Control Panel (MCP)** hoch:

* **Pfad**: `mcp/includes/movie_upload.php` (Schritt 1 bis 3) und `mcp/includes/uploader/upload_movie.php`.
* **Ablauf**:
  * **Schritt 1 (Metadaten)**: Eingabe von Titel, Beschreibung, Zuweisung des Darstellers (`actor_id`), Veröffentlichungsdatum (`online_at`), Preis pro Sekunde in Coins (`amount_second`), Download-Preis (`amount_download`) sowie SEO-Angaben. Der Entwurf wird in der Tabelle `movies` gespeichert.
  * **Schritt 2 (Datei-Upload)**:
    * **Validierung**: Erlaubte Formate sind `avi`, `flv`, `m4v`, `mkv`, `mov`, `mp4`, `mpg`, `wmv`. Maximale Größe beträgt 2,0 bis 4,0 GB.
    * **Analyse (getID3)**: Die PHP-Bibliothek `getID3` analysiert die temporäre Datei bezüglich Abspieldauer (`playtime_seconds`) und Auflösung (mindestens 640x480).
    * **Berechnung des Preises**: Der Gesamtpreis des Videos (`amount_own`) wird auf Basis der Dauer in Sekunden und des Sekundenpreises berechnet (`round(playtime_seconds * amount_second)`).
    * **Speicherung**: Die Datei wird als `[movie_id]_[file_id].[extension]` in den Händler-Ordner verschoben.
    * **Datenbank-Status**: Setzt `convert_status = '0'` (bereit für Konvertierung).
  * **Schritt 3 (Erfolg)**: Bestätigung für den Creator, dass das Video in Kürze konvertiert wird.

---

## 3. ⚙️ Asynchrone Video-Konvertierung (Cronjob)
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
    5. **Streaming-Optimierung (qt-faststart)**: Wichtig! Nach der Generierung von Desktop- und Mobil-MP4s wird das Tool `qt-faststart` aufgerufen. Dies verschiebt die Metadaten (moov atom) an den Anfang der Datei, wodurch das Video gestreamt werden kann, noch während es herunterlädt.
  * **Vorschaubilder (Thumbnails)**: Nach erfolgreicher Konvertierung extrahiert FFmpeg 10 Screenshots aus dem Video, die gleichmäßig über die Gesamtlaufzeit verteilt sind (`thumb_[movie_id]_[file_id]_%d.jpg`).
  * **Datenbereinigung**: Die ursprüngliche, hochgeladene Videodatei wird gelöscht, um Speicherplatz zu sparen.
  * **Datenbank-Status**: Setzt `convert_status = '2'` (Konvertierung erfolgreich beendet). Bei Fehlern wird `convert_status = '3'` gesetzt.

---

## 4. 🖼️ Vorschaubilder und Custom Covers (MCP)
Nach der Konvertierung kann der Händler das Video bearbeiten (`mcp/includes/movie.php`):
* **Thumbnail-Auswahl**: Der Händler wählt aus den 10 extrahierten Thumbnails jeweils eines für die FSK16- und FSK18-Vorschau aus. Dies wird als Zahl (1-10) in den Spalten `preview_image_fsk16`/`preview_image_fsk18` der Tabelle `movies` gespeichert.
* **Custom Poster Upload**: Lädt der Händler eigene Vorschaubilder hoch (`mcp/includes/uploader/upload_movie_poster.php`), werden diese als `thumb_[movie_id]_[file_id]_11.[ext]` (FSK16) bzw. `_12.[ext]` (FSK18) im Videoordner gespeichert.
* **Freigabe**: Das Speichern setzt `released = '1'` (zur Prüfung freigegeben) und `movie_checked = '0000-00-00 00:00:00'`, wodurch das Video dem Administrator zur Prüfung vorgelegt wird.

---

## 5. 🔍 Prüfung und Freischaltung (ACP)
Ein Administrator prüft das Video im **Admin Control Panel (ACP)**:

* **Listenansicht**: `acp/includes/movies_checking.php` (AJAX-Quelle: `acp/includes/ajax/movies_checking.php`).
  * Filtert nach: `movie_checked = '0000-00-00 00:00:00' AND released = '1' AND convert_status > '1'`.
* **Prüfungsseite**: `acp/includes/movie_checking.php`.
  * Der Admin kann alle Metadaten anpassen, die FSK16/18 Covers ändern und die Vorschau-Länge definieren.
  * **Freischaltung (Status active)**: Setzt `status = 'active'`, `movie_checked = current_datetime` und fügt das Video in die Tabelle `movies_online` ein. Erst hierdurch ist das Video auf den Frontend-Webseiten sichtbar.
  * **Ablehnung (Rejection)**: Der Admin wählt einen Ablehnungsgrund aus.
    * Der Status `released` wird auf `2` (abgelehnt) gesetzt.
    * Der Grund wird in der Tabelle `rejection_reason_movie_history` geloggt.
* **Fehlersuche & Archivierung (`/gesperrte-Filme`)**:
  * Abgelehnte, blockierte und gelöschte Filme werden gesammelt aufgelistet.
  * Farbige Badges (`Löschung`, `Abgelehnt`, `Gesperrt`) und eine neue Spalte "Bearbeitet am" erlauben eine direkte Zuweisung des aktuellen Status und Änderungszeitpunkts.

---

## 6. 🗑️ Löschen und Archivieren
Um alte, nicht mehr genutzte Inhalte zu bereinigen, greifen nach Freigabe des neuen Cronjobs folgende Schutzfristen (simuliert unter `/Content-Bereinigung`):
* **Regel 1 (Nie gekauft)**: Sofortige Löschung nach Soft-Delete.
* **Regel 2 (Inaktiv > 2 Jahre)**: Löschung nach 30 Tagen Karenzzeit ab Soft-Delete.
* **Regel 3 (Aktiv < 2 Jahre)**: Löschung nach 365 Tagen ab Soft-Delete (Kundenrechte-Schutz).
* **Regel 4 (Alt & Abgelehnt > 180 Tage)**: Sofortige Löschung von ungenutzten abgelehnten Film-Entwürfen, die seit 6 Monaten nicht mehr bearbeitet wurden.

Bei der physischen Bereinigung durch den Cronjob (`cronjobs/delete_movie.php`):
* Wird die Gesamtgröße des Video-Ordners ermittelt.
* Wird der physische Ordner gelöscht.
* Wird ein Eintrag in `movies_deleted` mit der freigegebenen Byte-Größe zur Protokollierung geschrieben.
* Werden alle Bezüge in `movies`, `movies_online` und `movies_access` bereinigt.

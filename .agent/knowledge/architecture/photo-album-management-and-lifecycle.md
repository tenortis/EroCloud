---
name: photo-album-management-and-lifecycle
description: Vollstaendige Architektur-, Datenbank-, API- und Loesch-Lifecycle-Dokumentation fuer Fotoalben in EroCloud (MCP v2.0, ACP v2.0, API-Endpunkte, Soft-Delete vs. Admin-Hard-Delete, Cronjob-Bereinigung und EroCMS-Synchronisation).
usage: 'Wird automatisch herangezogen bei allen Arbeiten an Fotoalben im MCP, ACP, in der API, in Cronjobs sowie bei Synchronisations- oder Loeschfragen mit angeschlossenen EroCMS-Portalen.'
---

# 📸 Fotoalben-Architektur, Lösch-Lebenszyklus & EroCMS-Sync

> **Kontext-Referenz:** Diese Dokumentation ergänzt `@master.md`, `@erocms-api-import.md`, `@mcp-modernization-architecture.md` und `@acp-modernization-architecture.md`. Sie ist die verbindliche Single Source of Truth für alle Abläufe rund um Fotoalben in der **EroCloud** und deren Zusammenspiel mit externen **EroCMS**-Webseiten.

---

## 1. 📂 Datenbank-Tabellen & Dateisystem-Struktur

### 1.1 Beteiligte MySQL-Tabellen (`btuzunczvw`)

| Tabelle | Zweck & wichtigste Spalten |
| :--- | :--- |
| **`photo_albums`** | Zentrale Arbeits- & Historientabelle (Entwürfe, Prüfung, freigegebene & gelöschte Alben). Wichtige Spalten: `id` (numerische interne DB-ID, sichtbar im MCP als `#780`), `album_id` (32-stelliger MD5-Hash für API & externe Portale), `merchant_id`, `actor_id`, `storage_location` (`photo_albums`), `checksum` (MD5), `number_of_photos`, `preview_image_fsk16`, `preview_image_fsk18`, `title` (max. 65 Zeichen im UI), `description`, `meta_title`, `meta_description`, `seo_url`, `category_master` (`porn` \| `fetish`), `category_slave` (Komma-Liste), `online_at`, `amount_webmaster`, `amount_download` (Preis in Euro-Cent, `0` bis `5000`), `views`, `purchases`, `album_checked` (`'0000-00-00 00:00:00'` = ungeprüft/Nachprüfung offen), `released` (`0` = Entwurf, `1` = eingereicht/freigegeben, `2` = abgelehnt), `status` (`'active'`, `'blocked'`, `'deleted'`), `deleted_datetime`, `performer_mode`, `owner_in_content`. |
| **`photo_albums_online`** | Öffentliche Vertriebs- & Synchronisationstabelle für alle jemals freigegebenen Fotoalben. Enthält eine eigene Auto-Increment-`id` (`online_id` im ACP) und wird über `album_id` (32-stelliger Hash) 1:1 mit `photo_albums` verknüpft. Besitzt ebenfalls `status` (`'active'`, `'blocked'`, `'deleted'`) und `deleted_datetime`. **WICHTIG:** Beim Soft-Delete bleibt der Datensatz zwingend in `photo_albums_online` erhalten (siehe Abschnitt 4)! |
| **`photo_albums_photos`** | Einzelfotos eines Albums (`id`, `file_id` [32-stelliger Hash], `album_id` [32-stelliger Hash], `merchant_id`, `filename` [`IMG_YYYYMMDD_HHMMSS_...jpg/png`], `upload_datetime`, `rejected` [`0` = ok, `1` = im ACP beanstandet]). |
| **`photo_albums_access`** | Rechtmäßige Kundenkäufe von Endkunden angeschlossener Portale (`id`, `user_key`, `album_id` [32-stelliger Hash], `user_id`, `shop_id`, `buy_as` [`download`], `buy_timestamp`, `access_token` [30 Min. gültig], `access_token_datetime`, `album_price`, `actor_commision`, `actor_commision_percent`). **Maßgebliche Quelle für den Käuferschutz!** |
| **`photo_albums_categories`** | Zulässige Unterkategorien (`name_id`, `category_group`, `de_name_value`). |
| **`rejection_reason_photo_album`** & **`_history`** | Standard-Ablehnungsgründe und Ablehnungshistorie pro `album_id`. |

### 1.2 Physische Verzeichnisstruktur auf dem Server (`t199.nlserver.net`)
* **Basis-Konstante:** `PHOTO_ALBUMS_PATH` (`/var/www/web1/cloud_storage`) + `storage_location` (`photo_albums`)
* **Album-Ordner:** `/var/www/web1/cloud_storage/photo_albums/{merchant_id}/{photo_albums.id}/`
  * `{hash}_16.jpg` / `.png` — Jugendfreies **FSK16-Cover** (`preview_image_fsk16`)
  * `{hash}_18.jpg` / `.png` — Unzensiertes **FSK18-Cover** (`preview_image_fsk18`)
  * `images/IMG_...jpg` — Original-Einzelfotos (`photo_albums_photos.filename`)
  * `images/thumb_IMG_...jpg` — Automatisch von [`api/album_photo_thumb.php`](file:///c:/xampp/htdocs/EroCloud/api/album_photo_thumb.php) generiertes, verpixeltes 200px-Vorschaubild für ungekaufte Album-Ansichten auf externen Portalen.

---

## 2. 🎨 Creator-Workflow im MCP v2.0 (`mcp/mcp_neu/`)

Gesteuert durch [`PhotoAlbumsController.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/PhotoAlbums/PhotoAlbumsController.php), geroutet in [`Router.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Core/Router.php):

1. **3-Schritte-Upload (`#/photo-album-upload`):**
   * **Schritt 1 (`photo-albums/save-step1`):** Legt Entwurf in `photo_albums` an (`released = 0`, `album_checked = '0000-00-00 00:00:00'`, `status = 'active'`).
   * **Schritt 2 (`views/upload.php`):** Mehrfach-Upload von JPG/PNG bis 6 MB (`photo-albums/upload-photos`), Gestaltung von FSK16/FSK18-Covern im integrierten Vollbild **Cover-Studio** (`photo-albums/upload-cover`), Drehen/Löschen einzelner Bilder und Einreichung (`photo-albums/publish` $\rightarrow$ prüft mind. 4 Fotos, FSK16-Cover, FSK18-Cover, keine rot abgelehnten Fotos $\rightarrow$ setzt `released = 1`).
2. **Einzelbearbeitung (`#/photo-album?id={id}`, `views/detail.php`):**
   * Text-, Preis-, Darsteller- und SEO-Änderungen (`photo-albums/update`).
   * **1-Klick-Foto-Rotation (`photo-albums/rotate-photo`, `rotatePhoto()`):**
     * Jede Foto-Kachel in [`detail.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/PhotoAlbums/views/detail.php) und [`upload.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/PhotoAlbums/views/upload.php) besitzt oben rechts zwei Dreh-Buttons (`90° links` / `90° rechts`, Handler `.js-rotate-album-photo-btn` in [`app.js`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/assets/js/app.js)).
     * `rotatePhoto()` normalisiert bei JPEGs **zuerst** eine vorhandene EXIF-`Orientation` (`3`, `6`, `8`), dreht das Bild anschließend per GD `imagerotate()` verlustarm um $\pm 90^\circ$, schreibt es atomar zurück, aktualisiert `filemtime`, löscht zwingend das verpixelte `images/thumb_{filename}` sowie den API-Cache (`ec_invalidate_photo_album_api_cache()`) und liefert neue `&v={timestamp}`-URLs zurück.
   * **Verbindliche Nachprüfungs-Regel:** Sobald bei einem bereits eingereichten oder veröffentlichten Album Cover, Einzelfotos (Upload, Drehung, Löschung) oder Texte geändert werden, wird `photo_albums.album_checked = '0000-00-00 00:00:00'` und `released = '1'` gesetzt, damit die Änderung vor Übernahme nach `photo_albums_online` erneut die ACP-Prüfung durchläuft.
3. **Geschützte Bildauslieferung im MCP ([`mcp/mcp_neu/web/media.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/media.php)):**
   * Route `/AlbumPhoto/{file_id}?w=200|900|1024&v={filemtime}`. Prüft Session + `merchant_id`, berücksichtigt EXIF-`Orientation` auch bei Bildern kleiner als Zielbreite und nutzt `&v={filemtime}` zum sauberen Cache-Busting trotz langem `private, max-age`-Header.

---

## 3. 🛡️ Moderations- & Online-Workflow im ACP v2.0 (`acp/acp_neu/`)

1. **Content-Prüfung (`#/photo-albums-checking` & `#/photo-album-checking`):**
   * [`PhotoAlbumsCheckingController.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/PhotoAlbumsChecking/PhotoAlbumsCheckingController.php): Prüft alle Alben mit `released = 1`, `album_checked = '0000-00-00 00:00:00'` und `status = 'active'`.
   * Einzelbilder können per `photo-albums-checking/toggle-photo` als `rejected = 1` markiert werden. Solange `rejected > 0`, ist eine Freigabe als `active` gesperrt.
   * Bei Freigabe (`approve()`) werden `photo_albums` und `photo_albums_online` synchronisiert, `photo_album_checksum()` berechnet, `ec_invalidate_photo_album_api_cache()` aufgerufen und bei Neu-Veröffentlichung Punkte gutgeschrieben sowie eine Benachrichtigungs-Mail versendet.
2. **Online-Verwaltung (`#/photo-albums-online` & `#/photo-album-edit`):**
   * [`PhotoAlbumsOnlineController.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/PhotoAlbumsOnline/PhotoAlbumsOnlineController.php): Bearbeitet veröffentlichte Alben direkt in `photo_albums_online` und `photo_albums`.
   * **Duplikat-Titel-Prüfung:** Ignoriert gezielt Alben mit `status = 'deleted'`, damit Titel gelöschter Alben wiederverwendet werden können.
   * **ACP-Medienproxy ([`acp/acp_neu/web/media.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/web/media.php)):** Wertet bei `type=album_photo` und `type=album_poster` EXIF-`Orientation` (`3`, `6`, `8`) aus.

---

## 4. 🗑️ Verbindlicher Lösch-Lebenszyklus (Käuferschutz vs. Admin-Hard-Delete)

> **OBERSTES GESETZ (KÄUFERSCHUTZ):** Sobald Endkunden ein Fotoalbum gekauft haben (`photo_albums_access`-Einträge $> 0$ oder `purchases > 0`), dürfen die Bilddateien und Zugriffsrechte **niemals** durch Creator oder automatische Cronjobs gelöscht werden, da das bezahlte Fotoalbum sonst aus dem Kundenkonto (*„Meine Fotoalben“*) verschwindet!

Es existieren exakt **vier klar getrennte Löschstufen**:

### Stufe 1: Entwurfs-Löschung durch Creator im MCP (`photo-albums/delete`)
* **Wo:** [`PhotoAlbumsController::delete()`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/PhotoAlbums/PhotoAlbumsController.php#L1230-L1290)
* **Bedingung:** **Ausschließlich** für ungeprüfte, unveröffentlichte Entwürfe (`album_checked = '0000-00-00 00:00:00'`, `released != 1` und **kein** Eintrag in `photo_albums_online`). Sobald ein Album geprüft oder online war, blockiert das MCP die Selbstlöschung strikt.
* **Wirkung:** Löscht die Entwurfsdateien auf Disk, entfernt `photo_albums_photos` und setzt `photo_albums.status = 'deleted'`, `deleted_datetime = NOW()`.

### Stufe 2: Reguläres Soft-Delete im ACP (`status = 'deleted'` in `photo-albums-online/save`)
* **Wo:** [`PhotoAlbumsOnlineController::save()`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/PhotoAlbumsOnline/PhotoAlbumsOnlineController.php) (Dropdown *„Gelöscht (Soft-Delete / Käuferschutz)“* in [`views/edit.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/PhotoAlbumsOnline/views/edit.php), ebenso automatisch bei Darsteller-Löschung in [`ActorsController::save()`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/Actors/ActorsController.php)).
* **Berechtigung:** Nur Administratoren (`$_SESSION['employee_rule'] === 'write'`).
* **Technischer Ablauf (WARUM `photo_albums_online` NICHT per `DELETE` geleert werden darf!):**
  1. Sowohl `photo_albums_online` als auch `photo_albums` erhalten `status = 'deleted'` und `deleted_datetime = NOW()` (keine physische Dateilöschung!).
  2. `photo_album_checksum()` hängt bei `status != 'active'` automatisch `|{status}` vor der MD5-Bildung an $\rightarrow$ die Checksumme ändert sich sofort.
  3. `ec_invalidate_photo_album_api_cache($albumIdHash)` löscht sofort `api/temp/get_photo_album/{album_id}.tmp`.
  4. **Warum die Zeile in `photo_albums_online` zwingend stehen bleiben muss:** Angeschlossene EroCMS-Seiten fragen `get_photo_albums` (`SELECT ... FROM photo_albums_online WHERE actor_id = ...`) ab. Würde die Zeile sofort aus `photo_albums_online` gelöscht, erhielten EroCMS-Seiten niemals `<status>deleted</status>` und würden das Album ewig weiter anzeigen!
  5. **Käufer-Zugriff bleibt 100 % intakt:** [`api/get_photo_album_url.php`](file:///c:/xampp/htdocs/EroCloud/api/get_photo_album_url.php), [`api/photo_album_downloader.php`](file:///c:/xampp/htdocs/EroCloud/api/photo_album_downloader.php) und [`api/my_album_photo.php`](file:///c:/xampp/htdocs/EroCloud/api/my_album_photo.php) prüfen ausschließlich `photo_albums_access` + physische Dateien. Bestehende Käufer können ihr Album weiterhin ansehen und als ZIP herunterladen.

### Stufe 3: Automatische Cronjob-Bereinigung ungekaufter Alben ([`cronjobs/delete_photo_album.php`](file:///c:/xampp/htdocs/EroCloud/cronjobs/delete_photo_album.php))
* **Eiserne Schutzregel (SQL + PHP Double-Check):** Verarbeitet **ausnahmslos** Fotoalben mit **`0` Käufen** (`COALESCE(pa.purchases, 0) = 0`, `COALESCE(pao.purchases, 0) = 0` **UND** `NOT EXISTS (SELECT 1 FROM photo_albums_access ...)`). Ein gekauftes Fotoalbum wird durch den Cronjob **niemals** gelöscht!
* **Regel 1 (Ungekaufte Soft-Delete-Alben nach $\ge 30$ Tagen):** Sobald `deleted_datetime` mindestens 30 Tage zurückliegt (alle EroCMS-Portale hatten 30 Tage Zeit für den Lösch-Sync), werden Ordner, `photo_albums_photos`, `photo_albums_online` und `photo_albums` rückstandslos gelöscht.
* **Regel 2 (Verwaiste Entwürfe/Ablehnungen nach $\ge 180$ Tagen):** Nie veröffentlichte Entwürfe (`released IN (0, 2)`, `pao.id IS NULL`), die seit über 180 Tagen inaktiv sind und `0` Käufe haben, werden physisch und in der DB aufgeräumt.

### Stufe 4: Manuelles physisches Hard-Delete durch Administrator (`photo-albums-online/hard-delete`)
* **Wo:** [`PhotoAlbumsOnlineController::hardDelete()`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/PhotoAlbumsOnline/PhotoAlbumsOnlineController.php), eigene rote Admin-Karte + Modal `#paoHardDeleteModal` am Ende von [`views/edit.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/PhotoAlbumsOnline/views/edit.php).
* **Strikte Berechtigung:** Ausschließlich `$_SESSION['employee_rule'] === 'write'` (Administratoren; Moderatoren/Prüfer sehen weder die Karte noch erlaubt der Controller den Aufruf $\rightarrow$ `403`).
* **Zweistufige Sicherheitsprüfung:**
  1. **Bei `0` Käufen** (z. B. versehentlich doppelt freigegebene Alben oder Test-Uploads): Sofortige physische Löschung aller Bilddateien auf Knopfdruck möglich.
  2. **Bei $> 0$ Käufen** (Rechtsproblem, Persönlichkeitsrecht, Urheberrecht, DSGVO): Zwingend Checkbox `force_legal_delete = 1` **und** Pflichtbegründung (`delete_reason`, mind. 5 Zeichen) erforderlich; löscht zusätzlich `photo_albums_access` und schreibt einen unveränderlichen Audit-Eintrag (`[ADMIN HARD-DELETE ...]`) in `admin_infos` und `log_action()`.
* **Sync-Tombstone:** Setzt `photo_albums_online` & `photo_albums` auf `status = 'deleted'`, `number_of_photos = 0`, leere Cover und neue Checksumme (`md5(...|hard_deleted|...)`), damit auch angeschlossene EroCMS-Seiten das Album beim nächsten Sync sicher abräumen, bevor der Cronjob den leeren 0-Byte-Tombstone nach 30 Tagen entfernt.

---

## 5. 🔄 End-to-End Synchronisation mit externen EroCMS-Portalen

### 5.1 API-Endpunkte in `EroCloud/api/`
1. **`?api_name=get_photo_albums&actor_id={id}`** ([`api/includes/get_photo_albums.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/get_photo_albums.inc.php)):
   * Liest **direkt und ungecached** `checksum, album_id, actor_id, online_at, status` aus `photo_albums_online`.
2. **`?api_name=get_photo_album&album_id={hash}`** ([`api/includes/get_photo_album.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/get_photo_album.inc.php)):
   * Liefert Titel, Beschreibung, SEO-Daten, Preis (`amount_download`), Cover-URLs, Kategorien, Fotoanzahl und `status`.
   * **50-Minuten-Datei-Cache:** `api/temp/get_photo_album/{album_id}.tmp`. **Wichtig:** Jede Änderung im ACP/MCP muss `ec_invalidate_photo_album_api_cache($albumIdHash)` aus [`includes/functions.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/functions.inc.php) aufrufen!
3. **`?api_name=get_photo_album_thumbs&album_id={hash}`** ([`api/includes/get_photo_album_thumbs.inc.php`](file:///c:/xampp/htdocs/EroCloud/api/includes/get_photo_album_thumbs.inc.php)) & [`api/album_photo_thumb.php`](file:///c:/xampp/htdocs/EroCloud/api/album_photo_thumb.php):
   * Liefert die Liste aller `file_id`s bzw. rendert die verpixelten 200px-Vorschaubilder (`images/thumb_{filename}`).
4. **Kauf & Kundenabruf:**
   * [`api/buy_photo_album_erocms.php`](file:///c:/xampp/htdocs/EroCloud/api/buy_photo_album_erocms.php): Trägt Kauf in `photo_albums_access` ein und erhöht `purchases` in `photo_albums` + `photo_albums_online`.
   * [`api/get_photo_album_url.php`](file:///c:/xampp/htdocs/EroCloud/api/get_photo_album_url.php), [`api/photo_album_downloader.php`](file:///c:/xampp/htdocs/EroCloud/api/photo_album_downloader.php) (ZIP-Stream) & [`api/my_album_photo.php`](file:///c:/xampp/htdocs/EroCloud/api/my_album_photo.php) (Einzelbild-Vollansicht für Käufer).

### 5.2 Gegenseite im EroCMS (`c:\xampp\htdocs\EroCMS`) & kritische Architektur-Regeln
Damit Änderungen an der EroCloud-API niemals Folgefehler auf den 100+ EroCMS-Mandantenseiten auslösen, sind diese Zusammenhänge verbindlich zu beachten:
1. **Zwingendes `utf8mb4` für 4-Byte-Emojis:**
   * Da MCP v2.0 & ACP v2.0 echte UTF-8-Emojis (z. B. `👠🔥`) in `title`, `description` und `meta_description` speichern, müssen alle EroCMS-Import-Skripte (`EroCMS/includes/cronjobs/import_new_photo_albums.php`, `EroCMS/erocloud_api/config.inc.php` u. a.) zwingend mit `SET NAMES 'utf8mb4'` arbeiten. Ein altes `SET NAMES 'utf8'` (`utf8mb3`) führt bei Emojis zu `1366 Incorrect string value` (`mysqli_sql_exception`) und bricht den gesamten Import-Cronjob ab!
2. **Schutz vor leeren Geister-Alben (`headline = ''`):**
   * `import_new_photo_albums.php` legt in `erocloud_fotoalben_alben` zunächst einen Platzhalter an und befüllt ihn über `update_erocloud_photo_album()`. Nach `fotoalben_alben` darf ein Datensatz **nur** kopiert werden, wenn `headline != ''` ist; etwaige leere Alt-Zeilen heilt der Cronjob beim nächsten Lauf automatisch nach.
3. **Verhalten auf EroCMS-Seiten bei `status = 'deleted'` oder `'blocked'` (0-DDL `only_if_buyed`):**
   * **Ungekauft (`anzahl_gekauft = 0` & kein Eintrag in `abrechnung`):** EroCMS ruft automatisch `del_fotoalbum()` auf und löscht das Album komplett von der Creator-Homepage.
   * **Bereits gekauft (`> 0` Käufe) oder `status = 'blocked'`:** EroCMS behält die Zeile in `fotoalben_alben` (damit der `INNER JOIN` in `meine_fotoalben.php` für bestehende Käufer erhalten bleibt!), setzt aber `online_ab = '999912312359'` und sperrt Direktaufruf/Neukauf für Nicht-Käufer. Dadurch ist das Album öffentlich unsichtbar, für bestehende Käufer aber dauerhaft verfügbar.

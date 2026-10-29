---
name: co-actor-verification-and-model-release
description: Vollstaendige Architektur-, Workflow- und Datenbank-Dokumentation fuer das Drehpartner- & ID-Check-System (Co-Actor Verification, Smartphone-QR-Self-Service, Model Release, KI-Gesichtsreferenz und ACP-Freigabe).
usage: 'Wird automatisch herangezogen bei allen Arbeiten an Darsteller-Verifizierungen, Model-Releases, QR-Code-Workflows, Film-/Fotoalbum-Darstellerzuordnungen, ACP-Content-Pruefungen und der spaeteren KI-Gesichtserkennung (Vision-AI).'
---

# 🪪 Drehpartner & ID-Check (Co-Actor Verification & Digital Model Release System)

> **Kontext-Referenz:** Diese Dokumentation ergänzt `@master.md`, `@mcp-modernization-architecture.md`, `@video-management.md` sowie `@ai-compliance-and-subtitle-pipeline.md` und beschreibt die gesamte Architektur, Datenhaltung, Sicherheitslogik und Benutzerführung der Darsteller- & Drehpartner-Verifizierung in der EroCloud.

---

## 1. 🎯 Hintergrund, Rechtsgrundlage & Kernziele

Bevor ein Film (und künftig auch ein Fotoalbum) in der EroCloud veröffentlicht werden darf, muss zweifelsfrei dokumentiert und überprüfbar sein, dass **jede im Inhalt sichtbare Person**:
1. zum Zeitpunkt der Produktion **volljährig ($\ge 18$ Jahre)** war (18 U.S.C. § 2257 / JMStV / StGB / Visa GBPP & Mastercard BRAM Compliance),
2. ihre **Identität durch amtliche Lichtbilddokumente** (Ausweis-Vorderseite, Ausweis-Rückseite sowie ID-Shot mit Ausweis neben dem eigenen Gesicht) nachgewiesen hat, und
3. der kommerziellen Veröffentlichung und Verbreitung in der EroCloud **rechtsverbindlich zugestimmt** hat (Model Release / Einverständniserklärung).

### Die 4 tragenden Säulen des Systems:
1. **Mobile-First QR-Code Self-Service statt Papierkrieg:**
   * Der Partner legt einen Drehpartner im MCP nur mit einem Kurznamen an und erhält sofort einen **lokal generierten QR-Code**.
   * Der Drehpartner scannt den QR-Code am Set mit seinem eigenen Smartphone, füllt seine Ausweisdaten selbst aus, fotografiert sich und seinen Ausweis direkt über die Smartphone-Kamera und **unterschreibt mit dem Finger auf dem Display**.
   * Daraus generiert die EroCloud automatisch ein **fälschungssicheres, druck- und als PDF speicherbares Einverständnis-Zertifikat** inkl. Zeitstempel, IP-Adresse, Geräte-Signatur und SHA-256-Hash.
2. **Pflicht-Eigenverifizierung für den Partner-Account („Ich“-Eintrag / `is_owner = 1`):**
   * Da der Partner-Account meist selbst als Hauptdarstellerin / Hauptdarsteller vor der Kamera steht, besitzt jeder Händler automatisch einen eigenen **„Ich“-Eintrag** (`is_owner = 1`).
   * Bevor ein Film mit *„Nur ich bin zu sehen“* (`solo_self`) oder *„Ich zusammen mit anderen Personen“* (`group`) gespeichert werden kann, muss auch dieser eigene „Ich“-Eintrag einmalig per Smartphone-QR-Code verifiziert werden.
3. **Vorbereitung für spätere KI-Gesichtserkennung (Vision-AI Video-Scan):**
   * Auf einem klassischen ID-Shot verdeckt der hingehaltene Personalausweis oft Teile der Wange, des Kinns oder wirft Schatten, was die Extraktion eines präzisen 512-D Face-Embeddings (z. B. via InsightFace / ArcFace) erschwert.
   * Deshalb verlangt der Smartphone-Workflow als **Foto 1 zwingend ein unverdecktes, scharfes Frontal-Selfie (`face_ref_image`)** ohne Ausweis vor dem Gesicht – zusätzlich zum klassischen ID-Shot (`id_shot_image`) als **Foto 2**.
   * Zudem werden über den QR-Code-Flow **keine alten Galerie-Uploads für Selfies** zugelassen (`capture="user"`), sondern immer frische Kamera-Aufnahmen erzwungen.
4. **Altbestand-Unterstützung (`legacy_upload`) mit aktivem Upgrade-Hinweis auf QR-Code:**
   * Für bereits abgedrehte ältere Filme, bei denen der Drehpartner aktuell nicht vor Ort ist, die Unterlagen aber in Papier-/PDF-/Fotoform vorliegen, kann der Partner die Dokumente manuell hochladen (`verification_method = 'legacy_upload'`).
   * Solche Einträge sind im MCP mit einem gelben Hinweis-Badge (*„Altbestand-Upload – Bei Wiedersehen per QR-Code erneuern“*) markiert, weil alte Papier-/PDF-Scans später **nicht** für die automatische KI-Gesichtssuche in Videos herangezogen werden können. Sieht der Partner den Darsteller wieder, kann er denselben Eintrag jederzeit per Klick auf den QR-Code-Button auf eine frische Smartphone-Verifizierung (`qr_mobile`) hochstufen.

---

## 2. 🗺️ Datei- & Komponenten-Landkarte (Wo liegt was?)

| Bereich | Dateipfad | Aufgabe & Verantwortlichkeit |
| :--- | :--- | :--- |
| **Zentrale Kernklasse** | [`includes/klassen/CoActorManager.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/CoActorManager.inc.php) | Herzstück des Systems: Automatische DB-Schema-Migration (`ensureTablesExist`), AES-Verschlüsselung/-Entschlüsselung, Verwaltung des „Ich“-Eintrags (`ensureOwnerEntry`), QR-Token-Verwaltung, Datei-Uploads, Validierung bei Film-/Album-Speicherung (`validateContentPerformers`), Verknüpfungsspeicherung (`saveContentPerformers`), Zertifikats-Generator (`renderConsentDocumentHtml`) und ACP-Prüflogik (`renderAcpMoviePerformersBox`, `handleAcpAction`). |
| **Lokaler QR-Generator** | [`includes/klassen/QrCodeSvg.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/QrCodeSvg.inc.php) | 100 % lokaler, DSGVO-konformer ISO/IEC 18004 QR-Code-Generator in reinem PHP (Versionen 1–10, Byte-Mode, Reed-Solomon $GF(2^8)$ Fehlerkorrektur, SVG- & Data-URI-Ausgabe). Es werden **keinerlei Daten an externe QR-Code-APIs** gesendet. |
| **Smartphone Self-Service** | [`mcp/mcp_neu/web/verify_co_actor.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/verify_co_actor.php) | Öffentliche, ausschließlich über das kryptografische 48-Zeichen-Token (`?t=<qr_token>`) erreichbare Mobile-Web-App für Darsteller & Account-Inhaber. Enthält Stammdaten-Formular, Kamera-Aufnahme mit Client-seitiger Bildkomprimierung und das Touch-Unterschriften-Pad. |
| **Sicherer Medien- & PDF-Proxy** | [`mcp/mcp_neu/web/co_actor_media.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/co_actor_media.php) | Geschützter Auslieferungs-Endpunkt für die außerhalb des Webroots gespeicherten Ausweisbilder (`face_ref`, `id_shot`, `id_front`, `id_back`, `signature`, `legacy_doc`) sowie für das druckbare Einverständnis-Zertifikat (`type=certificate`). Prüft MCP-Session, ACP-Admin-Session oder gültiges `qr_token`. |
| **MCP Controller** | [`mcp/mcp_neu/app/src/Modules/CoActors/CoActorsController.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/CoActors/CoActorsController.php) | Controller für die eigenständige MCP-Verwaltungsseite (`#/co-actors`) sowie alle AJAX-Aktionen (Anlegen, Laden für Live-Polling, Token erneuern, Altbestand-Upload, Löschen). |
| **MCP Hauptansicht** | [`mcp/mcp_neu/app/views/co_actors/index.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/views/co_actors/index.php) | Eigenständige Verwaltungsseite **„Drehpartner & ID-Check“** (`#/co-actors`) mit hervorgehobener „Ich“-Kachel oben, KPI-Übersicht, Filterung und Drehpartner-Karten. |
| **MCP Quick-Modal** | [`mcp/mcp_neu/app/views/co_actors/_quick_modal.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/views/co_actors/_quick_modal.php) | Überall wiederverwendbares Bootstrap-5-Modal zum Anlegen neuer Drehpartner, Anzeigen des QR-Codes inkl. **4-Sekunden-Live-Polling**, Kopieren des Smartphone-Links und Hochladen von Altbestand-Dokumenten – ohne die aktuelle Seite (z. B. den Film-Upload) verlassen zu müssen. |
| **Film-Darsteller-Sektion** | [`mcp/mcp_neu/app/views/movies/_performer_section.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/views/movies/_performer_section.php) | Interaktives Formular-Partial im Film-Upload (`upload_step1.php`) und Film-Editor (`edit.php`) mit den 3 großen Auswahl-Kacheln (`solo_self`, `group`, `others_only`), Live-Statusprüfung und Direkt-Verifizierung per Modal. |
| **Film-Controller** | [`mcp/mcp_neu/app/src/Modules/Movies/MoviesController.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/Movies/MoviesController.php) | Lädt die Darsteller-Daten für Upload (`uploadStep1`) & Bearbeitung (`edit`) und führt in `saveStep1()` sowie `update()` die verbindliche Server-Validierung (`validateContentPerformers`) und Speicherung (`saveContentPerformers`) aus. |
| **MCP Router & Sidebar** | [`mcp/mcp_neu/web/api.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/api.php) & [`mcp/mcp_neu/app/views/layouts/main.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/views/layouts/main.php) | Registriert die Routen `co-actors`, `co-actors/save`, `co-actors/get`, `co-actors/delete`, `co-actors/regenerate-token`, `co-actors/legacy-upload` und bindet den Menüpunkt **„Drehpartner & ID-Check“** unter *Profile* in der linken Sidebar ein. |
| **ACP Zentrale Prüf-Übersicht** | [`acp/includes/co_actors_checking.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/co_actors_checking.php) | Eigener Menüpunkt im ACP unter *Content Prüfen* $\rightarrow$ **„Drehpartner & ID-Check (X)“** (`/Drehpartner-pruefen`). Zeigt alle offenen (`under_review`), verifizierten (`verified`), abgelehnten (`rejected`) und ausstehenden (`pending_performer`) Darsteller & Account-Inhaber aller Partner inkl. Volltextsuche, verknüpfter Filme, Fotos, PDF-Zertifikat und Direkt-Freigabe/Ablehnung. |
| **ACP Film-Prüfung & Editor** | [`acp/includes/movie_checking.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/movie_checking.php) & [`acp/includes/movie_edit.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/movie_edit.php) | Zeigt dem Administrator bei der Filmprüfung (`/Film-pruefen/{id}`) und im Film-Editor (`/Film-bearbeiten/{id}`) die Box **„Darsteller-Verifizierung & Einverständniserklärungen (ID-Check)“** mit allen Fotos, Ausweisdaten, PDF-Zertifikat, Einzel-Freigabe/Ablehnung und blockiert die Film-Veröffentlichung bei abgelehnten oder unvollständigen Darstellern. |

---

## 3. 🗄️ Datenbank-Architektur & AES-Verschlüsselung

Die Tabellen und Spalten werden durch `CoActorManager::ensureTablesExist()` selbstheilend angelegt bzw. migriert.

### 3.1 Tabelle `co_actors` (Darsteller- & Drehpartner-Stammsatz)
Speichert sowohl den eigenen **„Ich“-Eintrag** (`is_owner = 1`) des Partners als auch alle externen Drehpartner (`is_owner = 0`).

* **Zuordnung & Identifikation:**
  * `id` (`INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`)
  * `merchant_id` (`INT UNSIGNED NOT NULL`) – Zugehöriger EroCloud-Partner
  * `is_owner` (`TINYINT(1) NOT NULL DEFAULT 0`) – `1` = Der Partner-Account selbst („Ich“), `0` = Weiterer Drehpartner
  * `linked_actor_id` (`INT UNSIGNED NOT NULL DEFAULT 0`) – Optionale Verknüpfung zu einem öffentlichen EroCloud-Darstellerprofil (`actors.id`)
  * `display_name` (`VARCHAR(120)`) – Anzeigename im MCP (z. B. Vor-/Nachname oder interner Rufname)
  * `creator_name` (`VARCHAR(120)`) – Optionaler Künstler-/Creator-Name (wird im UI als `CreatorName (Klarname)` dargestellt)
  * `gender` (`VARCHAR(20)`) – `'f'`, `'m'`, `'d'`, `''`
* **AES-verschlüsselte personenbezogene Daten (`VARBINARY`, verschlüsselt mit `AES_ENCRYPT(val, AES_KEY)`):**
  * `first_name` (`VARBINARY(255)`) – Rechtlicher Vorname laut Ausweis
  * `last_name` (`VARBINARY(255)`) – Rechtlicher Nachname laut Ausweis
  * `birth_date` (`VARBINARY(128)`) – Geburtsdatum (`YYYY-MM-DD`)
  * `nationality` (`VARCHAR(80)`) – Staatsangehörigkeit / Ausstellungsland (z. B. `DE`, `AT`, `CH`)
  * `street` (`VARBINARY(255)`) – Straße & Hausnummer
  * `zip` (`VARBINARY(64)`) – Postleitzahl
  * `city` (`VARBINARY(150)`) – Wohnort
  * `country` (`VARCHAR(80)`) – Wohnsitzland
  * `id_document_type` (`VARCHAR(50)`) – `'id_card'` (Personalausweis), `'passport'` (Reisepass), `'drivers_license'` (Führerschein)
  * `id_document_number` (`VARBINARY(255)`) – Ausweis-/Dokumentennummer
  * `id_valid_until` (`DATE NULL`) – Ablaufdatum des Ausweises (oder `NULL` bei unbegrenzter Gültigkeit)
  * `email` (`VARBINARY(255)`) – Optionale E-Mail-Adresse des Drehpartners
  * `phone` (`VARBINARY(255)`) – Optionale Telefonnummer des Drehpartners
* **Medien-Dateinamen (relativ zum geschützten Ordner `.../co_actors/{id}/`):**
  * `face_ref_image` (`VARCHAR(255)`) – **Unverdecktes Frontal-Selfie** (Referenzbild für spätere KI-Gesichtserkennung)
  * `id_shot_image` (`VARCHAR(255)`) – **ID-Shot** (Gesicht + Personalausweis gut lesbar daneben in der Hand)
  * `id_front_image` (`VARCHAR(255)`) – **Ausweis Vorderseite** (Nahaufnahme im Klartext)
  * `id_back_image` (`VARCHAR(255)`) – **Ausweis Rückseite** (optional bei Reisepass)
  * `signature_image` (`VARCHAR(255)`) – **Digitale Finger-Unterschrift** als transparentes PNG
  * `consent_pdf_file` (`VARCHAR(255)`) – **Unterschriebenes Dokument / PDF** (bei `legacy_upload`)
* **Rechtssicherer Audit-Trail & QR-Token:**
  * `verification_method` (`VARCHAR(30)`) – `'qr_mobile'` (Smartphone-Self-Service) oder `'legacy_upload'` (Manueller Altbestand-Upload)
  * `qr_token` (`VARCHAR(96) UNIQUE`) – Kryptografisches 48-Zeichen-Hex-Token (`bin2hex(random_bytes(24))`)
  * `qr_token_expires_at` (`DATETIME NULL`) – Gültigkeitsdauer des QR-Links (Standard: `+7 Tage`, jederzeit per Klick verlängerbar)
  * `consent_signed_at` (`DATETIME NULL`) – Exakter Zeitpunkt der digitalen Unterschrift bzw. des Dokument-Uploads
  * `consent_signer_ip` (`VARCHAR(64)`) – IP-Adresse des Smartphones bei der Unterschrift
  * `consent_user_agent` (`VARCHAR(255)`) – Browser-/Geräte-Kennung des Smartphones
  * `consent_text_hash` (`VARCHAR(64)`) – SHA-256-Hash des unterschriebenen Rechtstextes + Zeitstempel
* **Prüfstatus-Maschine (`checked_status`):**
  * `'pending_performer'` – Angelegt, wartet noch auf Smartphone-Scan / Dokumente (`is_ready_for_use = false`)
  * `'under_review'` – Alle Pflichtfotos & Unterschrift wurden vollständig eingereicht; Darsteller kann sofort in Filmen verknüpft werden (`is_ready_for_use = true`) und liegt zur finalen Sichtprüfung im ACP bereit
  * `'verified'` – Vom Administrator im ACP final geprüft und bestätigt (`is_ready_for_use = true`)
  * `'rejected'` – Vom Administrator im ACP abgelehnt (`rejection_reason` enthält den Grund; `is_ready_for_use = false`, bis der Darsteller per QR-Code neue Aufnahmen einreicht)
  * `checked_by_employee` (`VARCHAR(80)`) & `checked_on` (`DATETIME NULL`) – Dokumentiert, welcher ACP-Admin wann geprüft hat.

### 3.2 Tabelle `content_co_actors` (Polymorphe Verknüpfung zu Filmen & Fotoalben)
Verknüpft beliebig viele Personen aus `co_actors` mit einem Film (`movies.id`) oder künftig einem Fotoalbum (`photo_albums.id`):
* `content_type` (`VARCHAR(20)`) – `'movie'` oder `'album'`
* `content_id` (`INT UNSIGNED`) – ID des Films bzw. Fotoalbums
* `co_actor_id` (`INT UNSIGNED`) – Verweis auf `co_actors.id`
* `merchant_id` (`INT UNSIGNED`) – Partner-ID
* `role_type` (`VARCHAR(20)`) – `'owner'` (Account-Inhaber „Ich“) oder `'co_actor'` (Weiterer Drehpartner)
* `UNIQUE KEY uq_content_actor (content_type, content_id, co_actor_id)`

### 3.3 Erweiterte Spalten in `movies` und `photo_albums`
Sowohl `movies` als auch `photo_albums` besitzen die beiden Steuerungs-Spalten:
* `performer_mode` (`VARCHAR(20) NOT NULL DEFAULT 'solo_self'`):
  * `'solo_self'` – **Nur ich bin im Inhalt zu sehen** (Account-Inhaber alleine)
  * `'group'` – **Ich zusammen mit anderen Personen** (Account-Inhaber + mindestens 1 weiterer Drehpartner)
  * `'others_only'` – **Nur andere Personen (ohne mich)** (Mindestens 1 anderer Drehpartner; der Account-Inhaber ist selbst nicht vor der Kamera)
* `owner_in_content` (`TINYINT(1) NOT NULL DEFAULT 1`):
  * `1` bei `solo_self` und `group`, `0` bei `others_only`.

---

## 4. 📁 Physische Dateiablage & Schutz vor unbefugtem Zugriff

Alle hochgeladenen Ausweisfotos, KI-Gesichtsreferenzen, Unterschriften und Altbestand-Dokumente werden **außerhalb des öffentlichen Webroots** im zentralen Cloud-Storage-Verzeichnis des jeweiligen Händlers abgelegt:

```text
{MOVIES_PATH}/{MOVIES_DEFAULT_DIR}/{merchant_id}/co_actors/{co_actor_id}/
├── .htaccess                      # "Order deny,allow / Deny from all" + "Require all denied"
├── face_ref_{timestamp}_{rand}.jpg # Unverdecktes Frontal-Selfie (KI-Gesichtsreferenz)
├── id_shot_{timestamp}_{rand}.jpg  # Selfie mit Personalausweis neben dem Gesicht
├── id_front_{timestamp}_{rand}.jpg # Personalausweis / Reisepass Vorderseite
├── id_back_{timestamp}_{rand}.jpg  # Personalausweis Rückseite (optional)
├── signature_{timestamp}_{rand}.png# Freigestellte Touch-Unterschrift vom Smartphone
└── legacy_doc_{timestamp}_{rand}.* # Manuell hochgeladenes Model-Release (PDF/JPG/PNG bei Altbestand)
```

* **Automatische Bereinigung bei Neu-Aufnahme:** Wenn ein Darsteller z. B. nach einer Ablehnung oder bei einem Upgrade von `legacy_upload` auf `qr_mobile` ein neues Foto hochlädt (`CoActorManager::saveUploadedFile` / `saveSignatureDataUrl`), wird die vorherige Datei desselben Präfixes automatisch im Ordner gelöscht (`@unlink`), damit keine Dateileichen entstehen.
* **Auslieferung ausschließlich über `co_actor_media.php`:**
  Kein Bild ist direkt per statischer URL aufrufbar. [`mcp/mcp_neu/web/co_actor_media.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/co_actor_media.php) liefert Bilder und das Einverständnis-Zertifikat nur aus, wenn eine der 3 Bedingungen erfüllt ist:
  1. Der Partner ist im MCP eingeloggt (`$_SESSION['merchant_id'] === $row['merchant_id']`), **oder**
  2. Ein Administrator ist im ACP eingeloggt (`$_SESSION['logged_id'] === 'acp'`), **oder**
  3. Das gültige, zum Datensatz gehörende `qr_token` (`&t=<qr_token>`) wird übergeben (ermöglicht dem Darsteller auf dem Smartphone den Abruf seines eigenen Zertifikats sowie dem ACP auf `acp.erocloud.net` den Subdomain-übergreifenden Zugriff).

---

## 5. 📱 Workflow 1: Smartphone-QR-Code-Verifizierung im Detail (`verify_co_actor.php`)

### Schritt 1: Anlegen oder Auswählen im MCP
* **Für sich selbst („Ich“):** Beim ersten Aufruf von `#/co-actors`, `#/movie-upload` oder `#/movie` legt `CoActorManager::ensureOwnerEntry($merchantId)` automatisch den Datensatz mit `is_owner = 1` an und übernimmt dabei den Klarnamen aus `merchants` sowie den primären Künstlernamen aus `actors`.
* **Für einen Drehpartner:** Der Partner klickt entweder unter `#/co-actors` oder direkt im Film-Formular auf **„+ Neuen Drehpartner anlegen“**, gibt den Anzeigenamen (und optional den Creator-Namen) ein und wählt als Methode **„Per QR-Code am Smartphone (Empfohlen)“**.

### Schritt 2: Lokaler QR-Code & 4-Sekunden-Live-Polling
* Das Quick-Modal (`_quick_modal.php`) rendert sofort über `QrCodeSvg::toDataUri($qrUrl, 5, 3)` den scannbaren QR-Code zur Root-URL:
  `https://erocloud.net/verify_co_actor.php?t=<48-zeichen-hex-token>`
  *(Abwärtskompatibilität: Bereits vor dem Live-Cutover gedruckte QR-Codes mit dem Pfad `/mcp_neu/web/verify_co_actor.php?t=...` werden über `RewriteRule ^mcp_neu/web/(.*)$ /$1 [R=301,L,QSA]` in [`mcp/mcp_neu/web/.htaccess`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/.htaccess) automatisch weitergeleitet.)*
* Solange das QR-Modal auf dem PC/Tablet des Partners geöffnet ist, fragt ein JavaScript-Intervall (`startPollingCoActorStatus`) alle **4 Sekunden** die Route `api.php?route=co-actors/get&id=<id>` ab.
* Sobald der Darsteller den Vorgang auf seinem Smartphone abschließt, springt das Modal beim Partner **ohne Seiten-Reload** auf Grün (*„Verifizierung erfolgreich eingegangen!“*), aktualisiert die Darsteller-Kachel im Hintergrund und hakt den Drehpartner im geöffneten Film-Formular automatisch an.

### Schritt 3: Ausfüllen, Fotografieren & Unterschreiben auf dem Smartphone
Wenn der Darsteller (oder der Partner für sich selbst) den QR-Code scannt, öffnet sich [`verify_co_actor.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/verify_co_actor.php):
1. **Stammdaten & Volljährigkeits-Prüfung:**
   * Eingabe von rechtlichem Vorname, Nachname, Geburtsdatum (wird sowohl im Frontend als auch in PHP exakt auf **$\ge 18$ Jahre am heutigen Stichtag** geprüft), Nationalität, vollständiger Wohnanschrift sowie Ausweisart, Ausweisnummer und Gültigkeitsdatum.
2. **Geführte Kamera-Aufnahme mit Client-seitiger Bildkomprimierung:**
   * **Foto 1 (`face_ref_file`, Pflicht):** Unverdecktes Frontal-Selfie (`accept="image/*" capture="user"`). Freies Gesicht, gute Ausleuchtung, keine Sonnenbrille/Maske – optimiert für den späteren KI-Gesichtsabgleich.
   * **Foto 2 (`id_shot_file`, Pflicht):** Klassischer ID-Shot (`accept="image/*" capture="user"`). Darsteller hält seinen Ausweis gut lesbar direkt neben sein Gesicht.
   * **Foto 3 (`id_front_file`, Pflicht):** Nahaufnahme der Ausweis-Vorderseite (`accept="image/*" capture="environment"`).
   * **Foto 4 (`id_back_file`, Pflicht bei Personalausweis/Führerschein, optional bei Reisepass):** Nahaufnahme der Ausweis-Rückseite (`accept="image/*" capture="environment"`).
   * **Schutz vor Mobilfunk-Upload-Abbrüchen (`compressImageFile`):** Da moderne Smartphones (iPhone Pro / Samsung Ultra) oft 12–48 MP große Fotos (8–20 MB pro Bild) erzeugen, skaliert ein integrierter HTML5-Canvas-Kompressor in `verify_co_actor.php` jedes Foto vor dem Absenden im Browser verlustarm auf maximal `1920px` Kantenlänge (`JPEG 88%`, ca. 350–600 KB). Dadurch dauert der gesamte Upload aller 4 Fotos selbst im mobilen LTE/5G-Netz nur 1–2 Sekunden.
3. **Rechtstext & Resize-sichere Finger-Unterschrift:**
   * Bestätigung der beiden Pflicht-Checkboxen (Volljährigkeit + unwiderrufliche Einverständniserklärung / Model Release gegenüber dem benannten Partner und der EroCloud).
   * **Touch-Signature-Canvas (`#sig-canvas`):** Unterstützt Touch- und Maus-Eingabe mit `devicePixelRatio`-Skalierung.
   * **Wichtiger Mobile-Fix:** Da auf iOS Safari und Android Chrome beim bloßen Scrollen die Browser-Adressleiste ein-/ausblendet und dabei `window.resize` feuert, ignoriert `resizeCanvas()` reine Höhenänderungen (`Math.abs(rect.width - lastCanvasWidth) < 2`) und sichert/restauriert gezeichnete Striche bei echten Breitenänderungen automatisch. So geht eine bereits geleistete Unterschrift beim Scrollen zum Submit-Button niemals verloren.
4. **Speicherung & Zertifikats-Abruf:**
   * Nach dem Absenden speichert `verify_co_actor.php` alle Textfelder AES-verschlüsselt in `co_actors`, legt die Bilder und die PNG-Unterschrift im geschützten Ordner ab, protokolliert `consent_signed_at`, `consent_signer_ip`, `consent_user_agent` sowie `consent_text_hash` und setzt `checked_status = 'under_review'`.
   * Direkt auf dem Erfolgs-Screen kann sich der Darsteller (und ebenso der Partner im MCP) das fertige **digitale Einverständnis-Zertifikat (`co_actor_media.php?id=...&type=certificate`)** ansehen, ausdrucken oder über den Browser-Druckdialog als **PDF speichern**.

---

## 6. 🎬 Workflow 2: Darsteller-Auswahl & Validierung beim Film-Upload / Bearbeiten

In [`mcp/mcp_neu/app/views/movies/_performer_section.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/views/movies/_performer_section.php) (eingebunden in `upload_step1.php` und `edit.php`) ersetzt die neue Sektion **„Wer ist in diesem Film zu sehen? (Darsteller & ID-Check)“** die alte einfache Profil-Dropdown-Auswahl.

### 6.1 Die 3 Auswahl-Modi (`performer_mode`)
1. **`solo_self` („Nur ich bin im Film zu sehen“):**
   * Zeigt direkt darunter die Status-Box des eigenen „Ich“-Eintrags (`Name (Klarname)`).
   * Ist der eigene „Ich“-Eintrag bereits verifiziert (`under_review` oder `verified`), erscheint ein grünes Bestätigungs-Banner.
   * Fehlt der eigene ID-Shot noch (`pending_performer`) oder wurde er abgelehnt (`rejected`), erscheint ein gelb/rotes Warnbanner mit dem Direkt-Button **„Jetzt eigenen ID-Check per QR-Code starten“**, der sofort das QR-Modal öffnet.
2. **`group` („Ich zusammen mit anderen Personen“):**
   * Prüft ebenfalls den eigenen „Ich“-Status und blendet zusätzlich die Multi-Select-Liste aller angelegten Drehpartner (`is_owner = 0`) ein.
   * Jeder Drehpartner zeigt seinen Status-Badge (*Bereit / Verifiziert* vs. *Wartet auf QR-Scan* vs. *Altbestand*).
   * Über den Button **„+ Neuen Drehpartner anlegen“** kann direkt aus dem Film-Formular heraus ein neuer Drehpartner erstellt und per QR-Code verifiziert werden, ohne eingegebene Filmdaten zu verlieren.
3. **`others_only` („Nur andere Personen – ohne mich“):**
   * Blendet die Multi-Select-Liste der weiteren Drehpartner ein. Der eigene „Ich“-Eintrag wird für diesen Film nicht benötigt (`owner_in_content = 0`).

*(Hinweis: Das bisherige Dropdown **„Öffentliches Darsteller-Profil für die Anzeige im Shop (`actor_id`)“** bleibt darunter erhalten, damit der Partner wie gewohnt festlegen kann, unter welchem öffentlichen Bühnenprofil der Film auf den Webseiten gelistet wird.)*

### 6.2 Verbindliche Server-Validierung (`CoActorManager::validateContentPerformers`)
Beim Klick auf *„Weiter zu Schritt 2“* (`MoviesController::saveStep1`) sowie beim Speichern im Film-Editor (`MoviesController::update`) führt `CoActorManager::validateContentPerformers()` folgende Prüfungen durch:
1. **Prüfung des eigenen „Ich“-Eintrags (bei `solo_self` und `group`):**
   * Muss existieren und vollständige Dokumente + Unterschrift besitzen (`checked_status IN ('under_review', 'verified')`). Andernfalls wird das Speichern mit einer klaren Du-Form-Fehlermeldung blockiert.
2. **Prüfung der ausgewählten Drehpartner (bei `group` und `others_only`):**
   * Es muss mindestens **1 weiterer Drehpartner** (`is_owner = 0`) ausgewählt sein.
   * **Jeder** ausgewählte Drehpartner muss dem Partner gehören (`merchant_id`) und vollständige Dokumente + Unterschrift besitzen (`is_ready_for_use === true`). Sobald auch nur ein ausgewählter Drehpartner noch auf den QR-Scan wartet oder abgelehnt wurde, wird das Speichern mit Nennung des jeweiligen Namens blockiert.
3. **Automatischer Plausibilitäts-Abgleich mit den gewählten Film-Kategorien (`category_slave`):**
   * Berechnet die Gesamtzahl der im Film hinterlegten Personen (`$totalPeopleCount = ($ownerInContent ? 1 : 0) + count($validOtherIds)`).
   * Hat der Partner in den Kategorien eine **Mehrpersonen-/Paare-/Gruppen-Kategorie** gewählt (Kategorie-IDs `45` *Frau-Mann*, `14` *Lesben*, `25` *Schwul / Gay*, `12` *Dreier / Threesome*, `8` *Gruppensex / Orgie*, `9` *Gangbang* oder entsprechender Kategoriename), aber nur **1 Person** (`solo_self`) angegeben, blockiert das System mit einem präzisen Hinweis, dass auch alle weiteren beteiligten Drehpartner angegeben und verifiziert werden müssen.
   * Ist speziell *Dreier / Threesome* (`ID 12`), *Gruppensex* (`ID 8`) oder *Gangbang* (`ID 9`) gewählt, müssen mindestens **3 Personen** hinterlegt sein (oder die Kategorie auf ein Einzel-/Paar-Format korrigiert werden).

---

## 7. 🛡️ Workflow 3: Admin-Prüfung & Freigabe-Schutz im ACP

Im Admin Control Panel (`acp/`) ist die Darsteller-Prüfung an **zwei Stellen** verfügbar:

### 7.0 Zentrale Prüf-Übersicht im ACP-Menü (`/Drehpartner-pruefen` $\rightarrow$ [`acp/includes/co_actors_checking.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/co_actors_checking.php))
Unter **„Content Prüfen“ $\rightarrow$ „Drehpartner & ID-Check (X)“** in der linken ACP-Navigation ([`acp/index.php`](file:///c:/xampp/htdocs/EroCloud/acp/index.php)) findest du die zentrale Übersicht aller Darsteller- & „Ich“-Verifizierungen über alle Partner hinweg:
* **Live-Zähler im Menü:** Zeigt in Klammern sofort die Anzahl aller neu eingereichten Verifizierungen (`checked_status = 'under_review'`), auch wenn der Partner noch gar keinen Film zur Prüfung eingereicht hat.
* **Status-Tabs & Volltextsuche:** Schneller Wechsel zwischen *Zur Prüfung offen*, *Verifiziert (OK)*, *Abgelehnt*, *Wartet auf QR-Scan* und *Alle anzeigen* sowie Filterung nach Rolle (*Nur Account-Inhaber („Ich“)* vs. *Nur weitere Drehpartner*) und Suche nach Klarnamen, Creator-Namen, Ausweisnummer oder Partner-ID.
* **Komplett-Ansicht pro Zeile:** Zeigt Partner-Link, entschlüsselte Ausweisdaten, alle 5 anklickbaren Vorschau-Thumbnails (`KI-Gesicht`, `ID-Shot`, `Ausweis V.`, `Ausweis R.`, `Unterschrift`), den Button **`📄 Einverständnis-Zertifikat / PDF`**, alle **verknüpften Filme** (mit Direktlink zu `/Film-pruefen/{id}` bzw. `/Film-bearbeiten/{id}`) sowie die Buttons zum **Bestätigen**, **Ablehnen** (mit Begründung) oder **Zurücksetzen**.

### 7.1 Die ACP-Prüfbox direkt im Film (`CoActorManager::renderAcpMoviePerformersBox`)
Oberhalb der Metadaten rendert `renderAcpMoviePerformersBox($movieId, $merchantId)` eine übersichtliche Prüf-Kachel:
* **Kopfzeile:** Zeigt den vom Partner gewählten Modus (*„Nur Account-Inhaber (Solo)“*, *„Account-Inhaber + Drehpartner“* oder *„Nur andere Darsteller“*) sowie den Gesamtstatus (*Alle Dokumente vollständig* vs. *Prüfung offen / Unvollständig*).
* **Detailzeile pro beteiligter Person (`content_co_actors`):**
  * Rolle (*Account-Inhaber („Ich“)* oder *Drehpartner*), Anzeigename, entschlüsselter Klarname (`Vorname Nachname`), Geburtsdatum + berechnetes Alter in Jahren, Nationalität, Anschrift sowie Ausweistyp, Ausweisnummer und Gültigkeit.
  * Kennzeichnung der Methode: **📱 Frischer QR-Code Smartphone-Check (KI-Ready)** vs. **📄 Manueller Altbestand-Upload (Kein KI-Video-Scan)**.
  * **Vorschau-Thumbnails (Klick öffnet Original in neuem Tab über `co_actor_media.php`):**
    1. `KI-Gesicht` (`face_ref_image`)
    2. `ID-Shot` (`id_shot_image`)
    3. `Ausweis V.` (`id_front_image`)
    4. `Ausweis R.` (`id_back_image`)
    5. `Unterschrift` (`signature_image`)
  * **Zertifikats-Button:** *„📄 Einverständnis-Zertifikat / PDF“* öffnet das druckfertige Model-Release-Dokument inklusive aller eingebetteten Fotos, der Unterschrift und des kryptografischen Audit-Trails.
  * **Direkt-Aktionen pro Person:**
    * Button **`✓ ID & Einverständnis bestätigen`**: Setzt `checked_status = 'verified'`, `checked_by_employee = $_SESSION['acp_username']`, `checked_on = NOW()`.
    * Eingabefeld + Button **`✗ Ablehnen`**: Setzt `checked_status = 'rejected'` und speichert den Ablehnungsgrund in `rejection_reason` (wird dem Partner im MCP rot angezeigt, damit er per QR-Code neue Fotos einreichen kann).

### 7.2 Automatischer Veröffentlichungs-Schutz & Auto-Verify (`movie_checking.php`)
Wenn der Admin im ACP das Hauptformular mit **„Geprüft. Jetzt in der EroCloud veröffentlichen“** (`$status == 'active'`) absendet:
1. **Sperre bei abgelehnten oder unvollständigen Darstellern:**
   Prüft alle mit dem Film verknüpften Personen über `CoActorManager::getContentCoActors('movie', $movie_id)`. Sobald eine Person den Status `'rejected'` hat oder `is_ready_for_use === false` ist, wird die Veröffentlichung des Films verhindert und eine rote Fehlermeldung ausgegeben.
2. **Automatische Verifizierung (`under_review` $\rightarrow$ `verified`):**
   Sind alle verknüpften Darsteller vollständig (`is_ready_for_use === true`) und stehen noch auf `'under_review'`, werden sie beim Veröffentlichen des Films automatisch auf `checked_status = 'verified'` (inkl. Admin-Kürzel und Zeitstempel) gesetzt, sodass der Admin bei einwandfreien Dokumenten nicht jeden Darsteller vorher einzeln anklicken muss.

---

## 8. 🤖 Vorbereitung für zukünftige KI-Gesichtserkennung (Vision-AI Phase 4)

Das System wurde gezielt so entworfen, dass in einer späteren Ausbaustufe ein lokaler Vision-/Face-Recognition-Worker (z. B. `InsightFace` / `ArcFace` auf dem Server) prüfen kann, ob alle in einem Video sichtbaren Gesichter zu den für diesen Film gemeldeten Darstellern gehören:
1. **Dediziertes Referenzbild (`face_ref_image`):**
   Für jeden über `qr_mobile` verifizierten Darsteller liegt im Ordner `{merchant_id}/co_actors/{co_actor_id}/face_ref_*.jpg` ein unverdecktes Frontal-Selfie bereit. Ein Python-/C++-Worker kann daraus direkt das Referenz-Gesichts-Embedding berechnen.
2. **Filterung über `verification_method = 'qr_mobile'`:**
   Da bei älteren `legacy_upload`-Einträgen häufig nur ein abfotografiertes Formular oder ein verdeckter ID-Shot vorliegt, kann der spätere KI-Worker gezielt nach `verification_method = 'qr_mobile' AND face_ref_image != ''` filtern und alte Bestandsfilme ohne `face_ref_image` überspringen (oder bei Bedarf eine Nachverifizierung per QR-Code anfordern).
3. **Verknüpfung über `content_co_actors`:**
   Für jeden Film `movies.id` liefert eine einfache Abfrage auf `content_co_actors WHERE content_type = 'movie' AND content_id = <movie_id>` exakt die Liste aller erlaubten `co_actor_id`-Gesichter für diesen Film.

---

## 9. 📸 Checkliste: Späterer Einbau bei Fotoalben (`photo_albums`)

Datenbank und Backend-Logik in [`CoActorManager.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/CoActorManager.inc.php) unterstützen Fotoalben (`content_type = 'album'`) bereits zu **100 %**:
* Die Spalten `photo_albums.performer_mode` und `photo_albums.owner_in_content` werden in `CoActorManager::ensureTablesExist()` bereits automatisch angelegt.
* `CoActorManager::getContentCoActors('album', $albumId)`, `validateContentPerformers(...)` und `saveContentPerformers('album', $albumId, ...)` sind bereits vollständig generisch für `'album'` implementiert.

Um die Funktion später auch für Fotoalben im MCP & ACP freizuschalten, sind lediglich diese **3 Schritte** nötig:
1. **MCP Views (`mcp/mcp_neu/app/views/photo_albums/`):**
   In der Album-Erstellung und Album-Bearbeitung das bestehende Partial `app/views/movies/_performer_section.php` (inkl. `app/views/co_actors/_quick_modal.php`) einbinden.
2. **MCP Controller (`mcp/mcp_neu/app/src/Modules/PhotoAlbums/PhotoAlbumsController.php`):**
   * Beim Laden des Formulars `CoActorManager::ensureOwnerEntry($merchantId)`, `getCoActorsForMerchant($merchantId)` und `getContentCoActorIds('album', $albumId)` übergeben.
   * Beim Speichern `CoActorManager::validateContentPerformers($merchantId, $performerMode, $coActorIds, [])` aufrufen und bei Erfolg `CoActorManager::saveContentPerformers('album', $albumId, $merchantId, $performerMode, $check['final_co_actor_ids'], (int)$check['owner_in_content'])` ausführen.
3. **ACP Fotoalbum-Prüfung (`acp/includes/photo_album_checking.php` / `photo_album_edit.php`):**
   Analog zu `acp/includes/movie_checking.php` die Prüfbox einbinden (hierfür kann in `CoActorManager` `renderAcpMoviePerformersBox` mit `$contentType = 'album'` aufgerufen werden).

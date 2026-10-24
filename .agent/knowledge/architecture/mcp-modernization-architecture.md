---
name: mcp-modernization-architecture
description: Architektur-Blaupause und Migrationsplan fuer den kompletten Neubau des EroCloud Merchant-Control-Panels (MCP) nach dem Vorbild des KKC (app/ & web/ Trennung, SPA-Fragment-Loading, Pay4Coins-Blau Theme).
usage: 'Wird automatisch herangezogen bei allen Arbeiten am Neubau des MCP unter /mcp/mcp_neu/ (spaeter /mcp/).'
---

# MCP Modernisierungs-Architektur (`app/` & `web/` nach KKC-Vorbild)

> **Kontext-Referenz:** Diese Dokumentation ergänzt `@master.md` sowie `@server-and-system-environment.md` und definiert die verbindliche Softwarearchitektur für den kompletten Neubau des **EroCloud Merchant-Control-Panels (MCP)**.

---

## 1. 🎯 Zielsetzung & Grundprinzipien

1. **Greenfield-Neubau statt Altcode-Flickwerk:**
   Bestehende Legacy-Dateien in `/htdocs/mcp/` werden nicht mehr einzeln umgebaut, sondern parallel in einer modernen Architektur von Grund auf neu entwickelt.
2. **Trennung von `app/` und `web/` (KKC-Architektur):**
   * **`web/` (Öffentliches DocumentRoot):** Enthält ausschließlich den Einstiegspunkt (`index.php`), den AJAX-Dispatcher (`api.php`), den Pay4Coins-SSO-Endpunkt (`login.php`), Medien-Proxys sowie statische Assets (`assets/css`, `assets/js`, `assets/img`, `assets/fonts`).
   * **`app/` (Geschützter Anwendungskern):** Durch `.htaccess` (`Require all denied`) vollständig gegen direkten Browser-Zugriff gesperrt. Enthält Bootstrap, Core-Klassen, Models, Module (Controller + Views).
3. **Dynamisches Nachladen ohne Seiten-Reload (SPA-Architektur):**
   Wie im KKC (`KKC-EroCMS/htdocs/kkc`) lädt ein Klick in der linken Sidebar nicht die gesamte Seite neu, sondern ruft über reines Vanilla-JavaScript (`fetch('api.php?route=...')`) das jeweilige HTML-Fragment ab und rendert es verzögerungsfrei in `#app-content`.
4. **Look & Feel (KKC-Kacheldesign in Pay4Coins-Blau):**
   * Helles, freundliches Standard-Theme (**Light Mode Default** mit `#F8FAFC` Hintergrund und weißen Kacheln `#FFFFFF`, optionaler Dark-Mode-Toggle in der Topbar).
   * Statt des EroCMS-Rots (`#EF4444` im KKC) wird als Primärfarbe das **Pay4Coins-Blau** (`#4297D7` / `#0284C7`, Hover `#0369A1`, Subtle `rgba(66, 151, 215, 0.10)`) verwendet.
5. **100 % Einhaltung von `@master.md` & PHP 8.4.27 Kompatibilität:**
   * `declare(strict_types=1);` und `define('SAFE_INC', 1);`
   * Datenbankzugriff über die zentralen `p4c_*` Wrapper (`p4c_query($sql, __FILE__, __LINE__)`) und zentrales Error-Logging in `/var/www/web1/htdocs/log/.errorlog`.
   * Durchgehend **UTF-8** (keine `ISO-8859-1` Altlasten mehr).
   * Alle UI-Texte ausnahmslos in der persönlichen **Du-Form** (`du`, `dein`, `deine`, `dir`, `dich`).
   * **Kein Studio-Login (`G-...`) mehr:** Es wird ausschließlich der **Pay4Coins-Partner-Login** unterstützt.
6. **Striktes Verbot von Umsatz-, Provisions- und Verkaufszahlen in der neuen EroCloud:**
   * Die Partner sehen ihre echten Umsätze und Abrechnungen direkt in ihrer **EroCMS-Webseite** oder in ihrem **Pay4Coins-Account**.
   * Darum dürfen im gesamten neuen EroCloud MCP (`mcp_neu` / später `mcp`) **keinerlei Umsatzzahlen, Provisionen oder Verkaufszahlen** (wie *Monatsumsatz*, *Provision (Gesamt)*, *Käufe Streaming*, *Käufe Download* oder *Verkäufe*) angezeigt werden – weder auf der Startseite (`#/startseite`), noch unter `#/movies` oder `#/photo-albums` oder bei zukünftigen Erweiterungen.
   * EroCloud dient ausschließlich als Cloud-Content- & Profil-Management-Plattform.

---

## 2. 📂 Ordnerstruktur & URL-Strategie (Entwicklung $\rightarrow$ Go-Live)

### 2.1 Phase A: Parallelbetrieb während der Neuentwicklung (`/htdocs/mcp/mcp_neu/`)
Während der Entwicklungsphase liegt das neue System unter `/htdocs/mcp/mcp_neu/`:
* **Entwicklungs-URL:** `https://erocloud.net/mcp_neu/web/` (bzw. `https://erocloud.net/mcp_neu/web/Startseite`)
* **Session-Sharing:** Da das neue System unter derselben Domain (`erocloud.net`) läuft wie das aktuelle Live-MCP, wird die bestehende PHP-Session (`PHPSESSID` aus `/var/www/web1/tmp`) nahtlos geteilt. Ein im Live-System über Pay4Coins eingeloggter Partner ist unter `/mcp_neu/web/Startseite` sofort angemeldet.

### 2.2 Phase B: Späterer Go-Live (`/htdocs/mcp/`)
Nach Fertigstellung aller Module:
1. Das alte `/htdocs/mcp/` wird entfernt bzw. archiviert.
2. Die Ordner `app/` und `web/` liegen dann direkt unter `/htdocs/mcp/app/` und `/htdocs/mcp/web/`.
3. **Domain-Routing für `https://erocloud.net/Startseite`:**
   * **Option 1 (LiveConfig DocumentRoot):** Das Zielverzeichnis der Domain `erocloud.net` wird in LiveConfig auf `/mcp/web/` gestellt (analog zum KKC). Dadurch liegt `/mcp/app/` physisch außerhalb des Webroots.
   * **Option 2 (`.htaccess` Weiterleitung in `/mcp/.htaccess`):** Falls das DocumentRoot in LiveConfig auf `/mcp/` bleibt, leitet eine zentrale `.htaccess` in `/mcp/` alle Aufrufe transparent auf `web/` weiter, während `/mcp/app/.htaccess` (`Require all denied`) den App-Kern schützt.
4. **Dynamische Pfadauflösung in `app/bootstrap.php`:**
   `bootstrap.php` sucht die zentrale `config.inc.php` dynamisch in den übergeordneten Ebenen, sodass beim späteren Verschieben von `/mcp/mcp_neu/` nach `/mcp/` **keine einzige Codezeile** angepasst werden muss.

### 2.3 Verzeichnisbaum im Detail

```text
mcp_neu/ (später: mcp/)
├── app/
│   ├── .htaccess                  # Require all denied (Kein direkter Web-Zugriff)
│   ├── bootstrap.php              # Lädt config.inc.php, functions.inc.php, Session, Autoloader
│   └── src/
│       ├── Core/
│       │   ├── Autoloader.php     # PSR-4 Autoloader für Namespace App\
│       │   ├── Auth.php           # Pay4Coins SSO & MCP-Session-Prüfung (nur Merchant)
│       │   ├── Controller.php     # Basis-Controller für alle Module
│       │   ├── Csrf.php           # CSRF-Token-Schutz für POST-Requests
│       │   ├── Request.php        # Gekapselter Zugriff auf GET/POST/Route
│       │   ├── Response.php       # HTML-Fragment & JSON-Antworten
│       │   ├── Router.php         # Zentrales Routing für api.php
│       │   └── View.php           # Template-Renderer & XSS-Escaping View::e()
│       ├── Models/
│       │   ├── MerchantModel.php  # Kapselt Partner-Stammdaten, API-Key & Content-Regeln
│       │   ├── ActorModel.php     # Darsteller-Profile & Freischaltstatus
│       │   ├── MovieModel.php     # Filme, Upload-Status & Prüfzustand
│       │   └── PhotoAlbumModel.php# Fotoalben & Bilder
│       └── Modules/
│           ├── Home/
│           │   ├── HomeController.php
│           │   └── views/
│           │       ├── dashboard.php   # Eingeloggtes Partner-Dashboard (Kachel-Layout)
│           │       └── guest.php       # Öffentliche Landingpage (Pay4Coins-Login)
│           ├── Actors/
│           ├── CoActors/               # Drehpartner & ID-Check (Model Release & QR-Verifizierung)
│           ├── Movies/
│           ├── PhotoAlbums/
│           ├── Webmaster/
│           └── Support/
└── web/
    ├── .htaccess                  # RewriteEngine (/Startseite -> index.php), Security-Header
    ├── index.php                  # App-Shell (Sidebar, Topbar, #app-content) bzw. Guest-View
    ├── api.php                    # Zentraler AJAX-Dispatcher für SPA-Navigation
    ├── login.php                  # Pay4Coins Auto-Login (?pid=XXXXXXXXXX)
    ├── verify_co_actor.php        # Smartphone Self-Service für Drehpartner-ID-Check & Touch-Unterschrift (?t=<token>)
    ├── co_actor_media.php         # Geschützter Medien- & PDF-Zertifikats-Proxy für Ausweisfotos & Model-Releases
    └── assets/
        ├── css/
        │   ├── bootstrap.min.css
        │   ├── bootstrap-icons.css
        │   ├── fonts.css
        │   └── theme.css          # KKC-Designsystem adaptiert auf Pay4Coins-Blau (#4297D7)
        ├── js/
        │   ├── bootstrap.bundle.min.js
        │   └── app.js             # Vanilla-JS SPA-Router, Theme-Toggle, Modals, Toasts
        ├── fonts/
        └── img/
```

---

## 3. 🔐 Login-Flow (Pay4Coins Single-Sign-On)

1. **Ausgeloggter Zustand (`/Startseite` $\rightarrow$ `Home/views/guest.php`):**
   * Zeigt das moderne EroCloud-Infoportal im hellen Kachel-Design (in persönlicher **Du-Form**, ohne Studio-Login).
   * Der Login-Button führt zu `https://login.pay4coins.com?ref=erocloud`.
2. **Automatischer Login über Pay4Coins (`web/login.php?pid=XXXXXXXXXX`):**
   * Prüft die 10-stellige `pid` und berechnet `loginHash($pid)` (Browser-Fingerprint + `pid`).
   * Verifiziert den Login via cURL bei `https://api.pay4coins.com/remote/merchant_login.php`.
   * Ruft bei `'ok'` die Stammdaten von `https://api.pay4coins.com/remote/erocloud_merchant_data.php` ab, legt den Partner in `merchants` an bzw. aktualisiert ihn (AES-verschlüsselt mit `AES_KEY`), setzt die MCP-Session (`$_SESSION['logged_id'] = 'mcp'`, `$_SESSION['logged_in_as'] = 'merchant'`, `$_SESSION['merchant_id']`) und leitet auf `Startseite` weiter.
3. **Eingeloggter Zustand (`/Startseite` $\rightarrow$ `web/index.php` + `#/startseite`):**
   * Rendert die App-Shell (Topbar + linke Sidebar mit Darsteller-Profilen, Upload-/Cloud-Menü, Benachrichtigungs-Modal `#modal-notifications`, API-Key-Modal, FAQ/Support/Impressum dynamisch aus der DB) und lädt per JS das Partner-Dashboard (`api.php?route=startseite`).
4. **Direkt-Login über Magic-Link (`web/index.php?ml=<token>` via `EroCloudMagicLink`):**
   * Links aus E-Mails oder Push-Benachrichtigungen loggen den Creator automatisch und sicher ein (`session_regenerate_id(true)`) und leiten sofort per `302` (`Referrer-Policy: no-referrer`) auf die gewünschte Ziel-Ansicht (z. B. `#/movie?id=8482`) weiter. Details siehe `@email-notification-and-magic-link-system.md`.

---

## 4. 🎨 Verbindliche UI-/UX-Vorgaben (Abgenommener Layout-Stand)

1. **Sidebar-Verhalten (Einklappbare Untermenüs):**
   * Alle aufklappbaren Menügruppen (`Content hochladen`, `Content in der Cloud`, `Profile`, `Partnerprogramm`) sind beim initialen Laden der Startseite **geschlossen** (`aria-expanded="false"`, ohne `.show`).
   * Nur wenn gezielt eine Unterseite aufgerufen wird (z. B. `#/movie-upload` oder `#/movies`), öffnet der JS-Router automatisch genau das zugehörige Untermenü und schließt alle übrigen Untermenüs.
   * Alle Hauptmenüpunkte sind mit `white-space: nowrap` formatiert, sodass Einträge mit Zähler-Badge (`Content in der Cloud`) niemals mehrzeilig umbrechen.
   * Im Sidebar-Footer steht zentriert der rechtliche Hinweis (*„EroCloud ist ein Service der CIPA MEDIA S.L.“*), ohne technische Versions- oder PHP-Badges.
2. **Startseite (`#/startseite` Dashboard):**
   * **Obere KPI-Reihe (4 einheitliche Kennzahlen-Kacheln ohne Umsätze):** *Filme in der Cloud*, *Fotoalben in der Cloud*, *Darsteller-Profile* und *Inhalte in Prüfung* (Summe aus Filmen und Fotoalben in Prüfung/Konvertierung).
   * **Linke Hauptspalte:** *Schnellzugriff-Kachel* (4 Direktwahl-Kacheln) sowie Tabelle *Zuletzt hochgeladene Filme*.
   * **Rechte Spalte:** *Deine Darsteller-Profile* (Kompaktübersicht mit Status & Direktbearbeitung, **ohne** Amoredea-Werbekachel) sowie *Account-Status & Content-Richtlinien*.
3. **Content-Übersichten (`#/movies` & `#/photo-albums`):**
   * **Obere 4 KPI-Kacheln:** *Gesamtanzahl*, *Online*, *In Prüfung / Konvertierung* sowie *Abgelehnt / Korrektur nötig* (**niemals** Provisionen oder Umsatzzahlen).
   * **Tabellen-Spalten:** Zeigen ausschließlich Verwaltungs- und Statusdaten (*Status*, *ID*, *FSK16/FSK18 Vorschaubilder*, *Titel & technische Details / Verkaufspreis*, *Darsteller*, *Online ab*, *Aktion*) – **ohne** Spalten für Verkäufe oder Provisionen.
4. **Direkter Draht zu Martin (Testlauf-Feedback & Fehlermeldungen auf `#/startseite`):**
   * Am unteren Ende der Startseite (`Home/views/dashboard.php`) befindet sich die persönliche Feedback- & Fehlermeldungs-Box mit **Martin Zimmermann** (*CEO & Entwickler*, Profilbild `assets/img/ceo-150x150.jpg`) inklusive explizitem **Testlauf-Hinweis** in persönlicher **Du-Form**.
   * **Automatischer Kontext & Isolierung auf Shared-Domain (`erocloud.net`):**
     * Da sich alle Partner die Domain `erocloud.net` teilen, nutzt jeder Partner einen eindeutigen `creator_key` (`ec_merchant_<merchant_id>`), sodass jede Creatorin im Verlauf (`home/feedback-history`) und beim Bestätigen (`home/feedback-mark-read`) ausschließlich ihre eigenen Meldungen und Antworten sieht.
     * Beim Absenden (`home/feedback-send`) werden automatisch alle Partner- und Profil-Metadaten (`merchant_id`, Pay4Coins `partner_id`, `merchant_username`, entschlüsselter Realname & E-Mail, alle aktiven Darsteller-Profile `actors` mit `#ID` sowie verknüpfte Webseiten aus `sites`) als `meta` / `meta_json` übertragen.
     * Zusätzlich wird automatisch das Ende des zentralen EroCloud-Error-Logs (`SOURCEDIR . '/log/.errorlog'`, max. 12 KB / 45 Zeilen) zusammen mit einem Diagnose-Header angehängt.
   * **KKC-Integration (`#/feedback`):**
     * Meldungen landen über `https://remote.erocms.net/feedback.php` (bzw. lokal per Direkt-Include) in derselben KKC-Tabelle `creator_feedback`.
     * Im KKC (`#/feedback`) werden EroCloud-Meldungen mit einem **EroCloud MCP**-Badge und einer detaillierten Partner-/Darsteller-Leiste angezeigt; der Button *„KI-Prompt kopieren (MD)“* generiert automatisch einen auf `c:\xampp\htdocs\EroCloud` (`mcp/mcp_neu/`) zugeschnittenen Analyse-Prompt.
5. **Globale Top-Bar-Schnellsuche (`#global-search-input` / `home/global-search`):**
   * Die Schnellsuche in der oberen Top-Bar durchsucht von jeder Ansicht aus live sowohl **Filme** (`movies`) als auch **Fotoalben** (`photo_albums`) des eingeloggten Partners nach `#ID`, Titel, Darstellername, Datei-/Album-Code oder Status.
   * **Eindeutige Typ-Kennzeichnung:** Jeder Treffer im Live-Dropdown (`#global-search-dropdown`) trägt ein farblich und per Icon klar unterscheidbares Typ-Badge:
     * **Film:** Blaues Badge `<i class="bi bi-film"></i> Film` + `#ID`, Vorschaubild, Filmtitel, Darsteller, Spielzeit/Auflösung und Status.
     * **Fotoalbum:** Violettes Badge `<i class="bi bi-images"></i> Fotoalbum` + `#ID`, Coverbild, Albumtitel, Darsteller, Fotoanzahl und Status.
   * Zusätzlich bietet der Dropdown-Kopf Schnellfilter-Pills (`Alle`, `Filme`, `Fotoalben`) sowie Fußzeilen-Buttons zum direkten Filtern in `#/movies` bzw. `#/photo-albums`.
6. **Drehpartner & ID-Check (`#/co-actors` & Integration in `#/movie-upload` / `#/movie`):**
   * Unter `Profile` $\rightarrow$ **„Drehpartner & ID-Check“** (`#/co-actors`) verwalten Partner ihre eigene Identitäts- & Einverständnis-Verifizierung („Ich“-Eintrag mit `is_owner = 1`) sowie alle weiteren Drehpartner (`co_actors`).
   * **Mobile-First QR-Code-Workflow:** Statt Papierformularen scannt der Drehpartner (oder der Partner für sich selbst) einen lokal per `QrCodeSvg` erzeugten QR-Code, nimmt am Smartphone ein unverdecktes KI-Referenz-Selfie (`face_ref_image`), einen ID-Shot mit Ausweis neben dem Gesicht (`id_shot_image`) sowie Ausweis-Vorder-/Rückseite auf und unterschreibt mit dem Finger direkt auf dem Smartphone-Display (`verify_co_actor.php`).
   * Während das QR-Modal im MCP geöffnet ist, prüft ein **4-Sekunden-Live-Polling** (`co-actors/get`) den Status und schaltet bei erfolgreichem Abschluss am Handy sofort live auf Grün um.
   * Alle Details zu Tabellen (`co_actors`, `content_co_actors`), AES-Verschlüsselung, Kategorie-Plausibilitätsprüfung, Zertifikats-Generierung und ACP-Prüfung sind verbindlich dokumentiert in `@co-actor-verification-and-model-release.md`.
7. **Partnerprogramm / Webmaster-Modul (`App\Modules\Webmaster\WebmasterController`):**
   * Freigeschaltet für die Partner-IDs `['ABPP2HNMDG', 'KJDJEEESAX', 'CCRVWMVD67', '9SN2XTH2TU']`.
   * **Kunden werben (`#/webmaster-ads`):** 4 Reiter (*Websites*, *Kampagnen*, sowie bei eigenen aktiven EroAds-Websites *Eigene Banner verwalten* inkl. Standardgrößen-Prüfung & Grafik-Upload/Ersetzen und *Deine Kunden*).
   * **Werbemittel & Kampagnen (`#/webmaster-ads-domain`, `#/webmaster-new-campaign`, `#/webmaster-edit-campaign`):** Direkte Tracking-URLs (`ADS_URL/l/...`), HTML-Textlinks, XML-API-Endpoints, Bannercodes (`ADS_URL/bc/...`), Ersatzanzeigen (`blank`, `image`, `color`) sowie kampagnenbezogene Auswertungen (Conversions, Klicks & Impressionen inkl. Referer-Detail-Modal).
   * **Alle Banner (`#/webmaster-newads`):** Übersicht aller freigegebenen Werbebanner aktiver EroAds-Websites mit Live-Filterung nach Website, Format und Bannergröße.
   * **Statistiken (`#/webmaster-stats`):** Monats- und Jahres-Auswertungen für Klicks, Impressionen und Conversions inkl. Herkunfts-/Referer-Details (`webmaster/origin-details`).
   * **Darsteller werben (`#/webmaster-advertise-partner`):** Übersicht der persönlichen 3%-Partner-Werbelinks (`https://pay4coins.com/wm/{partner_id}` sowie `https://ad5.tv/wm/{actor_username}`).

---

## 5. 🚀 Go-Live & Cutover-Checkliste (`mcp/` $\rightarrow$ `mcp_neu/`)

Beim Umschalten der Hauptdomain `https://erocloud.net` auf `mcp_neu` sind folgende Punkte verbindlich einzuhalten:
1. **Medien- & Streaming-Endpunkte im Root erhalten:**
   * `/thumb.php`, `/ProfilePicture/{filename}`, `/AlbumPhoto/{file_id}`, `/Player/{file_id}`, `/PlayerPoster/{file_id}` sowie `/erocloud_logo.png` und `/favicon/*` müssen im neuen Web-Root erreichbar bleiben, da sowohl MCP als auch ACP ihre Medien-URLs über `MCP_URL` (`https://erocloud.net`) auflösen.
2. **Rückwärtskompatibilität für QR-Codes & E-Mail-Links:**
   * `.htaccess`-Regel `RewriteRule ^mcp_neu/web/(.*)$ /$1 [R=301,L,QSA]`, damit bereits gedruckte Co-Actor-QR-Codes und versendete Magic-/Unsubscribe-Links nahtlos weiterfunktionieren.
   * Bereinigung des Subpfads `/mcp_neu/web/` in `EroCloudMailer.inc.php`, `movie_converted.inc.php`, `CoActorManager.inc.php`, `EroCloudMagicLink.inc.php` und `SystemTechController.php`.
3. **Produktiv-Schaltung des Mail-Versands:**
   * In `config.inc.php` `define('MAIL_SANDBOX_MODE', false);` setzen.






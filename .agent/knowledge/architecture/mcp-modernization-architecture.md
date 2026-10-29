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

## 2. 📂 Ordnerstruktur & Produktives Domain-Routing (`erocloud.net`)

### 2.1 Aktiver Produktivbetrieb auf `https://erocloud.net` (PHP `8.4.27`)
Seit dem Go-Live zeigt die Hauptdomain **`https://erocloud.net`** (inkl. `www.erocloud.net`) in LiveConfig direkt auf das öffentliche Webroot **`/var/www/web1/htdocs/mcp/mcp_neu/web/`** unter **PHP `8.4.27`** (`php-8.4-opt`):
1. **Physische Kapselung von `app/`:** Der Anwendungskern `/var/www/web1/htdocs/mcp/mcp_neu/app/` liegt physisch **oberhalb** des DocumentRoots und ist zusätzlich per `.htaccess` (`Require all denied`) geschützt.
2. **Archivierung & lokales Fallback:** Der frühere 2016er Altbestand in `/htdocs/mcp/` bleibt als Referenz im Dateisystem erhalten; [`mcp/.htaccess`](file:///c:/xampp/htdocs/EroCloud/mcp/.htaccess) leitet lokale XAMPP-Aufrufe (`/EroCloud/mcp/`) transparent nach `mcp_neu/web/` weiter.
3. **Dynamische Pfadauflösung in `app/bootstrap.php`:** [`bootstrap.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/bootstrap.php) sucht `config.inc.php` dynamisch in den übergeordneten Verzeichnisebenen und unterstützt sowohl `/htdocs/mcp/mcp_neu/app/` als auch ein eventuelles späteres `/htdocs/mcp/app/` ohne Codeänderung.

### 2.2 Verzeichnisbaum im Detail

```text
mcp_neu/
├── app/
│   ├── .htaccess                  # Require all denied (Kein direkter Web-Zugriff)
│   ├── bootstrap.php              # Output-Buffer, config.inc.php, SQL-Mode, Session & 3h-Cookie-Recovery
│   └── src/
│       ├── Core/
│       │   ├── Autoloader.php     # PSR-4 Autoloader für Namespace App\
│       │   ├── Auth.php           # Pay4Coins SSO & MCP-Session-Prüfung (nur Merchant)
│       │   ├── Controller.php     # Basis-Controller für alle Module
│       │   ├── Csrf.php           # CSRF-Token-Schutz für POST-Requests
│       │   ├── Request.php        # Gekapselter Zugriff auf GET/POST/Route
│       │   ├── Response.php       # HTML-Fragment & JSON-Antworten
│       │   ├── Router.php         # Zentrales Routing für api.php (58 Routen)
│       │   └── View.php           # Template-Renderer & XSS-Escaping View::e()
│       ├── Models/
│       │   ├── MerchantModel.php  # Kapselt Partner-Stammdaten, API-Key & Content-Regeln
│       │   ├── ActorModel.php     # Darsteller-Profile & Freischaltstatus
│       │   ├── MovieModel.php     # Filme, Upload-Status & Prüfzustand
│       │   └── PhotoAlbumModel.php# Fotoalben & Bilder
│       └── Modules/
│           ├── Home/              # Dashboard, Schnellsuche, Richtlinien, Notifications & Feedback
│           ├── Actors/            # Darsteller-Profile & FSK16/FSK18 Bildupload
│           ├── CoActors/          # Drehpartner & ID-Check (Model Release & QR-Verifizierung)
│           ├── Movies/            # Film-Upload (3 Schritte) & Filmverwaltung
│           ├── PhotoAlbums/       # Fotoalben-Upload & Verwaltung
│           ├── Webmaster/         # EroAds Partnerprogramm, Banner & Kampagnen
│           └── Preview/           # FAQ & Info-Ansichten
└── web/                           # LiveConfig DocumentRoot für https://erocloud.net (PHP 8.4.27)
    ├── .htaccess                  # CORS, 301 /mcp_neu/web/* Redirect, Medien- & Legacy-SPA-Rewrites
    ├── index.php                  # App-Shell (Sidebar, Topbar, #app-content), Logout & Magic-Link-Entry
    ├── api.php                    # Zentraler AJAX-Dispatcher für SPA-Navigation
    ├── login.php                  # Pay4Coins SSO Auto-Login (?pid=XXXXXXXXXX) mit sauberem Account-Switch
    ├── media.php                  # Geschützter Medien- & Video-Streaming-Proxy (/Player, /ProfilePicture, /AlbumPhoto, /PlayerPoster)
    ├── thumb.php                  # GD-Szenen-Thumbnail-Endpunkt (?movie_id=...&thumb_number=1..12&w=...)
    ├── unsubscribe.php            # Öffentlicher 1-Klick-E-Mail-Abmelde- & Präferenz-Endpunkt
    ├── verify_co_actor.php        # Smartphone Self-Service für Drehpartner-ID-Check & Touch-Unterschrift (?t=<token>)
    ├── co_actor_media.php         # Geschützter Medien- & PDF-Zertifikats-Proxy für Ausweisfotos & Model-Releases
    ├── favicon.ico                # Root-Favicon
    └── assets/
        ├── css/                   # bootstrap.min.css, bootstrap-icons.css, fonts.css, theme.css (#4297D7)
        ├── js/                    # bootstrap.bundle.min.js, app.js (SPA-Router, Modals, Global-Dialog)
        ├── fonts/
        └── img/                   # Logos, Favicons, movie_poster_nopic.jpg, movie_quality_buttons/ (sd..6k)
```

---

## 3. 🔐 Login-Flow, Session-Recovery & Logout (Pay4Coins Single-Sign-On)

1. **Ausgeloggter Zustand – Öffentliche Landingpage (`/`) bzw. Login-Redirect (`/Startseite`):**
   * **Root-URL `https://erocloud.net/`:** Nicht eingeloggte Besucher sehen die öffentliche Präsentations-Landingpage [`Home/views/guest.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/Home/views/guest.php) (gerendert aus `web/index.php` via `View::render('Home', 'guest')`). Sie präsentiert EroCloud als zentrales Content-Backend von **Pay4Coins** für Creator mit eigener **EroCMS**-Website (Sektionen: Hero mit Content-Flow-Grafik, *Das Prinzip*, *So funktioniert's*, *Funktionen*, *EroCMS*, *Pay4Coins*, *FAQ*, CTA, Footer). Das alte Webmaster-/Syndication-Thema wird bewusst **nicht** mehr beworben.
   * **Primärer Funnel:** Alle „Website starten“-CTAs führen auf `https://pay4coins.com/de/Betreiben-Sie-Ihre-ganz-private-Erotikwebsite-mit-EroCMS`; der Login auf `LOGIN_URL?ref=erocloud`. *Partner werden* (`merchant.pay4coins.com/Partner-werden`), Impressum und Datenschutz (`pay4coins.com/de/datenschutz`) sind im Footer verlinkt.
   * **SEO / KI / Social Sharing:** Title, Meta-Description, Canonical, hreflang, Open Graph + Twitter Card (Share-Bild `web/assets/img/og-erocloud.jpg`, 1200×630), JSON-LD `@graph` (Organization, WebSite, WebPage, WebApplication, SoftwareApplication EroCMS, FAQPage – FAQ-Inhalte aus einem einzigen `$faqItems`-Array) sowie die statischen Begleitdateien `web/robots.txt`, `web/sitemap.xml` und `web/llms.txt`. Eigene Assets: `web/assets/css/landing.css`, `web/assets/js/landing.js` (Theme-Toggle mit Key `ec-mcp-theme`, Scroll-Spy, Progressive-Reveal nur unterhalb des Viewports).
   * **Alle anderen Pfade (z. B. `/Startseite`):** Leiten Gäste weiterhin direkt auf `https://login.pay4coins.com?ref=erocloud` weiter.
2. **Automatischer SSO-Login & sauberer Partner-Wechsel (`web/login.php?pid=XXXXXXXXXX`):**
   * Definiert vor dem Laden von `bootstrap.php` verbindlich `MCP_SKIP_AUTO_SESSION_RECOVERY`, damit keine vorherige Partner-Session während des SSO-Handshakes wiederbelebt wird.
   * Prüft die 10-stellige `pid`, berechnet `loginHash($pid)` (Browser-Fingerprint + `pid`) und verifiziert den Login via cURL bei `https://api.pay4coins.com/remote/merchant_login.php`.
   * Ruft bei `'ok'` die Stammdaten von `https://api.pay4coins.com/remote/erocloud_merchant_data.php` ab und legt den Partner in `merchants` an bzw. aktualisiert ihn (AES-verschlüsselt mit `AES_KEY`).
   * **Account-Switch-Schutz:** Vor dem Setzen der neuen Session verwirft `login.php` alle vorherigen MCP-Session-Keys (`merchant_id`, `merchant_username`, `mcp_impersonated_by_admin`, `erocloud_stream_cache`) und rotiert die Session-ID (`session_regenerate_id(true)`). In [`Merchant::__construct()`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/merchant.inc.php) hat eine explizit übergebene `$merchant_id` bzw. 10-stellige `partner_id` immer Vorrang vor `$_SESSION['merchant_id']`.
   * Synchronisiert das HMAC-signierte 3-Stunden-Cookie `erocloud_mcp_auth` (`mcp_neu_sync_auth_cookie()`) sowie `erocloud` und leitet auf `/Startseite` weiter. Schlägt der SSO-Handshake fehl, werden etwaige Alt-Cookies/Sessions bereinigt und an `LOGIN_URL?logout&ref=erocloud` abgemeldet.
3. **180-Minuten Session-Recovery (`mcp_neu_ensure_session()` in `app/bootstrap.php`):**
   * Da ältere Endpunkte (`api/`) denselben `TEMP_DIR` mit kürzerer Standard-GC-Lifetime nutzen können, stellt das mit `AES_KEY` HMAC-SHA256-signierte Cookie `erocloud_mcp_auth` (Gültigkeit `10800s` = 180 Min. analog `MCP_SESSION_DURATION`, inkl. optionalem Admin-Impersonation-Kontext) eine vorzeitig vom GC gelöschte `/tmp`-Session nahtlos wieder her.
   * **Kein Stale-Auto-Login über das 3-Monats-Cookie `erocloud`:** Da Pay4Coins beim Logout `merchants.login_hash` nicht in der DB leert, erfolgt nach Ablauf von `erocloud_mcp_auth` oder nach einem Logout bewusst **keine** stille Wiederbelebung aus dem `erocloud`-Cookie.
4. **Vollständiger Dual-Cookie-Logout (`web/index.php?logout`):**
   * Rekonstruiert bei bereits abgelaufener `/tmp`-Session den Partner-/Impersonation-Kontext aus dem signierten `erocloud_mcp_auth`-Cookie (für korrektes Partner-Historien-Logging bzw. Admin-Rücksprung nach `https://acp.erocloud.net/Startseite#/merchants`).
   * [`mcp_neu_clear_auth_cookie()`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/bootstrap.php) löscht **beide** Cookies (`erocloud_mcp_auth` und `erocloud`) sowohl für `.erocloud.net` als auch Host-only, invalidiert das `PHPSESSID`-Cookie, zerstört die PHP-Session und leitet Partner zuverlässig an `https://login.pay4coins.com/?logout&ref=erocloud` weiter.
5. **Eingeloggter Zustand (`/Startseite` $\rightarrow$ `web/index.php` + `#/startseite`):**
   * Rendert die App-Shell (Topbar + linke Sidebar mit Darsteller-Profilen, Upload-/Cloud-Menü, Benachrichtigungs-Modal `#modal-notifications`, API-Key-Modal, FAQ/Support/Impressum dynamisch aus der DB) und lädt per JS das Partner-Dashboard (`api.php?route=startseite`).
6. **Direkt-Login über Magic-Link (`web/index.php?ml=<token>` via `EroCloudMagicLink`):**
   * Links aus E-Mails, Push-Benachrichtigungen oder der ACP-Session-Übernahme (`acp_impersonate`) überspringen die Auto-Recovery, loggen den Creator automatisch und sicher ein (`session_regenerate_id(true)`) und leiten sofort per `302` (`Referrer-Policy: no-referrer`) auf die gewünschte Ziel-Ansicht (z. B. `#/movie?id=8482`) weiter. Details siehe `@email-notification-and-magic-link-system.md`.

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
8. **Globales Dialog-Modal-System (`#ec-global-modal`) & Favicons:**
   * In [`mcp/mcp_neu/web/index.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/index.php) und [`mcp/mcp_neu/web/assets/js/app.js`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/assets/js/app.js) steht analog zum ACP das globale, Promise-basierte Dialog-System (`App.dialog`, `App.confirm`, `App.prompt`, `App.alert`) mit den Varianten `info`, `success`, `warning` und `danger` zur Verfügung; sämtliche nativen `window.confirm()`-Aufrufe (Film löschen, Foto-Album löschen, Einzelfoto entfernen, Drehpartner entfernen, Kampagne löschen) sind dadurch abgelöst.
   * Aus `erocloud_logo.png` generierte Favicons (`favicon.ico`, `favicon-16x16.png`, `favicon-32x32.png`, `favicon-96x96.png`, `apple-touch-icon.png`) sind in `mcp/mcp_neu/web/assets/img/` und `mcp/mcp_neu/web/favicon.ico` abgelegt und im `<head>` von `index.php` und `verify_co_actor.php` eingebunden.

---

## 5. 🚀 Produktiv-Umstellung (Go-Live auf `erocloud.net` & PHP 8.4.27)

Die Hauptdomain **`https://erocloud.net`** zeigt in LiveConfig analog zum ACP direkt auf **`/htdocs/mcp/mcp_neu/web/`** und läuft produktiv unter **PHP `8.4.27`** (`php-8.4-opt`):
1. **Physische Trennung von `app/` & `web/`:**
   * `mcp/mcp_neu/web/` ist das öffentliche DocumentRoot (`https://erocloud.net/Startseite`), während `mcp/mcp_neu/app/` (`../app`) physisch **oberhalb** des DocumentRoots liegt und aus dem Web nicht erreichbar ist.
   * Der Altbestand in `mcp/` bleibt unangetastet im Dateisystem als Archiv erhalten (inkl. `mcp/.htaccess` als lokales XAMPP-/Fallback-Routing).
2. **Autarke Medien- & Streaming-Endpunkte in `mcp/mcp_neu/web/`:**
   * [`mcp/mcp_neu/web/thumb.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/thumb.php): Liefert Szenen-Thumbnails (`1..12`) skaliert über GD mit Session-Lock-Freigabe und HTTP-304-Caching aus.
   * [`mcp/mcp_neu/web/media.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/media.php): Zentraler, geschützter Partner-Medien- & Streaming-Proxy für `/ProfilePicture/{filename}`, `/AlbumPhoto/{file_id}`, `/Player/{file_id}` (`VideoStream` mit Session-Stream-Cache), `/PlayerPoster/{file_id}` (inkl. `SD`..`6K` Wasserzeichen) und `/amoredea/RedirectContent/{content_id}_{month}`.
   * `/erocloud_logo.png` sowie `/favicon/*` und `/images/favicons/*` werden über [`mcp/mcp_neu/web/.htaccess`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/.htaccess) direkt aus `assets/img/` ausgeliefert; [`api/movie_downloader.php`](file:///c:/xampp/htdocs/EroCloud/api/movie_downloader.php), [`api/photo_album_downloader.php`](file:///c:/xampp/htdocs/EroCloud/api/photo_album_downloader.php) und [`api/messenger_image_info.php`](file:///c:/xampp/htdocs/EroCloud/api/messenger_image_info.php) arbeiten mit autarkem Inline-CSS ohne veraltete `mcp/fw/jquery-ui`-Abhängigkeiten.
3. **100 % Rückwärtskompatibilität für QR-Codes, E-Mail-Links & Lesezeichen:**
   * `RewriteRule ^mcp_neu/web/(.*)$ /$1 [R=301,L,QSA]` in [`mcp/mcp_neu/web/.htaccess`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/.htaccess) stellt sicher, dass alle bereits generierten Drehpartner-QR-Codes (`/mcp_neu/web/verify_co_actor.php?t=...`), gespeicherten Lesezeichen sowie versendeten Magic- und Unsubscribe-Links nahtlos auf das Root-Level weitergeleitet werden.
   * Alte MCP-Direktlinks (`/Movies`, `/Movie-Upload`, `/Photo-Albums`, `/Actors`, `/CoActors`, `/Webmaster`, `/faq` usw.) leiten automatisch per `[R=302,L,NE]` auf die jeweilige SPA-Route (`/Startseite#/...`) weiter.
   * Alle URL-Generatoren ([`CoActorManager`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/CoActorManager.inc.php), [`EroCloudMagicLink`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMagicLink.inc.php), [`EroCloudMailer`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMailer.inc.php), [`EroCloudNotificationCenter`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudNotificationCenter.inc.php)) erzeugen saubere Root-URLs auf `https://erocloud.net/...`.
4. **Produktiver E-Mail-Versand & 7-stufiger Deep-Logic-Audit (PHP 8.0 / 8.4.27):**
   * In [`config.inc.php`](file:///c:/xampp/htdocs/EroCloud/config.inc.php) steht `define('MAIL_SANDBOX_MODE', false);`, sodass System-Benachrichtigungen produktiv an die Creator zugestellt werden (sofern im ACP unter `#/system-settings` kein manueller DB-Sandbox-Override aktiv ist).
   * [`mcp/mcp_neu/app/bootstrap.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/bootstrap.php) kapselt das Bootstrapping mit `ob_start()`, initialisiert global `$settings` und setzt `SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';` für 100 % Kompatibilität mit 2016er Tabellen-Defaults (`'0000-00-00 00:00:00'`), inklusive automatischer Performance-Index-Prüfung in [`HomeController::ensureSearchIndexes()`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/Home/HomeController.php).
   * Alle `61` involvierten PHP-Dateien (`php -l`), `316` `p4c_query()`-Aufrufe (`__FILE__, __LINE__`), `58` registrierten Router-Endpunkte, Live-View-Renders und Inline-JS-Handler über `30` Views wurden automatisiert ohne Fehler, Warnungen oder Deprecations auditiert.


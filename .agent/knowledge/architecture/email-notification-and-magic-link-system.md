---
name: email-notification-and-magic-link-system
description: Vollstaendige Architektur-Dokumentation fuer das globale EroCloud E-Mail-System (PHPMailer v7.1.1, EroCloudMailer, Postfix/DNS/DKIM/DMARC), das zentrale Pay4Coins-Impressum in der DB, das Auto-Login Deep-Link-System (EroCloudMagicLink) und das Multi-Channel Notification & Preference Center (EroCloudNotificationCenter).
usage: 'Wird automatisch herangezogen bei allen Arbeiten an E-Mails, Push-Benachrichtigungen, Newsletter-Funktionen, Auto-Login-Links (Magic Links), 1-Klick-Abmeldungen oder dem zentralen Impressum.'
---

# 📧 Globales E-Mail-, Notification- & Magic-Link-System (`EroCloud`)

> **Kontext-Referenz:** Diese Dokumentation ergänzt `@master.md`, `@server-and-system-environment.md`, `@mcp-modernization-architecture.md` und `@video-management.md`. Sie beschreibt verbindlich, wie E-Mails, Push-Benachrichtigungen, Auto-Login-Links und das zentrale Impressum in EroCloud technisch aufgebaut sind und im Code genutzt werden.

---

## 1. 🖥️ Mailserver-, Postfix- & DNS-Konfiguration (`mail.erocloud.net`)

Damit System-E-Mails und Newsletter bei großen Providern (Gmail, T-Online, GMX, Web.de, Apple iCloud, Outlook) zuverlässig im Hauptpostfach landen, ist der Server `t199.nlserver.net` (`91.184.62.199`) wie folgt konfiguriert:

### 1.1 LiveConfig Mailserver (Postfix & Dovecot)
* **Servername (HELO / Banner):** `mail.erocloud.net` (sowohl für Postfix SMTP als auch Dovecot IMAP/POP3).
* **SSL/TLS-Zertifikat:** Gültiges Let's Encrypt **RSA (2048 Bit)** Zertifikat für `mail.erocloud.net` (TLS 1.2 / TLS 1.3 aktiv).
* **Datenschutz / IP-Maskierung:** Die Option **„IP-Adresse des SMTP-Absenders verstecken“** ist in LiveConfig unter *Serververwaltung $\rightarrow$ E-Mail $\rightarrow$ Postfix* aktiviert, sodass im `Received`-Header ausgehender E-Mails keine lokalen oder internen Client-IPs auftauchen.
* **System-Postfach:** `noreply@erocloud.net` (genutzt für authentifizierten SMTP-Versand über Port `587` STARTTLS).

### 1.2 DNS-Einträge für `erocloud.net` (`ns1.nldns.net`)
| Typ | Name / Host | Wert / Ziel | Zweck |
| :--- | :--- | :--- | :--- |
| **A** | `mail.erocloud.net` | `91.184.62.199` | Aufgelöster Mailserver-Host |
| **MX** | `erocloud.net` | `10 mail.erocloud.net.` | Zuständiger Mail-Exchanger |
| **TXT (SPF)** | `erocloud.net` | `v=spf1 a mx ip4:91.184.62.199 ~all` | Autorisiert `91.184.62.199` für den Versand |
| **TXT (DKIM)** | `mail._domainkey.erocloud.net` | `v=DKIM1; k=rsa; p=MIIBIjANBg...` (2048-Bit RSA) | Kryptografische Signatur jeder ausgehenden E-Mail |
| **TXT (DMARC)** | `_dmarc.erocloud.net` | `v=DMARC1; p=quarantine; adkim=r; aspf=r;` | Domain-Alignment & Schutz vor Spoofing |

### 1.3 Best-Practice: Warum KEIN abweichender `Reply-To`-Header gesetzt wird
* EroCloud ist ein Projekt von **Pay4Coins** (`CIPA Media S.L.U.`). Die offizielle Support-Adresse lautet **`support@pay4coins.com`** (es existiert kein Postfach `support@erocloud.net`).
* **Anti-Spam-Regel:** Bei automatischen System-Mails von `noreply@erocloud.net` wird **kein** `Reply-To: support@pay4coins.com` im technischen E-Mail-Header mitgesendet (`MAIL_REPLY_TO_ADDRESS = ''`). Ein Domain-Mismatch zwischen `From` (`@erocloud.net`) und `Reply-To` (`@pay4coins.com`) führt bei strengen Spamfiltern zu Punktabzug.
* Stattdessen steht im E-Mail-Footer ein klarer Hinweis in persönlicher **Du-Form**, dass auf diese automatisch generierte System-Nachricht nicht direkt geantwortet werden kann, inklusive klickbarem `mailto:support@pay4coins.com`-Link für Rückfragen.

---

## 2. 📦 Globale Mailer-Architektur (`PHPMailer v7.1.1` & `EroCloudMailer`)

### 2.1 Beteiligte Dateien
* **[`config.inc.php`](file:///c:/xampp/htdocs/EroCloud/config.inc.php#L34-L54):**
  Zentrale SMTP-Konstanten (`MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_SMTP_SECURE`, `MAIL_SMTP_USER`, `MAIL_SMTP_PASS`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`) sowie der globale Sandbox-Schutz (`MAIL_SANDBOX_MODE`, `MAIL_SANDBOX_RECIPIENT`).
* **[`includes/klassen/phpmailer/src/`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/phpmailer/src/PHPMailer.php):**
  Lokal gehosteter **PHPMailer `v7.1.1`** (`PHPMailer.php`, `SMTP.php`, `Exception.php`, `POP3.php`, `OAuth.php`, `DSNConfigurator.php`) inkl. deutscher Sprachdatei [`includes/klassen/phpmailer/language/phpmailer.lang-de.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/phpmailer/language/phpmailer.lang-de.php). Vollständig kompatibel mit **PHP 8.0.30** und **PHP 8.4.27**.
* **[`includes/klassen/EroCloudMailer.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMailer.inc.php):**
  Zentrale Mailer-Klasse für das gesamte Projekt (`ACP`, `MCP`, `mcp_neu`, `API`, `Cronjobs`).
* **[`includes/klassen/sendSMTPmail.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/sendSMTPmail.inc.php):**
  Bindet automatisch `EroCloudMagicLink.inc.php`, `EroCloudNotificationCenter.inc.php` und `EroCloudMailer.inc.php` ein und stellt zusätzlich die abwärtskompatible Wrapper-Klasse `SendSMTPmail` bereit.
* **[`includes/mail_templates/layout.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/mail_templates/layout.inc.php):**
  Globales, responsives HTML- & Plaintext-Masterlayout im **Pay4Coins-Blau (`#0284C7`)** mit No-Reply-Hinweis, 1-Klick-Abmelde-Button und dynamischem Pay4Coins-Impressum aus der Datenbank.
* **[`includes/mail_templates/movie_converted.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/mail_templates/movie_converted.inc.php):**
  Template für die Benachrichtigung nach abgeschlossener Video-Konvertierung & Thumbnail-Generierung.

### 2.2 Anti-Spam Inhalts-Richtlinie (Option A – 100 % neutral ohne Filmtitel)
* Da Creator-Videos häufig explizite/pornografische Begriffe im Titel tragen, die Content-Spamfilter (SpamAssassin, T-Online, GMX/Web.de) triggern und die Domain-Reputation von `erocloud.net` gefährden würden, gilt verbindlich **Option A**:
* **Weder im E-Mail-Betreff noch im E-Mail-Body dürfen ungefilterte Filmtitel oder Adult-Begriffe stehen!**
* Die Zuordnung erfolgt eindeutig und seriös über:
  * **Betreff:** `Dein Video (#8482) ist fertig konvertiert – Vorschaubilder stehen bereit`
  * **Info-Box im Body:** `Film-ID (#8482)`, `Hochgeladen am`, `Laufzeit & Qualität`, `10 Szenen-Thumbnails generiert`
  * **Direkt-Login-Button:** *„Vorschaubilder auswählen & Film bearbeiten →“*

### 2.3 Sandbox- / Test-Schutz (`MAIL_SANDBOX_MODE`)
In [`config.inc.php`](file:///c:/xampp/htdocs/EroCloud/config.inc.php#L52-L53) steuert die Konstante `MAIL_SANDBOX_MODE` (bzw. ein optionaler Override `mail_sandbox_mode` in der Tabelle `settings` über ACP `#/system-settings`), ob E-Mails produktiv an echte Creator rausgehen:
```php
define('MAIL_SANDBOX_MODE',      false); // false = Produktiver Live-Betrieb aktiv (echte Creator erhalten Mails)
define('MAIL_SANDBOX_RECIPIENT', 'erocms@gmail.com');
```
* Seit dem MCP-Go-Live steht `MAIL_SANDBOX_MODE` produktiv auf `false`. Falls im ACP unter `#/system-settings` vorübergehend der Sandbox-Modus aktiviert wird (`mail_sandbox_mode = '1'`), fängt `EroCloudMailer::send()` jede ausgehende E-Mail ab und leitet sie ausschließlich an `erocms@gmail.com` um.

---

## 3. 🏛️ Zentrales Pay4Coins-Impressum in der Datenbank (`settings`)

Da jede kommerzielle / geschäftliche E-Mail einen vollständigen Impressums-Footer benötigt und das Impressum auch im MCP angezeigt wird, sind die Impressumsdaten von **Pay4Coins (`https://pay4coins.com/de/impressum`)** zentral in der Datenbank-Tabelle `settings` gespeichert:

* **Datenbank-Keys in `settings` (`type` / `value`):**
  * `imprint_company_name` (`CIPA Media S.L.U.`)
  * `imprint_address_street` (`Av. Emilio Luque Moreno 19 Oficina 6`)
  * `imprint_address_city` (`ES-38300 La Orotava (S/C de Tenerife)`)
  * `imprint_register_court` (`Registro Mercantil de Santa Cruz de Tenerife`)
  * `imprint_register_details` (`Tomo: 3368, Folio: 43, Sección: 8, Hoja: TF-54807, Inscripción: 8`)
  * `imprint_vat_id` (`CIF: B76659168`)
  * `imprint_ceo` (`Christina Toepel`)
  * `imprint_support_email` (`support@pay4coins.com`)
  * `imprint_phone` (`+34 616 571 860`)
  * `imprint_whatsapp_url` (`https://wa.me/34616571860`)
  * `imprint_website_url` (`https://pay4coins.com`)
  * `imprint_external_url` (`https://pay4coins.com/de/impressum`)
* **Bearbeitung im ACP:**
  Über die Seite **`/Einstellungen`** (`?mod=system_settings` $\rightarrow$ [`acp/includes/system_settings.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/system_settings.php)) können alle Impressums- und Support-Daten jederzeit ohne Code-Änderung bearbeitet werden; dort lässt sich auch per 1-Klick eine Test-E-Mail an `erocms@gmail.com` auslösen.
* **Automatische Verwendung:**
  [`EroCloudMailer::getImprintData()`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMailer.inc.php#L47-L95) liefert die Daten an alle E-Mail-Footer ([`layout.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/mail_templates/layout.inc.php)), die öffentliche 1-Klick-Abmeldeseite sowie die Modals `#modal-imprint` und `#modal-support` im neuen MCP ([`mcp/mcp_neu/web/index.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/index.php)).

---

## 4. 🔐 Sicheres Auto-Login & Deep-Link-System ([`EroCloudMagicLink`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMagicLink.inc.php))

Damit ein Creator beim Klick auf einen Button in einer E-Mail oder Push-Benachrichtigung **automatisch eingeloggt** wird und **direkt in der Ziel-Ansicht** (z. B. `#/movie?id=8482`) landet, existiert die globale Klasse [`includes/klassen/EroCloudMagicLink.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMagicLink.inc.php).

### 4.1 Sicherheits-Architektur (Defense-in-Depth)
1. **Encrypt-then-MAC (`AES-256-CBC` + `HMAC-SHA256`):**
   * Das Payload (`merchant_id`, `target_route`, `channel`, `context`, `expires_at`, `max_uses`, `128-Bit Nonce`) wird mit `AES-256-CBC` verschlüsselt und mit `HMAC-SHA256` (`hash_equals()` in konstanter Zeit) signiert.
2. **Kein Klartext-Token in der Datenbank (`auth_magic_links`):**
   * In der Tabelle `auth_magic_links` wird ausschließlich der **SHA-256-Hash** (`token_hash`) des Tokens gespeichert, niemals das Klartext-Token.
3. **Striktes Route-Whitelisting ([`sanitizeTargetRoute()`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMagicLink.inc.php#L77-L139)):**
   * Erlaubt ausschließlich validierte interne MCP-Routen (`startseite`, `movies`, `movie?id=...`, `movie-upload`, `photo-albums`, `photo-album?id=...`, `actors`, `actor?id=...`, `preview`, `webmaster`). Open-Redirect- oder XSS-Angriffe sind ausgeschlossen.
4. **Schutz gegen E-Mail-Scanner & Link-Prefetcher ([`isPrefetchOrScannerRequest()`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMagicLink.inc.php#L254-L295)):**
   * `HEAD`-Requests, Prefetch-Header und bekannte Scanner-Bots (SafeLinks, Gmail Image Proxy, WhatsApp/Telegram Preview) werden abgefangen, ohne den Nutzungszähler (`use_count`) zu erhöhen oder eine Session zu starten.
   * Standardmäßig gilt für E-Mail-Links `ttlSeconds = 259200` (72 Stunden) und `maxUses = 5`, damit ein Wechsel vom Mail-App-In-App-Browser zu Safari/Chrome problemlos funktioniert.
5. **Session-Fixation-Schutz & URL-Bereinigung ([`handleRequest()`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMagicLink.inc.php#L302-L469)):**
   * Beim Aufruf von `index.php?ml=<token>` rotiert das System die Session-ID (`session_regenerate_id(true)`), setzt das `erocloud_mcp_auth`-Cookie, protokolliert den Login in `merchant_history` und leitet sofort per `302` (`Referrer-Policy: no-referrer`) auf `index.php#/<zielroute>` weiter. Dadurch verschwindet `?ml=...` sofort aus der Browser-Adresszeile.

### 4.2 Code-Beispiele: Wie nutze ich `EroCloudMagicLink` künftig im Code?

```php
// 1. Direkt-Login-Link zu einem bestimmten Film (z.B. für E-Mail oder Push)
$movieUrl = EroCloudMagicLink::forMovie(
    merchantId: $merchantId,
    movieId: $movieId,
    channel: 'email',             // 'email' | 'push' | 'system'
    context: 'movie_converted',   // Erscheint im Audit-Log (merchant_history)
    ttlSeconds: EroCloudMagicLink::DEFAULT_TTL_EMAIL // 72h (259200s)
);

// 2. Direkt-Login-Link zu einem Foto-Album (z.B. für Push-Benachrichtigung mit 24h Gültigkeit)
$albumUrl = EroCloudMagicLink::forPhotoAlbum(
    merchantId: $merchantId,
    albumId: $albumId,
    channel: 'push',
    context: 'album_approved',
    ttlSeconds: EroCloudMagicLink::DEFAULT_TTL_PUSH // 24h (86400s)
);

// 3. Direkt-Login-Link zu einem Darsteller-Profil
$actorUrl = EroCloudMagicLink::forActor($merchantId, $actorId, 'email', 'actor_reviewed');

// 4. Beliebige interne MCP-Route mit eigenen Parametern
$customUrl = EroCloudMagicLink::createUrl(
    merchantId: $merchantId,
    targetRoute: 'movies',
    channel: 'email',
    context: 'newsletter_cta',
    ttlSeconds: 604800, // 7 Tage
    maxUses: 10
);

// 5. Alle offenen Magic-Links eines Creators sofort widerrufen (z.B. bei Sicherheits-Reset)
EroCloudMagicLink::revokeAllForMerchant($merchantId);
```

---

## 5. 🔔 Zentrales Notification & Preference Center ([`EroCloudNotificationCenter`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudNotificationCenter.inc.php))

Statt für neue E-Mail- oder Push-Themen immer wieder neue Spalten an `merchants` anzuhängen, verwaltet [`includes/klassen/EroCloudNotificationCenter.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudNotificationCenter.inc.php) alle Benachrichtigungs-Wünsche, Versand-Logs und Push-Geräte zentral über **3 Datenbank-Tabellen**.

### 5.1 Die 3 Datenbank-Tabellen
1. **`notification_preferences`** (`UNIQUE(merchant_id, channel, event_key)`):
   * Speichert für jeden Creator (`merchant_id`), Kanal (`'email'`, `'push'`) und jedes Thema (`event_key`), ob Benachrichtigungen erwünscht sind (`is_enabled = 1` oder `0`).
   * Speichert zusätzlich `updated_via` (`'mcp_modal'`, `'upload_checkbox'`, `'one_click_unsubscribe'`, `'rfc8058_one_click'`, `'unsubscribe_page_matrix'`) sowie `updated_ip` und `updated_at` als **DSGVO-Nachweis**.
2. **`notification_log`**:
   * Protokolliert jede gesendete Nachricht mit `merchant_id`, `channel`, `event_key`, `reference_type` (z. B. `'movie'`, `'photo_album'`, `'actor'`, `'newsletter'`), `reference_id`, `recipient`, `status` (`'sent'`, `'sandbox'`, `'failed'`), `message_id` und `created_at`.
   * Dient gleichzeitig als kanalübergreifender **Duplikat-Schutz** (`hasBeenSent()`).
3. **`notification_push_subscriptions`**:
   * Speichert die WebPush-/Mobile-Push-Endpunkte (`endpoint_hash`, `endpoint`, `p256dh_key`, `auth_token`, `device_label`, `user_agent`, `is_active`) pro `merchant_id`.

### 5.2 Aktuell registrierte Themen (`event_key`) im Katalog
Definierbar und jederzeit ohne DB-Migration erweiterbar in [`EroCloudNotificationCenter::getEventCatalog()`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudNotificationCenter.inc.php#L30-L68):
* **`movie_converted`** (*Video fertig konvertiert & Vorschaubilder bereit*) – Default: `email = 1`, `push = 0` (Push im UI noch deaktiviert mit Hinweis *„In Kürze“*)
* **`content_reviewed`** (*Prüfungs-Status für Filme, Foto-Alben & Darsteller*) – Default: `email = 1`, `push = 0`
* **`newsletter`** (*EroCloud Creator-News & Updates / Newsletter*) – Default: `email = 1`, `push = 0`

### 5.3 Wo kann der Creator seine Einstellungen verwalten?
1. **Im neuen MCP (`#modal-notifications` in [`mcp/mcp_neu/web/index.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/index.php#L411-L486)):**
   * Aufrufbar über das **Glocken-Icon** in der Topbar, über das **Partner-Dropdown** (*„Benachrichtigungen“*) oder über die linke **Sidebar** (*„Benachrichtigungen“*).
   * Speichert per AJAX (`api.php?route=home/save-notifications` $\rightarrow$ [`HomeController::saveNotifications()`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/Home/HomeController.php#L822-L881)) alle E-Mail- und Push-Schalter und synchronisiert automatisch einen evtl. geöffneten Video-Upload.
2. **Beim Video-Upload in Schritt 2 (`#movie-notify-on-convert` in [`upload.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Modules/Movies/views/upload.php#L507-L519)):**
   * Zeigt den aktuellen Status für `movie_converted` als Schalter an und merkt sich Änderungen sofort für den aktuellen Film (`movies.notify_on_convert`) sowie für künftige Uploads (`notification_preferences`).
3. **Über den öffentlichen 1-Klick-Abmeldelink in jeder E-Mail (OHNE Login-Zwang!):**
   * Route: `https://erocloud.net/mcp_neu/web/api.php?route=email-unsubscribe&token=...` (wird in [`Router::dispatch()`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/app/src/Core/Router.php#L26-L33) **vor** der Login-Prüfung ausgeführt).
   * Unterstützt automatische **RFC 8058 One-Click POSTs** von Gmail/Apple Mail sowie im Browser ein komplettes **1-Klick-Präferenz-Center**, in dem der Creator ohne Login einzelne Themen an-/abwählen oder auf *„Alle abbestellen“* klicken kann.

---

## 6. 🧰 Code-Referenz (Cheat-Sheet für zukünftige Entwicklungen)

### A) Prüfen, ob ein Creator eine E-Mail oder Push-Nachricht erhalten möchte
```php
// Prüfen für E-Mail:
if (EroCloudNotificationCenter::isEnabled($merchantId, EroCloudNotificationCenter::EVENT_CONTENT_REVIEWED, EroCloudNotificationCenter::CHANNEL_EMAIL)) {
    // Creator möchte Prüfungs-Status per E-Mail erhalten
}

// Prüfen für Newsletter:
if (EroCloudNotificationCenter::isEnabled($merchantId, EroCloudNotificationCenter::EVENT_NEWSLETTER, EroCloudNotificationCenter::CHANNEL_EMAIL)) {
    // Creator hat den Newsletter abonniert
}

// Prüfen für Push:
if (EroCloudNotificationCenter::isEnabled($merchantId, EroCloudNotificationCenter::EVENT_MOVIE_CONVERTED, EroCloudNotificationCenter::CHANNEL_PUSH)) {
    // Creator möchte Push-Benachrichtigung erhalten
}
```

### B) Prüfen, ob eine Benachrichtigung für ein Objekt schon gesendet wurde & Versand loggen
```php
// 1. Duplikat-Prüfung (z.B. wurde für Foto-Album #512 schon eine Freigabe-Mail gesendet?)
if (!EroCloudNotificationCenter::hasBeenSent($merchantId, EroCloudNotificationCenter::EVENT_CONTENT_REVIEWED, 'email', 'photo_album', 512)) {

    // 2. Auto-Login-Link & Abmelde-Link erzeugen
    $albumUrl = EroCloudMagicLink::forPhotoAlbum($merchantId, 512, 'email', 'album_approved');
    $unsubUrl = EroCloudMailer::getUnsubscribeUrl($merchantId, EroCloudNotificationCenter::EVENT_CONTENT_REVIEWED);

    // 3. E-Mail im EroCloud-Layout rendern & senden
    $rendered = EroCloudMailer::renderTemplate('custom_template', [
        'merchant_id'     => $merchantId,
        'creator_name'    => $creatorName,
        'intended_email'  => $creatorEmail,
        'subject'         => 'Dein Foto-Album (#512) wurde geprüft',
        'body_html'       => '<p>Hallo ' . htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8') . ', ...</p>',
        'unsubscribe_url' => $unsubUrl,
    ]);

    $mailer = new EroCloudMailer();
    $ok = $mailer->send($creatorEmail, $creatorName, $rendered['subject'], $rendered['html'], $rendered['text'], [
        'unsubscribe_url' => $unsubUrl,
    ]);

    // 4. Im zentralen notification_log protokollieren
    EroCloudNotificationCenter::logDelivery(
        merchantId: $merchantId,
        eventKey: EroCloudNotificationCenter::EVENT_CONTENT_REVIEWED,
        channel: 'email',
        referenceType: 'photo_album',
        referenceId: 512,
        recipient: $mailer->effectiveRecipient,
        status: $ok ? 'sent' : 'failed',
        messageId: $mailer->lastMessageId,
        errorMessage: $mailer->lastError
    );
}
```

### C) Neues Benachrichtigungs-Thema in Zukunft hinzufügen
Um ein neues Thema (z. B. `payout_ready` oder `actor_verified`) einzuführen:
1. In [`includes/klassen/EroCloudNotificationCenter.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudNotificationCenter.inc.php#L30-L68) eine neue Konstante und einen Eintrag im Array von `getEventCatalog()` ergänzen.
2. **Fertig!** Das neue Thema erscheint sofort automatisch im MCP-Modal (`#modal-notifications`), auf der öffentlichen 1-Klick-Abmeldeseite und wird in `merchant_notification_prefs` gespeichert – ganz ohne Datenbank-Migration.

---

## 7. Aktive Strategische E-Mail-Auslöser & ACP-Verwaltung (`#/system-settings`)

### A) Die 10 aktiven System-E-Mail-Auslöser (in 3 operativen Kategorien + 1 Newsletter-Kategorie)
1. **Kategorie `movie_converted` (*Video fertig konvertiert & Vorschaubilder bereit*):**
   - `movie_converted`: Ausgelöst durch [`cronjobs/convert_movie.php`](file:///c:/xampp/htdocs/EroCloud/cronjobs/convert_movie.php) nach FFmpeg-Konvertierung & Erstellung der 10 Szenen-Thumbnails $\rightarrow$ Magic-Link nach `#/movie?id={ID}`.
2. **Kategorie `content_reviewed` (*Prüfungs-Status: Filme, Foto-Alben & Darsteller*):**
   - `movie_approved` & `movie_rejected`: Ausgelöst im ACP durch [`MoviesCheckingController.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/MoviesChecking/MoviesCheckingController.php) $\rightarrow$ Magic-Link nach `#/movie?id={ID}`.
   - `album_approved` & `album_rejected`: Ausgelöst im ACP durch [`PhotoAlbumsCheckingController.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/PhotoAlbumsChecking/PhotoAlbumsCheckingController.php) $\rightarrow$ Magic-Link nach `#/photo-album?id={ID}`.
   - `actor_approved` & `actor_rejected`: Ausgelöst im ACP durch [`ActorsController.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/Actors/ActorsController.php) bei Statuswechsel auf `active` oder `blocked` $\rightarrow$ Magic-Link nach `#/actor?id={ID}`.
   - *(Hinweis: Für Werbebanner-Prüfungen im `AffiliateController` wird bewusst keine E-Mail versendet.)*
3. **Kategorie `co_actor_status` (*Drehpartner & 18+ ID-Check Status*):**
   - `co_actor_submitted`: Ausgelöst durch [`mcp/mcp_neu/web/verify_co_actor.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/verify_co_actor.php), sobald ein Drehpartner den Smartphone-QR-Check abschließt $\rightarrow$ Magic-Link nach `#/co-actors`.
   - `co_actor_verified` & `co_actor_rejected`: Ausgelöst im ACP durch [`CoActorManager::acpReviewCoActor()`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/CoActorManager.inc.php) / [`CoActorsCheckingController.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/CoActorsChecking/CoActorsCheckingController.php) $\rightarrow$ Magic-Link nach `#/co-actors`.

### B) ACP E-Mail-Zentrale (`acp/acp_neu` $\rightarrow$ `#/system-settings`)
In [`SystemTechController.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/SystemTech/SystemTechController.php) und [`views/settings.php`](file:///c:/xampp/htdocs/EroCloud/acp/acp_neu/app/src/Modules/SystemTech/views/settings.php) stehen 4 spezialisierte Tabs bereit:
1. **Tab 1 – Postfach & SMTP-Einstellungen (+ Vorlagen-Labor):**
   - Einsehen & Ändern von SMTP-Host, Port, Verschlüsselung (`STARTTLS`/`SSL`), SMTP-Benutzer, SMTP-Passwort (AES-verschlüsselt in `settings.mail_smtp_pass_enc`), Absendername, Absenderadresse sowie **Sandbox-Testmodus An/Aus** und **Sandbox-Empfängeradresse**.
   - Live-Vorschau (HTML & Plaintext) und echter SMTP-Testversand für alle 10 Vorlagen-Varianten.
2. **Tab 2 – Für was werden Mails versendet? (Auslöser & Kategorien):**
   - Übersicht der 4 Opt-Out-Kategorien inkl. Live-Zähler aktiver vs. abgemeldeter Partner (`EroCloudNotificationCenter::getOptInStats()`) sowie vollständige Tabelle aller 10 Auslöser mit 1-Klick-Vorschau.
3. **Tab 3 – Versand-Tracking (Wer / Wann / Für was):**
   - Durchsuch- und filterbares Protokoll (`notification_log`) mit Anzeige von Datum/Uhrzeit, Partner (`MID #`, Benutzername, Partner-ID), Auslöser + verlinktem Referenz-Objekt (`Film #`, `Foto-Album #`, `Darsteller #`, `Drehpartner #`), ursprünglichem Ziel-Empfänger vs. Sandbox-Empfänger, Betreff, Status-Badge und Modal-Ansicht (**„E-Mail ansehen“**) des exakt versendeten HTML-Inhalts.
4. **Tab 4 – Impressum, Support & Server:**
   - Pflege der Pay4Coins-Impressumsdaten für den E-Mail-Footer sowie PHP-Laufzeitdiagnose.


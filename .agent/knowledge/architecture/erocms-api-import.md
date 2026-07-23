---
name: erocms-api-import
description: Dokumentation über die API-Schnittstelle zur Anbindung und zum Import von Filmen in externe EroCMS-Seiten.
usage: 'Referenz für Schnittstellen-Entwicklung, API-Debugging und Fehleranalyse bei Filmimporten.'
---

# 🔌 EroCMS API & Film-Import (EroCloud API)

Dieses Dokument beschreibt die technische Funktionsweise der API-Schnittstelle von EroCloud, über die sich externe EroCMS-Webseiten per API-Key mit der Cloud verbinden, Creator-Videos (Filme) abfragen, importieren und diese für das Endkunden-Streaming sowie den Download bereitstellen.

---

## 1. 🛡️ Authentifizierung & API-Key-Validierung
Jede Anfrage an die API muss durch einen API-Key legitimiert werden. Dieser Key ist einem bestimmten Creator (Merchant) zugeordnet.

* **Speicherung in der DB:** Der API-Key ist in der Spalte `api_key` der Tabelle `merchants` verschlüsselt hinterlegt und wird per `AES_DECRYPT(api_key, 'AES_KEY')` abgeglichen.
* **Verifikations-Funktion:** `check_api_key_exists($api_key)` in [api/index.php](file:///c:/xampp/htdocs/EroCloud/api/index.php).
* **Datenbank-Schutz (Session-Caching):**
  Um bei aufeinanderfolgenden API-Abrufen nicht bei jedem Request die MySQL-Datenbank belasten zu müssen, wird ein erfolgreicher Key-Abgleich in der PHP-Session (`$_SESSION['api_key']`) gespeichert. Bei weiteren Anfragen wird direkt gegen die Session validiert.

---

## 2. 🗂️ API-Endpunkte und Datenfluss

Der API-Zentralrouter liegt in [api/index.php](file:///c:/xampp/htdocs/EroCloud/api/index.php). Folgende Endpunkte steuern den Import und Abruf:

### A. API-Key prüfen (`check_api_key`)
Prüft die grundsätzliche Gültigkeit des Schlüssels und registriert die Verbindung in der Session.
* **Request:**
  `GET https://api.erocloud.net/index.php?api_name=check_api_key&api_key={API_KEY}&domain={DOMAIN}`
* **Verarbeitung:** Ruft [api/includes/check_api_key.inc.php](file:///c:/xampp/htdocs/EroCloud/api/includes/check_api_key.inc.php) auf.
* **Response (XML):**
  ```xml
  <api>
      <version>1.0</version>
      <status>ok</status>
  </api>
  ```

### B. Filmliste abrufen (`get_movies`)
Gibt eine Liste aller online verfügbaren Filme eines bestimmten Darstellers (`actor_id`) aus.
* **Request:**
  `GET https://api.erocloud.net/index.php?api_name=get_movies&api_key={API_KEY}&domain={DOMAIN}&actor_id={ACTOR_ID}`
* **Verarbeitung:** Ruft [api/includes/get_movies.inc.php](file:///c:/xampp/htdocs/EroCloud/api/includes/get_movies.inc.php) auf.
* **Caching-Mechanismus (50-Minuten-Cache):**
  * Das JSON-Ergebnis der Query auf `movies_online` wird in einer temporären Datei unter `api/temp/get_movies_by_actor_id/{ACTOR_ID}.tmp` abgelegt.
  * Existiert diese Datei und ist jünger als 50 Minuten, wird sie ohne Datenbankzugriff direkt geladen.
* **Domain-Whitelisting (Sichtbarkeitsprüfung):**
  * Es wird geprüft, ob die Spalte `visible_for_website` auf `public` steht oder exakt mit der anfragenden `domain` übereinstimmt.
  * Falls die Domain keine Berechtigung für das Video besitzt, wird der Status in der Ausgabe auf `deleted` maskiert.
* **Response (XML):**
  ```xml
  <api>
      <version>1.0</version>
      <number_of_movies>1</number_of_movies>
      <movies>
          <item>
              <movie_id>12345</movie_id>
              <actor_id>98</actor_id>
              <checksum>abc123xyz...</checksum>
              <online_since>2026-07-02T11:00:00+02:00</online_since>
              <status>online</status>
              <domain>public</domain>
          </item>
      </movies>
  </api>
  ```

### C. Filmdetails importieren (`get_movie`)
Gibt alle vertriebs- und SEO-relevanten Details für ein spezifisches Video (`movie_id`) aus.
* **Request:**
  `GET https://api.erocloud.net/index.php?api_name=get_movie&api_key={API_KEY}&domain={DOMAIN}&movie_id={MOVIE_ID}`
* **Verarbeitung:** Ruft [api/includes/get_movie.inc.php](file:///c:/xampp/htdocs/EroCloud/api/includes/get_movie.inc.php) auf.
* **Caching-Mechanismus (50-Minuten-Cache & 7-Stunden-Cleanup):**
  * Das Ergebnis wird in `api/temp/get_movie/{MOVIE_ID}.tmp` gecached (Gültigkeit: 50 Minuten).
  * Cache-Dateien, die älter als 7 Stunden sind, werden beim nächsten Aufruf physisch per `unlink()` gelöscht.
* **Ausgelesene Datenfelder:**
  * **Grunddaten:** Titel (`title`), Beschreibung (`description`), Sprache (`movie_language`), Kategorien (`category_slave`).
  * **Technisches:** Auflösung (`resolution`), Länge in Zeichenkette (`playtime_string`) und Sekunden (`playtime_seconds`), Checksumme (`checksum`).
  * **Bilder:** Direkte Links zum FSK16- und FSK18-Poster.
  * **Finanzen & Vertrieb:** Coin-Preise pro Sekunde (`amount_second`), Webmaster-Provision (`amount_webmaster`), Download-Erlaubnis (`as_download`) und Download-Preis (`amount_download`).
  * **SEO:** Meta-Title, Meta-Description und die SEO-URL (`seo_url`).
* **Response (XML):** Enthält alle oben genannten Eigenschaften im XML-Format zur Weiterverarbeitung im EroCMS.

---

## 3. 🎥 Auslieferung (Streaming und Download)
Die physischen Videodateien verbleiben permanent in der EroCloud-Infrastruktur und werden nicht an das EroCMS übertragen. Beim Kauf oder Aufruf eines Videos generiert das EroCMS eine Token-Anfrage für den Kunden:

* **Endpunkt:**
  `GET https://api.erocloud.net/get_movie_url.php?user_streaming_key={KEY}&movie_id={MOVIE_ID}&buy_as={streaming|download}`
* **Verarbeitung in [api/get_movie_url.php](file:///c:/xampp/htdocs/EroCloud/api/get_movie_url.php):**
  1. Validiert den `user_streaming_key` gegen die Tabelle `movies_access`.
  2. Generiert ein zufälliges Einweg-Token (`access_token`) mit einer Gültigkeit von **30 Minuten** (`access_token_datetime`).
  3. Liefert die geschützten Links zum Cloud-Player oder Cloud-Downloader zurück.
* **Response (XML):**
  ```xml
  <api>
      <version>1.0</version>
      <!-- Für Downloads -->
      <movie_url>https://api.erocloud.net/Downloader/{user_streaming_key}/{movie_id}/{access_token}</movie_url>
      <!-- Für Streaming -->
      <movie_url>https://api.erocloud.net/Player/{user_streaming_key}/{movie_id}/{access_token}</movie_url>
      <movie_url_mobil_mp4>https://api.erocloud.net/Player/{user_streaming_key}/{movie_id}/{access_token}&amp;mobile=mp4</movie_url_mobil_mp4>
      <poster_url_fsk18>https://api.erocloud.net/PlayerPoster/FSK18/{movie_id}</poster_url_fsk18>
      <width>1920</width>
      <height>1080</height>
  </api>
  ```

---

## 4. 🛠️ Hilfsfunktionen und Ausgabeformat
* **Ausgabeformat:** Die Hilfsfunktion `print_xml($api)` in [includes/functions.inc.php](file:///c:/xampp/htdocs/EroCloud/includes/functions.inc.php) konvertiert das `$api`-Array rekursiv in ein standardkonformes XML-Dokument und sendet den entsprechenden HTTP-Header.

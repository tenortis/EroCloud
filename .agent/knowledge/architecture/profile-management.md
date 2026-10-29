---
name: profile-management
description: Detaillierte Dokumentation über den Lebenszyklus und die Verwaltung von Darsteller-Profilen (Actors), Merchants und Usern.
usage: 'Referenz für Entwicklungen an der Darsteller- und Merchant-Verwaltung.'
---

# 👤 Profil-Verwaltung (Actors, Merchants, Users)

Dieses Dokument beschreibt im Detail alle Prozesse rund um die Verwaltung von Darsteller-Profilen (Actors), Händlern (Merchants) und Benutzern (Users) in EroCloud.

---

## 1. 🏗️ Profil-Erstellung (Darsteller anlegen)
Die Erstellung neuer Darsteller-Profile erfolgt im **Merchant Control Panel (MCP)** durch den Händler:
* **Pfad (MCP)**: `mcp/includes/new_actor.php` oder `mcp/includes/co_actor_create.php` (für Co-Darsteller).
* **Erstellungs-Limit**: Die maximale Anzahl an Profilen, die ein Merchant anlegen darf, ist über das Feld `minimum_number_actor_profiles` in der Tabelle `merchants` beschränkt (wird vom Administrator im ACP konfiguriert).
* **Initialer Status**: Neu angelegte Profile haben standardmäßig den Status `inactive` und müssen vom Admin im ACP geprüft und aktiviert werden.
* **Gruppenzuordnung**: Nach der Erstellung wird das Profil automatisch der ersten verfügbaren Gruppe des Händlers (`group_actors`) zugeordnet.

---

## 2. 📝 Profil-Bearbeitung & Stammdaten
Sowohl der Händler (im MCP) als auch der Administrator (im ACP) können Darstellerprofile bearbeiten. 

### Erfasste Profil-Stammdaten:
1. **Physische Merkmale**:
   * Geschlecht (`gender`)
   * Alter (`age`)
   * Sternzeichen (`star_sign`)
   * Körpergröße (`body_height`)
   * Körpergewicht (`body_weight`)
   * Körbchengröße (`cup_size`)
   * Behaarung/Rasiert (`shaven`)
   * Aussehen/Typ (`look`)
2. **Persönliche Merkmale & Präferenzen**:
   * Familienstand (`marital_status`)
   * Sexuelle Orientierung (`sexual_orientation`)
   * Suche nach (`looking_for`)
   * Interessen (`interests`)
   * Sexuelle Vorlieben (`sexual_preferences`)
   * Beschreibungstext (`about_me`)
3. **Anzeigebereich (`is_displayed_as`)**:
   * `only_chat_actor` (Chat & Webcam)
   * `only_upload_actor` (Filme & Fotoalben)
   * `chat_upload_actor` (Beides)

---

## 3. 🏷️ Einkategorisierung (Darsteller kategorisieren)
Die Kategorisierung erfolgt ausschließlich im **ACP** (`acp/includes/actor.php`). Die Zuordnungen werden als kommagetrennte Liste von Kategorie-Schlüsseln im Feld `actor_categories` der Tabelle `actors` gespeichert.

### Kategorie-Gruppen:
* **Hauptkategorie** (`category_group = 'main'`): z.B. *Porno* oder *Fetisch*.
* **Gewicht** (`category_group = 'weight'`)
* **Hauttyp** (`category_group = 'skin_color'`)
* **Haarfarbe** (`category_group = 'hair_color'`)
* **Geschlecht** (`category_group = 'gender'`)
* **Alter** (`category_group = 'age'`)
* **Körpereigenschaften** (`category_group = 'body'`)

---

## 4. 📞 Hotline- & Finanzeinstellungen
Diese Einstellungen werden ausschließlich vom Administrator im **ACP** vorgenommen:
* **0900er-Rufnummer (Deutschland)**:
  * Aktivierungsstatus (`erocall_number_de_status`)
  * Rufnummer und DDI (`erocall_number_de`, `erocall_number_de_ddi`)
  * Zielrufnummern für Festnetz (`erocall_number_de_dest_landline`) und Mobilfunk (`erocall_number_de_dest_mobile`)
  * Minutentarif für den Anrufer (`erocall_number_de_rate`): Standardmäßig `1,99 EUR` oder `2,99 EUR`.
* **Trinkgeld/Tribut (`obolus_type`)**:
  * `tip` ("Trinkgeld senden" - primär Erotik/Porno)
  * `tribute` ("Tribut zollen" - primär Fetisch/Domina)
* **Plattform-Provisionen**:
  * Prozentsatz für Content-Verkäufe (`amoredea_provision_content`) - Standard: `50%`
  * Prozentsatz für Obolus/Trinkgeld (`amoredea_provision_obolus`) - Standard: `70%`

---

## 5. 🔄 Besitzerwechsel (Darsteller neu zuordnen)
Ein Administrator kann einen Darsteller einem anderen Merchant zuordnen (Besitzerwechsel). Da hierbei physische Dateien verschoben werden müssen, ist dieser Prozess asynchron aufgebaut:

1. **Zuweisung im ACP**: Der Administrator wählt im Profil des Darstellers einen neuen Händler aus.
2. **Datenbank-Eintrag**: Es wird ein Eintrag in der Tabelle `actor_reassign` erstellt.
3. **Cronjob-Verarbeitung**: Der Cronjob `cronjobs/actor_reassign.php` läuft alle 5 Minuten und führt folgende Schritte aus:
   * **Data-Mapping**: Aktualisierung des `merchant_id`-Fremdschlüssels in allen verknüpften Tabellen:
     * `actors`, `actor_cams`, `actor_member_info`, `chat_messages` (sowohl gesendete als auch empfangene Nachrichten), `chat_messages_history`, `group_actors`, `messenger_sync` und `revenue_webcam`.
     * Registrierung aller Filme und Fotoalben des Darstellers in `actor_reassign_files`.
   * **Files-Mapping (Datei-Transfer)**:
     * Verschieben der Film-Verzeichnisse (`copyDir` und Löschen der alten Ordner) und Hinzufügen der neuen `merchant_id` in `movies`/`movies_online`.
     * Verschieben der Fotoalben-Verzeichnisse und Hinzufügen der neuen `merchant_id` in `photo_albums`/`photo_albums_online`/`photo_albums_photos`.
     * Verschieben des Profilbild-Verzeichnisses unter `PROFILE_IMAGE_PATH`.

---

## 6. 🔒 Status-Änderungen: Sperren & Löschen
Ein Profil kann vier verschiedene Statuswerte haben (`status`):
1. **`active`**: Profil ist online und voll funktionsfähig.
2. **`inactive`**: Profil ist offline (z.B. neu erstellt oder temporär deaktiviert).
3. **`blocked` (Gesperrt)**: Profil ist gesperrt.
4. **`deleted` (Gelöscht)**: Profil ist gelöscht.

### Auswirkungen des Status `deleted`:
* Alle zugeordneten Filme des Darstellers werden in den Tabellen `movies` und `movies_online` auf den Status `deleted` gesetzt.
* Alle zugeordneten Fotoalben des Darstellers werden in den Tabellen `photo_albums` und `photo_albums_online` auf den Status `deleted` gesetzt.
* Der Zugriff des Händlers im MCP auf dieses Profil wird blockiert (Weiterleitung zur Übersichtsseite).

---

## 7. 🔑 Login & Händler/User wechseln (Impersonation)

### Händler-Login (MCP v2.0 auf `erocloud.net`)
Der Login der Partner ins produktive MCP (`mcp/mcp_neu/web/`) läuft über eine Single-Sign-On-Verbindung (SSO) mit Pay4Coins:
* **Ablauf**: Der Partner wird von `login.pay4coins.com` bzw. `merchant.pay4coins.com` mit seiner 10-stelligen Partner-ID an `https://erocloud.net/login.php?pid=[PARTNER_ID]` ([`mcp/mcp_neu/web/login.php`](file:///c:/xampp/htdocs/EroCloud/mcp/mcp_neu/web/login.php)) weitergeleitet.
* **Sicherheitsprüfung & sauberer Account-Wechsel**: Der Login-Hash (`loginHash($pid)`) wird über die Pay4Coins-API validiert (`Pay4Coins_API_URL . '/remote/merchant_login.php'`). `login.php` überspringt die Auto-Session-Recovery (`MCP_SKIP_AUTO_SESSION_RECOVERY`), verwirft etwaige vorherige Partner-Sessions, rotiert die `session_id()` (`session_regenerate_id(true)`) und setzt das HMAC-signierte 3-Stunden-Cookie `erocloud_mcp_auth` sowie `erocloud` frisch.
* **Datenabgleich & `Merchant::__construct()`**: Bei erfolgreichem Login werden die Stammdaten (E-Mail, Name, Geburtsdatum) via XML von Pay4Coins abgefragt und AES-verschlüsselt in `merchants` aktualisiert. In [`includes/klassen/merchant.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/merchant.inc.php) hat eine explizit übergebene `$merchant_id` bzw. 10-stellige `partner_id` immer Vorrang vor `$_SESSION['merchant_id']`, sodass es weder beim Partner-Wechsel noch bei ACP-Prüfungen zu Account-Vermischungen kommt.
* **ACP-Impersonation (1-Klick-Übernahme)**: Administratoren (`employee_rule === 'write'`) können sich im ACP unter `acp.erocloud.net/#/merchants` per Klick auf *„Session übernehmen“* (`POST ?route=merchants/impersonate`) über einen 120s gültigen [`EroCloudMagicLink`](file:///c:/xampp/htdocs/EroCloud/includes/klassen/EroCloudMagicLink.inc.php) direkt in die MCP-Ansicht eines Partners schalten, ohne Pay4Coins-Passwort und ohne die Partner-Login-Historie zu verfälschen.

### User-Login (Frontend)
Normale Benutzer (User/Members) registrieren und authentifizieren sich direkt über die jeweiligen Frontend-Shops (zu finden in der Tabelle `sites` unter der entsprechenden `p4c_shop_id`). Das ACP listet die Benutzerdaten (`amount_coins`, `online_device`, etc.) und Chatverläufe auf.

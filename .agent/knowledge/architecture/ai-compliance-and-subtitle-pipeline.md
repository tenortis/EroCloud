---
name: ai-compliance-and-subtitle-pipeline
description: Architektur- und Phasenkonzept für die lokale KI-Pipeline (Audio-Transkription, Spracherkennung, Untertitel-Generierung, BRAM-Textprüfung und spätere Video-Frame-Analyse) auf dem EroCloud-Server.
usage: 'Wird automatisch angewendet bei Planung, Entwicklung und Erweiterung der lokalen KI-Moderation, Whisper-Untertitel-Erstellung, BRAM-Compliance-Prüfung und des ACP-KI-Testlabors.'
---

# 🤖 Lokale KI-Compliance- & Untertitel-Pipeline (Konzept & Architektur)

> **Kontext-Referenzen:** Dieses Dokument ergänzt die allgemeinen Projektvorgaben aus `@master.md`, die Dokumentations-Richtlinien aus `@documentation-standards.md` sowie den Video-Lebenszyklus aus `@video-management.md`.
> **Status:** Lebendes Architektur- & Planungsdokument (wird im Projektverlauf fortlaufend verfeinert und erweitert).

Dieses Dokument beschreibt das Gesamtkonzept sowie die schrittweise Umsetzung (Best Practice) für eine lokal auf dem EroCloud-Server betriebene KI-Pipeline zur:
1. **Audio-Auswertung & Untertitel-Generierung** (Spracherkennung, `.vtt`-Untertitel, Volltext-Transkription des gesprochenen Wortes im Video).
2. **Text-Compliance-Prüfung (Visa/Mastercard BRAM & Rechtliches)** für Creator-Eingaben (Titel, Beschreibung, SEO) sowie das gesprochene Audio-Transkript.
3. **Visuellen Video-Inhaltsanalyse (Vision-KI)** über intelligente Frame-Collagen (Sprite-Grids) in einer nachgelagerten Ausbaustufe.

---

## 1. 🖥️ Server-Hardware & Technische Rahmenbedingungen

Die Architektur ist exakt auf die physische Hardware des EroCloud-Hauptservers abgestimmt:

* **Server-Modell:** Dell Inc. PowerEdge R720 (`t199.nlserver.net`)
* **Betriebssystem:** Debian GNU/Linux 10 (Buster)
* **Prozessor (CPU):** 2x Intel(R) Xeon(R) CPU E5-2690 v2 @ 3.00GHz (**20 Kerne / 40 Threads**, 25 MB Cache, Ivy-Bridge-EP Architektur)
* **Grafikkarte (GPU):** Keine dedizierte GPU verbaut (reine CPU-Inferenz)
* **Arbeitsspeicher (RAM):** 62,86 GiB DDR3 ECC (Grundauslastung ~5 %, bis zu **32 GB RAM** für lokale KI-Prozesse verfügbar)
* **Speicherplatz (`/var/www/web1/cloud_storage`):** 5,20 TiB (aktuell ~87 % belegt – daher **strikte Vermeidung dauerhafter großer Zusatzdateien** wie unkomprimierter WAV-Audiodateien!).

### Kritische Hardware-Besonderheiten (Best Practices für Xeon E5-2690 v2):
1. **CPU-Befehlssatz (AVX1 + F16C, kein AVX2):**
   * Da die Xeon E5-2690 v2 Generation noch kein `AVX2` unterstützt, dürfen keine vorkompilierten Standard-Binaries (die `AVX2` voraussetzen) verwendet werden.
   * **Lösung:** Einsatz von **`whisper.cpp`** (für Audio/Untertitel) und **`llama.cpp`** (für Text- & Vision-LLMs), direkt auf dem Server aus dem C/C++-Quellcode mit `-DGGML_NATIVE=ON` kompiliert. Das garantiert maximale Geschwindigkeit auf allen 20 Kernen ohne Python-/PyTorch-Abhängigkeitsprobleme unter Debian 10.
2. **Kein „Safety-Refusal“ bei Adult-Content:**
   * Standard-Sprachmodelle blockieren die Auswertung expliziter Erotik-Texte. Daher werden ausschließlich **Uncensored / Abliterated** Modell-Varianten (im GGUF-Format, Quantisierung `Q4_K_M` oder `Q5_K_M`) eingesetzt, die neutral und präzise anhand unseres BRAM-Regelwerks prüfen.
3. **Ressourcenschonung gegenüber Live-Betrieb & Video-Konvertierung:**
   * KI-Prozesse laufen immer mit reduzierter Prozesspriorität (`nice -n 15`) und begrenzter Thread-Anzahl (z. B. 12–16 von 40 Threads).
   * Wenn `cronjobs/convert_movie.php` aktiv Videos konvertiert (`convert_status = '1'`), drosselt oder pausiert der Hintergrund-KI-Cronjob automatisch.

---

## 2. 🔄 Architektur-Entscheidung: Entkopplung von der Video-Konvertierung

### Warum die Extraktion NICHT fest in `convert_movie.php` eingebaut werden muss (und sollte):
* Auf dem Server liegen bereits **über 5.500 fertig konvertierte Bestandsvideos** (`convert_status = '2'`), die nachträglich analysiert werden müssen.
* Bei jedem fertig konvertierten Film (sowohl Altbestand als auch Neu-Uploads nach Abschluss der Konvertierung) liegt die fertige **Desktop-MP4 (`{id}_{file_id}.mp4`)** bereits im Film-Ordner vor.
* Aus einer bestehenden MP4-Datei extrahiert FFmpeg die für Whisper benötigte `16kHz Mono WAV`-Audiospur (`-vn -acodec pcm_s16le -ar 16000 -ac 1`) in **nur 1 bis 3 Sekunden**!
* **Speicherplatz-Vorteil:** Würde man während der Konvertierung für 5.500 Filme dauerhaft WAV-Dateien aufheben, würde das über **150 GB Speicherplatz** verschwenden. Stattdessen erzeugt der Analyse-Prozess die `.wav`-Datei *just-in-time* aus der `.mp4`, übergibt sie direkt an `whisper.cpp`, speichert die winzige `.vtt`-Untertiteldatei (wenige Kilobyte) sowie das Text-Transkript in der Datenbank und **löscht die temporäre `.wav`-Datei sofort wieder (`unlink`)**.
* **Einheitlicher Workflow:** Dadurch funktioniert ein und derselbe Prozess zu 100 % identisch für das **Testen an einem einzelnen Video**, für die **Nachbearbeitung aller >5.500 Bestandsvideos** und für **alle zukünftig hochgeladenen Videos** (sobald `convert_status = '2'` erreicht ist).

---

## 3. 🧱 Die 4 Ausbaustufen (Phasen-Planung)

Um volles Vertrauen in die Ergebnisse, die Geschwindigkeit und die Erkennungsqualität auf dem Live-Server zu gewinnen, wird das System strikt modular von Phase 1 bis Phase 4 aufgebaut:

```mermaid
flowchart TD
    A["Bestehende oder neu konvertierte MP4\n(convert_status = 2)"] --> B["Stufe 1: Audio & Whisper.cpp\n(Temp-WAV -> Sprache, .vtt Untertitel & Transkript -> WAV löschen)"]
    B --> C["Datenbank: movies_ai_analysis\n(Sprache, Transkript, .vtt-Pfad gespeichert)"]
    C --> D["Stufe 2: Lokale Text-KI (llama.cpp)\n(Prüft Titel, Beschreibung & Audio-Transkript auf BRAM/Recht)"]
    D --> E["Stufe 3: Vision-KI (Separater Ausbauschritt)\n(FFmpeg 4x4 Sprite-Grid -> Visuelle Szenen- & BRAM-Prüfung)"]
    E --> F["ACP Compliance-Bericht & Ampel-Status\n(Grün = OK | Gelb = Hinweis | Rot = BRAM-Verstoß)"]
```

### Phase 1: Einzel-Video-Testlabor im ACP (Proof of Concept & Feinjustierung)
Bevor irgendein Cronjob über tausende Filme läuft, bauen wir im Admin Control Panel (ACP) unter einer eigenen Seite (z. B. `/KI-Analyse` -> `acp/includes/ai_compliance_lab.php`) ein **interaktives Test-Cockpit**:
1. **Gezielte Video-Auswahl:**
   * Eingabe einer beliebigen `movie_id` (oder Auswahl aus einer Liste hochgeladener Creator-Videos).
   * Anzeige der aktuellen Film-Metadaten (Titel, Beschreibung, Kategorien, Laufzeit, Dateipfad der `.mp4`, Status).
2. **Manueller Test-Button für Modul A (Audio -> Untertitel & Transkript):**
   * Klick auf **„1. Audio & Untertitel jetzt extrahieren (Whisper)“**:
   * Extrahiert temporär die Audiospur der gewählten MP4, führt `whisper.cpp` aus und löscht die temporäre WAV-Datei.
   * **Sofort-Anzeige im ACP:**
     * Erkannte Sprache (z. B. `Deutsch (98.4 %)` oder `Kein gesprochener Text erkannt`).
     * Benötigte Server-Rechenzeit (z. B. `14,2 Sek. bei 12 Threads`).
     * Vorschau der erzeugten `.vtt`-Untertiteldatei + vollständiges Text-Transkript mit Zeitstempeln (`[00:12 - 00:18] ...`).
     * Speicherung in der Datenbank (`movies_ai_analysis`).
3. **Manueller Test-Button für Modul B (Text- & Transkript-Compliance via lokaler KI):**
   * Klick auf **„2. Texte & Transkript mit KI auf BRAM prüfen“**:
   * Sendet Filmtitel, Filmbeschreibung und das aus Modul A gewonnene Audio-Transkript an das lokale Sprachmodell (`llama.cpp`).
   * Über ein direkt im ACP anpassbares **Regel-/Prompt-Feld** können wir unsere verbotenen Begriffe und BRAM-Kriterien live testen und verfeinern.
   * **Sofort-Anzeige im ACP:**
     * Compliance-Ampel (**Grün / Gelb / Rot**) & Risiko-Score (`0–100`).
     * Konkrete Begründung der KI, welche Textstelle im Titel, in der Beschreibung oder bei welcher Sekunde im gesprochenen Video-Text verdächtig ist.

### Phase 2: Gesteuerter Batch-Lauf & Hintergrund-Cronjob (Bestand >5.500 Videos + Neu-Uploads)
Sobald wir in Phase 1 an mehreren echten Creator-Videos gesehen haben, dass Untertitel, Spracherkennung und Text-Prüfung perfekt arbeiten:
1. **Batch-Steuerung im ACP (analog zur `/Speicher-Analyse`):**
   * Buttons für kontrollierte Testläufe: `5 Filme prüfen`, `20 Filme prüfen` oder `Auto-Lauf`.
   * Live-Fortschrittsbalken: *„X von 5.550 Videos haben bereits Untertitel & Transkript“* / *„Y von 5.550 Videos sind KI-Text-geprüft“*.
2. **Hintergrund-Cronjob (`cronjobs/ai_process_movies.php`):**
   * Prüft bei jedem Aufruf zuerst: Läuft gerade eine Video-Konvertierung (`convert_status = '1'`)? Falls ja -> schonender Modus oder Pause.
   * **Prioritäts-Warteschlange (Queue):**
     * **Prio 1:** Neu hochgeladene und frisch konvertierte Filme (`released = '1'` und noch ungeprüft im ACP `movie_checked = '0000-00-00 00:00:00'`), damit der Admin bei der Freischaltung sofort das Transkript und den KI-Bericht vorliegen hat!
     * **Prio 2:** Schrittweises Abarbeiten der über 5.500 Bestandsfilme (`movies_online` / `movies` mit `convert_status = '2'`), bei denen noch keine Untertitel bzw. noch keine KI-Textprüfung in der Datenbank hinterlegt sind.

### Phase 3: Integration in die ACP-Videoprüfung (`movie_checking.php`), Videobearbeitung (`movie_edit.php`), Übersichtslisten & Untertitel-Bereinigung (Abgeschlossen & Aktiv)
* **Sortierbare Spalte `BRAM-Score` in den ACP-Filmübersichten:**
  * Sowohl in `/Filme-online` (`acp/includes/movies_online.php` & `acp/includes/ajax/movies_online.php`) als auch in `/Filme-pruefen` (`acp/includes/movies_checking.php` & `acp/includes/ajax/movies_checking.php`) wird über `aic_render_bram_score_badge()` der aktuelle BRAM-Risiko-Score angezeigt und ist direkt per Klick in DataTables sortierbar.
  * **Klare Unterscheidung zwischen Vorab-Textcheck und vollständiger Audio-Prüfung:**
    * Solange ein Film noch nicht durch den Whisper-Cronjob transkribiert wurde (`subtitle_status = 0`), aber in Titel/Beschreibung unauffällig ist, zeigt das Badge **Blau/Türkis (`0 / 100 (Nur Text)`)** statt Grün an.
    * Erst wenn sowohl die Text-Metadaten als auch das gesprochene Audio-Transkript (`subtitle_status IN (2, 3)`) geprüft und unbedenklich sind, erscheint **Grün (`0 / 100`)**.
* **Dedizierte Reiter in `/Film-bearbeiten/{movie_id}` und `/Film-pruefen/{movie_id}`:**
  * Über `aic_get_movie_bram_and_ai_tabs_data()` werden in `acp/includes/movie_edit.php` und `acp/includes/movie_checking.php` zwei eigene jQuery-UI-Tabs sowie ein kompaktes Top-Banner im Bearbeiten-Tab eingebunden:
    1. **Tab „BRAM & KI-Analyse“ (`#tab-bram`):** 4 KPI-Karten, KI-Zusammenfassung, detaillierte Fundstellen-Tabelle (mit exaktem Zeitstempel bei Audio-Treffern), Übersicht der bereinigten Textquellen und Button zur sofortigen Neuprüfung.
    2. **Tab „Audio & Transkript (.vtt)“ (`#tab-transcript`):** Status der MP4- & `.vtt`-Datei, erkannte Sprache, manueller Whisper-Direktstart (mit wählbarem Audio-Umfang) sowie nebeneinanderliegende Textfelder für das gesprochene Audio-Transkript und die `.vtt`-Untertiteldatei.
  * Beim Speichern eines Films im ACP wird die BRAM-Bewertung automatisch mit den neuen Texten aktualisiert (`$force_recalc = true`).

### Phase 4: Visuelle Video-Analyse (Vision-KI / Frame-Collagen)
Als komplett eigenständiges Modul (sobald Phase 1–3 abgeschlossen sind):
* FFmpeg erstellt aus der bestehenden `.mp4` ein **4x4 Sprite-Grid** (`16 Szenen` gleichmäßig über den Film verteilt auf einem einzigen Bild inkl. eingebranntem Zeitstempel).
* Ein lokales Vision-Modell analysiert dieses Übersichtsbild auf visuelle BRAM-Verstöße (Gewalt, Waffen, Blut, Fesselung/Zwang, eingeblendete Fremd-URLs/Watermarks) und erstellt eine visuelle Inhaltszusammenfassung in `movies_ai_analysis`.

---

## 4. 🗄️ Datenbank-Schema (`movies_ai_analysis`)

Um die Kerntabelle `movies` sauber zu halten und für jedes der >5.500 Videos jederzeit abfragen zu können, welcher Schritt bereits erledigt ist, wird eine dedizierte Tabelle `movies_ai_analysis` verwendet:

| Spalte | Typ | Beschreibung |
| :--- | :--- | :--- |
| `movie_id` | `INT(11) UNSIGNED PRIMARY KEY` | Verknüpfung zu `movies.id` |
| `merchant_id` | `INT(11) UNSIGNED INDEX` | Verknüpfung zu `movies.merchant_id` |
| `subtitle_status` | `TINYINT(1) DEFAULT 0` | `0` = Offen, `1` = In Arbeit, `2` = Erstellt, `3` = Kein Audio/Stumm, `4` = Fehler |
| `detected_language` | `VARCHAR(16) DEFAULT ''` | Bereinigter ISO-Sprachcode (z. B. `de`, `en`, `es`, `fr`, `nl`, `pl`, `ru`) |
| `language_confidence` | `DECIMAL(5,2) DEFAULT 0.00` | Sicherheit der Spracherkennung in Prozent (z. B. `98.40`) |
| `subtitle_filename` | `VARCHAR(255) DEFAULT ''` | Dateiname der erzeugten `.vtt`-Datei im Film-Ordner (`sub_{id}_{file_id}.vtt`) |
| `spoken_transcript` | `MEDIUMTEXT` | Vollständiger, bereinigter Text aus dem Video mit Zeitstempeln (`[MM:SS - MM:SS] ...`) |
| `spoken_segments_json`| `MEDIUMTEXT` | JSON-Array der bereinigten & verschmolzenen Segmente (`start`, `end`, `text`) |
| `whisper_duration_sec`| `DECIMAL(7,2) DEFAULT 0.00` | Benötigte Rechenzeit für die Audio-Transkription in Sekunden |
| `text_check_status` | `TINYINT(1) DEFAULT 0` | `0` = Offen, `1` = In Arbeit, `2` = Geprüft, `3` = Fehler |
| `text_compliance` | `VARCHAR(20) DEFAULT 'pending'` | Ergebnis der BRAM-/Rechtsprüfung (`pending`, `ok`, `warning`, `violation`) |
| `text_risk_score` | `TINYINT(3) UNSIGNED DEFAULT 0` | Risikobewertung von `0` (unbedenklich) bis `100` (schwerer Verstoß) |
| `text_engine_used` | `VARCHAR(64) DEFAULT ''` | Verwendete Prüf-Engine (z. B. `BRAM-Regel-Engine (JSON v1.1)` oder LLM) |
| `text_summary` | `TEXT` | Deutsche Zusammenfassung der Prüfung zum Inhalt & Audio-Transkript |
| `text_violations_json`| `MEDIUMTEXT` | JSON-Liste gefundener Verstöße (Kategorie, Zitat, Quelle: Titel/Beschreibung/Audio-Zeit) |
| `text_duration_sec` | `DECIMAL(7,2) DEFAULT 0.00` | Benötigte Rechenzeit der Text-Prüfung in Sekunden |
| `vision_check_status` | `TINYINT(1) DEFAULT 0` | *(Für Phase 4)* `0` = Offen, `1` = In Arbeit, `2` = Geprüft, `3` = Fehler |
| `vision_compliance` | `VARCHAR(20) DEFAULT 'pending'` | *(Für Phase 4)* Ergebnis der visuellen Bild-Analyse |
| `vision_report_json` | `MEDIUMTEXT` | *(Für Phase 4)* JSON-Bericht der visuellen Video-Analyse |
| `last_error` | `VARCHAR(500) DEFAULT ''` | Fehlerprotokoll oder Herkunftsvermerk (`OrigLang:...`) bei Sprachkorrektur |
| `created_at` | `DATETIME` | Zeitstempel der ersten Erstellung |
| `updated_at` | `DATETIME` | Zeitstempel der letzten Aktualisierung |

---

## 5. 🛡️ Geprüfte Compliance-Kategorien (Visa / Mastercard BRAM & Gesetz)

Die lokale KI und die Keyword-Regel-Engine prüfen sowohl **direkte Begriffe** als auch **Umschreibungen / Wortstämme** in folgenden Bereichen:
1. **Minderjährigkeit / Age-Play:** Begriffe oder Rollenspiele, die unter 18-Jährige suggerieren (*Schoolgirl, Teen-Abuse, zu jung, „gerade 18“ im bedenklichen Kontext*).
2. **Fehlende Einvernehmlichkeit (Non-Consensual / Gewalt):** Zwang, Vergewaltigungs-Rollenspiele, echt wirkende Schmerzensschreie/Hilferufe im Audio-Transkript (*„Hör auf“, „Nein“* im nicht-einvernehmlichen Kontext), Erpressung.
3. **Bewusstlosigkeit / K.O.-Mittel (Hard-Block Rot):** Schlafende Personen, K.O.-Tropfen, Ohnmacht, schwere Trunkenheit/Drogen.
4. **Inzest / Blutsverwandtschaft:** Direkte familiäre Bezeichnungen (*Vater, Mutter, Bruder, Schwester, Tochter, Sohn* = Rot; mit „Stief-“ als Warnstufe `warning` / Orange zur manuellen Admin-Sichtung).
5. **Extreme Gewalt & Illegalität (Hard-Block Rot):** Blut, Wunden, Waffen, Ersticken/Strangulation, Tier-Bezug (Zoophilie § 184a StGB).
6. **Spezielle Fetische ohne Kreditkarten-Erlaubnis (Prüfhinweis Orange – `warning`):**
   * **KV (Kaviar / Scheiße / Kot), NS (Natursekt / Urin), Poppers & Hypnose:** Verstoßen zwar gegen die Visa/Mastercard BRAM-Richtlinien, sind bei EroCloud aber auf speziellen Fetisch-Partnerseiten **ohne Kreditkarten-Zahlung** erlaubt. Sie werden daher bewusst **orange (`warning`, Score 70)** markiert mit dem Hinweis: *„Nur auf Seiten OHNE Kreditkarte erlaubt!“*.
7. **Unerlaubte Eigenwerbung / Abwerbung (Prüfhinweis Orange – `warning`):** Gesprochene oder geschriebene Hinweise auf externe Bezahlmethoden, PayPal, Überweisung, Telegram, OnlyFans oder private Treffen/Escort.

---

## 6. 🧹 Intelligente Whisper-Sprach-Whitelist & WebVTT-Untertitel-Bereinigung

Eine Analyse von **1.287 real transkribierten Creator-Videos** auf dem Live-Server ergab wichtige Besonderheiten von `whisper.cpp` bei Erotik-Videos, die direkt in der Pipeline (`includes/ai_compliance_core.inc.php`) abgefangen werden:

### 6.1 Europäische Sprach-Whitelist & Automatischer 2-Stufen-Fallback
* **Hintergrund:** Wenn in den ersten 30 Sekunden eines Videos nicht gesprochen, sondern nur gestöhnt, geatmet oder Musik gespielt wird, rät `whisper.cpp` im `-l auto`-Modus häufig exotische Sprachen wie `NN` (Norwegisch Nynorsk), `KO` (Koreanisch), `JA` (Japanisch), `JW` (Javanisch), `LA` (Latein) oder `ZH` (Chinesisch) und transkribiert den gesamten Rest des Videos im falschen Alphabet.
* **Lösung (`aic_get_allowed_whisper_languages()` & `aic_process_single_movie_pipeline()`):**
  1. Es gilt eine feste Whitelist europäischer Sprachen: **`de`, `en`, `es`, `fr`, `it`, `nl`, `pl`, `cs`, `ru`, `pt`, `tr`, `hu`, `sv`, `da`**.
  2. Erkennt Whisper mit `-l auto` eine dieser Sprachen, wird sie übernommen (korrigiert damit auch falsche Datenbank-Angaben der Creatorin).
  3. Gibt `-l auto` hingegen eine exotische Nicht-Whitelist-Sprache zurück (`nn`, `ko`, `ja`, `jw`, `la`, `zh` usw.) und hat Whisper Text außerhalb von Klammern transkribiert, startet `aic_process_single_movie_pipeline()` **sofort automatisch einen zweiten Whisper-Lauf auf derselben temporären `.wav`-Datei mit erzwungener Fallback-Sprache (`-l de` bzw. `movies.movie_language`)**.

### 6.2 Normalisierung von Klammer-/Sternchen-Tags & Zeitraum-Verschmelzung (`aic_clean_and_normalize_segments()`)
1. **Vereinheitlichung sinnvoller Atmosphären-Untertitel auf Deutsch (`aic_normalize_single_bracket_tag()`):**
   * Alle Varianten in eckigen Klammern `[...]`, runden Klammern `(...)` und Sternchen `*...*` werden auf 5 saubere deutsche Closed-Caption-Tags vereinheitlicht:
     * **`[Musik]`** *(aus `[Musik]`, `[MUSIC]`, `[Music]`, `(upbeat music)`, `*Musik*`, `(音楽)`, `[w tle śpiew]` usw.)*
     * **`[Stöhnen]`** *(aus `[Stöhnen]`, `* Sie stöhnt. *`, `* Er stöhnt. *`, `* Sie schreit. *`, `[Groaning]`, `[moaning]`, `(Sie schluchzt.)`, `[Suspiración]` sowie phonetischen IPA-Stöhnlauten wie `[ ˈbɑː ]`, `[ ˈkɛː ]`)*
     * **`[Schweres Atmen]`** *(aus `[Atmen]`, `[heavy breathing]`, `(breathing heavily)`, `[inhales]`, `(panting)` usw.)*
     * **`[Lachen]`** *(aus `[Lachen]`, `* Sie lacht. *`, `[Gelächter]`, `[Laughter]` usw.)*
     * **`[Klatschen]`** *(aus `[Applaus]`, `(applause)`, `[Klatschen]`)*
2. **Echtes Verschmelzen aufeinanderfolgender Zeiträume (Anti-Flacker-Logik):**
   * Wenn Whisper im Sekundentakt hunderte Male untereinander `[Musik]` oder `[Stöhnen]` ausgibt, werden direkt aufeinanderfolgende identische Atmosphären-Tags (solange keine Pause > 12 Sek. und kein gesprochener Dialog dazwischen liegt) **zu einem einzigen durchgehenden Zeitraum verschmolzen** (`Start des ersten Blocks` bis `Ende des letzten zusammenhängenden Blocks`, z. B. `[00:10 - 01:45] [Musik]`).
   * Sobald echter gesprochener Dialog einsetzt, wird der vorangegangene Atmosphären-Block exakt an der Startsekunde des Satzes beendet, damit sich im Videoplayer keine Untertitel überlappen.
3. **Restlose Entfernung von Halluzinationen & technischen Artefakten:**
   * Alle fremden Schriftsysteme (Koreanisch `[두 번째 요리]`, Japanisch `(おうくんの声)`, Chinesisch `(我現在要吃飯了)`, Arabisch, Hindi, Kyrillisch außer bei `ru`), TV-Untertitel-Halluzinationen (`[Untertitel im Auftrag des ZDF]`, `amara.org`) sowie unbrauchbare Tags (`[BLANK_AUDIO]`, `[inaudible]`, `[unverständlich]`, `(istig oder schreit)`, `* Stimmengewirr *`, `* Sie sprechen durcheinander. *`) werden restlos gelöscht.

---

## 7. 📍 Implementierte Dateien & ACP-Oberflächen im Überblick

> **Server-Installations-Details:** Alle Linux-/Debian-10-spezifischen Kompilierungsschritte (`whisper.cpp`, CMake/GCC-Patches, Modell-Pfade unter `/var/www/web1/priv/ai/`) sind im Detail in `@ai-server-installation-guide.md` dokumentiert.

1. **Zentrale Kern-Bibliothek (`includes/ai_compliance_core.inc.php`)**:
   * [`includes/ai_compliance_core.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/ai_compliance_core.inc.php):
     * `aic_ensure_table()`, `aic_check_environment()`, `aic_get_allowed_whisper_languages()`.
     * `aic_normalize_single_bracket_tag()` & `aic_clean_and_normalize_segments()`: Bereinigung, Übersetzung und Zeitraum-Verschmelzung aller `.vtt`-Segmente.
     * `aic_process_single_movie_pipeline()`: Komplette Audio- & Text-Pipeline inkl. automatischem Sprach-Fallback.
     * `aic_render_bram_score_badge()`: Rendert die farbigen BRAM-Badges (`Grün`, `Orange`, `Rot`, `Blau/Nur Text`) in den Film-Tabellen.
     * `aic_get_movie_bram_and_ai_tabs_data()`: Generiert das Top-Banner sowie die beiden kompletten Reiter `#tab-bram` und `#tab-transcript` für die Film-Detailseiten.
2. **BRAM-Regelwerk, Prompt & Batch-Konfiguration (`includes/ai_rules/`)**:
   * [`includes/ai_rules/bram_system_prompt.md`](file:///c:/xampp/htdocs/EroCloud/includes/ai_rules/bram_system_prompt.md) & [`includes/ai_rules/bram_keyword_rules.json`](file:///c:/xampp/htdocs/EroCloud/includes/ai_rules/bram_keyword_rules.json).
   * [`includes/ai_rules/batch_config.json`](file:///c:/xampp/htdocs/EroCloud/includes/ai_rules/batch_config.json) & `includes/ai_rules/batch_status.json`.
3. **Hintergrund-Cronjob (`cronjobs/ai_compliance_worker.php`)**:
   * [`cronjobs/ai_compliance_worker.php`](file:///c:/xampp/htdocs/EroCloud/cronjobs/ai_compliance_worker.php) (dokumentiert in [`cronjobs/jobs.txt`](file:///c:/xampp/htdocs/EroCloud/cronjobs/jobs.txt)).
4. **ACP 5-Tab-Cockpit (`/KI-Analyse`)**:
   * [`acp/includes/ai_compliance.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/ai_compliance.php) & [`acp/includes/ajax/ai_compliance_action.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/ajax/ai_compliance_action.php):
     * **Tab 1:** Einzel-Video-Testlabor & Live-Prüfung.
     * **Tab 2:** Batch- & Cronjob-Steuerung (inkl. 15-Sekunden-Katalog-Schnellscan & Live-Browser-Batch).
     * **Tab 3:** BRAM-Regelwerk & KI-System-Prompt-Editor.
     * **Tab 4:** Analyse-Historie & Filter (`movies_ai_analysis`).
     * **Tab 5:** Transkript- & VTT-Inspektor (Statistische Auswertung aller Klammer-/Sternchen-Tags und Sprachen, `.json`-Export/Copy, **Schritt 1: 1-Klick-Massenbereinigung aller bestehenden Transkripte & `.vtt`-Dateien** ohne Whisper-Neulauf sowie **Schritt 2: 1-Klick-Wiedereinreihen exotischer Sprach-Filme** in die Whisper-Cronjob-Queue).
5. **ACP Film-Übersichten & Detailseiten**:
   * [`acp/includes/movies_online.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/movies_online.php) & [`acp/includes/ajax/movies_online.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/ajax/movies_online.php): Sortierbare Spalte `BRAM-Score`.
   * [`acp/includes/movies_checking.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/movies_checking.php) & [`acp/includes/ajax/movies_checking.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/ajax/movies_checking.php): Sortierbare Spalte `BRAM-Score`.
   * [`acp/includes/movie_edit.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/movie_edit.php) & [`acp/includes/movie_checking.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/movie_checking.php): Top-Banner + eigene Reiter `BRAM & KI-Analyse` und `Audio & Transkript (.vtt)`.



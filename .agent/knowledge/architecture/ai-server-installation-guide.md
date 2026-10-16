---
name: ai-server-installation-guide
description: Vollständiges Server-Installations-, Kompilierungs- und Betriebs-Handbuch (Runbook) für die lokale KI-Infrastruktur (whisper.cpp, Sprachmodelle, Debian 10 Workarounds, LiveConfig-Pfade und Cronjob) auf dem EroCloud-Server.
usage: 'Wird automatisch angewendet bei Server-Neuinstallationen, System-Migrationen, Fehlersuche an whisper.cpp/FFmpeg/llama.cpp oder Anpassungen der Linux-Serverumgebung.'
---

# 🛠️ Server-Installations- & Rebuild-Handbuch: Lokale KI-Pipeline (Debian 10 / LiveConfig)

> **Kontext-Referenzen:** Ergänzt `@master.md`, `@documentation-standards.md` sowie die Architektur-Dokumentation `@ai-compliance-and-subtitle-pipeline.md`.
> **Zweck:** Exakte Schritt-für-Schritt-Dokumentation aller Server-Anpassungen, Kompilierungs-Befehle, Workarounds und Pfade, damit das System jederzeit auf diesem oder einem neuen Server identisch reproduziert oder geprüft werden kann.

---

## 1. 🖥️ Zielsystem & Verzeichnisstruktur auf dem Server

* **Server:** `t199.nlserver.net` (Dell PowerEdge R720)
* **CPU:** 2x Intel Xeon E5-2690 v2 @ 3.00GHz (20 physische Kerne / 40 Threads, unterstützt `SSE3`, `SSSE3`, `AVX`, `F16C` – **kein `AVX2`**)
* **RAM:** 64 GB DDR3 ECC
* **OS:** Debian GNU/Linux 10 (Buster), Kernel `4.19.0-21-amd64`
* **Compiler & Build-Tools:** GCC `8.3.0`, CMake `3.13.4`
* **Web-Verwaltung:** LiveConfig (Web-Benutzer: `web1`, Gruppe: `web1`)

### Wichtige Verzeichnisse (Außerhalb & Innerhalb des Webroots)
Damit die KI-Programme und großen Sprachmodelle sauber vom Webcode getrennt liegen, aber für den PHP-Webbenutzer (`web1`) und den Cronjob les- und ausführbar sind, wurde folgende Struktur unter `/var/www/web1/` eingerichtet:

| Pfad auf dem Server | Beschreibung | Berechtigung |
| :--- | :--- | :--- |
| `/var/www/web1/priv/ai/` | Basisverzeichnis für alle lokalen KI-Tools & Modelle | `web1:web1` (`755`) |
| `/var/www/web1/priv/ai/whisper.cpp/` | Quellcode & Build-Verzeichnis von `whisper.cpp` | `web1:web1` |
| `/var/www/web1/priv/ai/whisper.cpp/build/bin/whisper-cli` | Nativ kompiliertes Whisper-Hauptprogramm (statisch gelinkt) | `web1:web1` (`755`) |
| `/var/www/web1/priv/ai/models/ggml-small-q5_1.bin` | **Standard-Modell (181 MB):** Sehr schnell (~20s pro Kurzvideo, ~430 MB RAM), sehr gute deutsche & englische Erkennung | `web1:web1` (`644`) |
| `/var/www/web1/priv/ai/models/ggml-large-v3-turbo-q5_0.bin` | **Großes Modell (547 MB):** Maximale Detailtiefe, ~7x langsamer auf CPU (~900 MB RAM) | `web1:web1` (`644`) |
| `/var/www/web1/priv/tmp/` | LiveConfig `TEMP_DIR` – hier werden kurzzeitig die temporären `.wav`-Dateien und das Worker-Lockfile (`erocloud_ai_worker.lock`) abgelegt | `web1:web1` |
| `/var/www/web1/htdocs/` | EroCloud Webroot (PHP-Anwendung) | `web1:web1` |
| `/usr/bin/ffmpeg` | System-FFmpeg zur blitzschnellen Extraktion der `16kHz Mono WAV`-Spur aus `.mp4`-Videos | `root:root` (`755`) |

---

## 2. 🔧 Schritt-für-Schritt Reproduktion der Server-Installation

Falls das System auf einem neuen Server aufgesetzt oder unter Debian 10 (Buster) wiederhergestellt werden muss, sind exakt folgende Schritte auszuführen:

### Schritt 2.1: Debian 10 (Buster) Paketquellen auf `archive.debian.org` umstellen
Da Debian 10 (Buster) offiziell im Archiv-Status ist, schlägt `apt-get update` mit Standard-Quellen (`deb.debian.org`) fehl. Ohne das Betriebssystem zu aktualisieren, werden die Paketquellen sicher auf das offizielle Debian-Archiv umgestellt:

```bash
cp /etc/apt/sources.list /etc/apt/sources.list.bak

cat << 'EOF' > /etc/apt/sources.list
deb http://archive.debian.org/debian/ buster main contrib non-free
deb http://archive.debian.org/debian-security/ buster/updates main contrib non-free
EOF

apt-get -o Acquire::Check-Valid-Until=false update
apt-get install -y build-essential cmake git curl wget
```

### Schritt 2.2: Verzeichnisse anlegen & `whisper.cpp` klonen
```bash
mkdir -p /var/www/web1/priv/ai/models
cd /var/www/web1/priv/ai
git clone https://github.com/ggerganov/whisper.cpp.git
cd /var/www/web1/priv/ai/whisper.cpp
```

### Schritt 2.3: Kompatibilitäts-Patches für Debian 10 (CMake 3.13 & GCC 8.3)
Neuere Versionen von `whisper.cpp` (ab v1.9.x / `ggml 0.26+`) setzen standardmäßig CMake `3.14` voraus und nutzen `std::filesystem` aus C++17. Auf Debian 10 (CMake `3.13.4` und GCC `8.3.0`) sind dafür drei gezielte Anpassungen erforderlich:

```bash
# 1. CMake-Mindestversion von 3.14 auf 3.13 senken und optionale install()-Regeln ohne Zielpfad auskommentieren
find . -name "CMakeLists.txt" -exec sed -i 's/VERSION 3\.14/VERSION 3.13/g' {} +
find . -name "CMakeLists.txt" -exec sed -i 's/install(TARGETS/#install(TARGETS/g' {} +

# 2. CMake-Konfiguration mit nativer CPU-Optimierung (-DGGML_NATIVE=ON) und statischer Verlinkung (-DBUILD_SHARED_LIBS=OFF)
rm -rf build
cmake -B build -DGGML_NATIVE=ON -DBUILD_SHARED_LIBS=OFF -DCMAKE_BUILD_TYPE=Release

# 3. Wichtig bei GCC 8.3: -lstdc++fs ans Ende der Linker-Zeile von whisper-cli anfügen (verhindert undefined reference to std::filesystem)
sed -i 's/$/ -lstdc++fs/' build/examples/cli/CMakeFiles/whisper-cli.dir/link.txt

# 4. Kompilieren mit 20 Threads
cmake --build build --config Release --target whisper-cli -j 20
```

### Schritt 2.4: Whisper-Sprachmodelle (GGML) herunterladen & Rechte setzen
```bash
# 1. Empfohlenes schnelles Modell (181 MB - ca. 7x schneller auf Xeon E5-2690 v2)
wget -O /var/www/web1/priv/ai/models/ggml-small-q5_1.bin https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-small-q5_1.bin

# 2. Optionales großes Modell (547 MB)
wget -O /var/www/web1/priv/ai/models/ggml-large-v3-turbo-q5_0.bin https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-large-v3-turbo-q5_0.bin

# 3. Besitzerrechte für LiveConfig-Webbenutzer (web1) setzen
chown -R web1:web1 /var/www/web1/priv/ai
```

### Schritt 2.5: Funktionstest auf der Konsole
```bash
/var/www/web1/priv/ai/whisper.cpp/build/bin/whisper-cli \
  -m /var/www/web1/priv/ai/models/ggml-small-q5_1.bin \
  -f /var/www/web1/priv/ai/whisper.cpp/samples/jfk.wav \
  -bs 1 -bo 1 -t 16
```
* **Erwartete System-Info in der Ausgabe:**
  `system_info: n_threads = 16 / 40 | CPU : SSE3 = 1 | SSSE3 = 1 | AVX = 1 | F16C = 1 | OPENMP = 1 | REPACK = 1`
* **Gemessene Benchmark-Werte auf `t199` (11 Sek. Audio):**
  * Mit `ggml-large-v3-turbo-q5_0.bin`: `~22,2 Sekunden` (`~900 MB RAM`)
  * Mit `ggml-small-q5_1.bin` (`-bs 1 -bo 1 -t 16`): **`~3,2 Sekunden`** (`~430 MB RAM`)

---

## 3. ⚠️ Wichtige technische Besonderheiten im LiveConfig-Betrieb

1. **`open_basedir` Restriktion unter PHP (Web-FPM/FastCGI):**
   * Im Web-Kontext von LiveConfig darf PHP mittels `file_exists()` nur Pfade innerhalb von `/var/www/web1/` prüfen. Ein direkter Aufruf von `file_exists('/usr/bin/ffmpeg')` liefert daher `false`, obwohl `/usr/bin/ffmpeg` per `exec()` problemlos ausführbar ist!
   * **Gelöst in:** [`includes/ai_compliance_core.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/ai_compliance_core.inc.php) über die Hilfsfunktion `aic_file_or_exec_exists($path)`, die als Fallback `exec('test -e ... && echo 1')` nutzt.
2. **HTML-Entities in `movies.description` und `movies.title`:**
   * Viele ältere und neue Filmbeschreibungen in der Tabelle `movies` enthalten HTML-Tags (`<p>`) sowie HTML-Entities (`&Uuml;berweisung`, `&auml;`, `&szlig;`).
   * **Gelöst in:** [`includes/ai_compliance_core.inc.php`](file:///c:/xampp/htdocs/EroCloud/includes/ai_compliance_core.inc.php) über `aic_clean_html_text()`, sodass alle Texte vor der Anzeige im ACP und vor dem BRAM-Regex-Scan sauber in echtes UTF-8 dekodiert werden.
3. **PHP Session-Locking bei langen AJAX-Requests:**
   * Da ein kompletter Whisper-Durchlauf im Browser-Batch je nach Filmlänge 20 bis 180 Sekunden dauert, gibt [`acp/includes/ajax/ai_compliance_action.php`](file:///c:/xampp/htdocs/EroCloud/acp/includes/ajax/ai_compliance_action.php) direkt nach der Login-Prüfung (`is_logged_in('acp')`) die Session mit `@session_write_close()` frei.
   * Dadurch können während eines laufenden Whisper-Prozesses weiterhin im 2-Sekunden-Takt Live-Status-Abfragen (`poll_worker_status`) ausgeführt und andere ACP-Seiten ohne Wartezeit geöffnet werden.

---

## 4. ⏱️ Eingerichteter Hintergrund-Cronjob (LiveConfig)

* **Cronjob-Skript:** [`cronjobs/ai_compliance_worker.php`](file:///c:/xampp/htdocs/EroCloud/cronjobs/ai_compliance_worker.php) (dokumentiert in [`cronjobs/jobs.txt`](file:///c:/xampp/htdocs/EroCloud/cronjobs/jobs.txt))
* **LiveConfig / Crontab-Befehl (alle 2 Minuten):**
  ```bash
  */2 * * * * /usr/bin/php /var/www/web1/htdocs/cronjobs/ai_compliance_worker.php >/dev/null 2>&1
  ```
* **Manueller Test-Aufruf über SSH (ignoriert den Pause-Schalter im ACP durch `--force`):**
  ```bash
  /usr/bin/php /var/www/web1/htdocs/cronjobs/ai_compliance_worker.php --force --limit=1
  ```
* **Schutzmechanismen des Cronjobs:**
  1. **ACP-Hauptschalter (`cron_enabled`):** Liest [`includes/ai_rules/batch_config.json`](file:///c:/xampp/htdocs/EroCloud/includes/ai_rules/batch_config.json). Ist das Häkchen im ACP unter `/KI-Analyse` -> Reiter 2 nicht gesetzt, beendet sich der Cronjob sofort ohne Last.
  2. **Exklusives Prozess-Lock (`flock`):** Nutzt `/var/www/web1/priv/tmp/erocloud_ai_worker.lock`. Falls ein vorheriger Whisper-Lauf bei einem 30-Minuten-Video länger als 2 Minuten dauert, bricht der nächste Cron-Aufruf sofort sauber ab – es laufen **niemals** zwei Whisper-Prozesse parallel.
  3. **Vorrang für Video-Konvertierung (`pause_on_ffmpeg_convert`):** Prüft `SELECT COUNT(*) FROM movies WHERE convert_status = '1'`. Sobald `convert_movie.php` ein neues Video konvertiert, wartet der KI-Worker automatisch.
  4. **Stummfilm- & Fehler-Toleranz:** Hat ein Video keine Tonspur (`subtitle_status = 3`) oder fehlt die `.mp4`-Datei (`subtitle_status = 4`), wird trotzdem die BRAM-Textprüfung über Titel/Beschreibung ausgeführt und das Video als verarbeitet markiert, damit die Warteschlange niemals blockiert.

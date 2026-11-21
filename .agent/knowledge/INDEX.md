---
name: knowledge-index
description: Zentrales Inhaltsverzeichnis (Single Point of Entry) fuer die gesamte technische und fachliche Wissensdatenbank der EroCloud (.agent/knowledge/).
usage: 'Wird vor jeder Code-Aenderung, Fehleranalyse oder Feature-Entwicklung konsultiert, um sofort die zuständigen Dateien, Datenbanktabellen, Caches und Regeln zu kennen.'
---

# 📚 EroCloud Wissensdatenbank – Zentrales Inhaltsverzeichnis (`INDEX.md`)

> **Pflicht-Einstiegspunkt gemäß `@master.md` & `@documentation-standards.md`:** Vor jeder Code-Änderung, Fehleranalyse oder Feature-Erweiterung wird hier geprüft, welche Architektur- und Fachdokumentation für den betroffenen Bereich existiert. Werden Abläufe, Endpunkte, Caches oder Datenbankfelder geändert, ist die zugehörige Dokumentation **sofort im selben Arbeitsschritt** nachzuziehen.

---

## 🧭 1. Grundgesetze & Projekt-Regeln (`.agent/rules/`)
* [`master.md`](../rules/master.md) — **Oberstes Projekt-Gesetz:** PHP `8.0.30` + `8.4.27` Kompatibilität, `p4c_*`-DB-Wrapper mit `__FILE__, __LINE__`, `SAFE_INC`, Du-Form im UI & Dokumentations-Pflicht.
* [`documentation-standards.md`](../rules/documentation-standards.md) — Namenskonventionen, YAML-Frontmatter & Pflege-Pflicht für `.agent/`.
* [`github-auto-commit.md`](../rules/github-auto-commit.md) — Verpflichtender automatischer Git-Commit & Push nach Aufgabenabschluss.
* [`permissions.md`](../rules/permissions.md) — Shell- & Dateiberechtigungen für Agenten.

---

## 🖥️ 2. Server-Infrastruktur, Panels & System-Architektur
* [`server-and-system-environment.md`](architecture/server-and-system-environment.md) — Produktionsserver `t199.nlserver.net` (`91.184.62.199`, Debian 11, LiveConfig), PHP-Pfade (`/opt/php-8.0/`, `/opt/php-8.4/`), alle Domains (`erocloud.net`, `acp.erocloud.net`, `api.erocloud.net`), aktive Cronjobs & zentrales Error-Log (`/var/www/web1/htdocs/log/.errorlog`).
* [`mcp-modernization-architecture.md`](architecture/mcp-modernization-architecture.md) — Architektur des modernen **Merchant-Control-Panels (MCP v2.0)** unter `mcp/mcp_neu/` (`app/` & `web/` Trennung, SPA-Router, CSRF, Cover-Studio & `mcp_neu/web/media.php`).
* [`acp-modernization-architecture.md`](architecture/acp-modernization-architecture.md) — Architektur des modernen **Admin-Control-Panels (ACP v2.0)** unter `acp/acp_neu/` (`app/` & `web/`, Rollen `employee_rule` [`read`/`write`] & Bereichsrechte `employee_access_area`).
* [`storage-and-traffic-analytics.md`](architecture/storage-and-traffic-analytics.md) — ACP-Modul *„Speicher & Traffic“* (`#/storage-analytics`), Dateisystem-Scanner, Streaming-Traffic-Auswertung & Kostenkalkulation.

---

## 🎬 3. Content-Module, Lösch-Lebenszyklen & Externe API-Synchronisation
* [`video-management.md`](architecture/video-management.md) — Kompletter **Video-Lebenszyklus** (`movies`, `movies_online`, `movies_access`), Upload, FFmpeg-Konvertierung (`cronjobs/convert_movie.php`), Cover-Generierung & ACP-Videoprüfung.
* [`movie-deletion-lifecycle.md`](architecture/movie-deletion-lifecycle.md) — **Lösch-Lebenszyklus von Filmen:** Soft-Delete vs. MCP-Löschung, 5-Regel-Schutzfristenmodell, `cronjobs/delete_movie.php`, Archivtabelle `movies_deleted` & bekannte Konflikte.
* [`photo-album-management-and-lifecycle.md`](architecture/photo-album-management-and-lifecycle.md) — **Fotoalben-Gesamtarchitektur & Lösch-Lebenszyklus:** Tabellen (`photo_albums`, `photo_albums_online`, `photo_albums_photos`, `photo_albums_access`), Cloud-Storage-Pfade, MCP-Upload/Cover-Studio/1-Klick-Foto-Rotation (`rotatePhoto`), ACP-Prüfung, **4-stufiger Lösch-Lifecycle** (Entwurf, Soft-Delete mit Käuferschutz, 0-Käufe-Cronjob `cronjobs/delete_photo_album.php`, manueller Admin-Hard-Delete) & End-to-End EroCMS-Sync (`utf8mb4`, `0-DDL` `only_if_buyed`).
* [`erocms-api-import.md`](architecture/erocms-api-import.md) — **Zentrale EroCloud-API (`api.erocloud.net`) für externe EroCMS-Seiten:** API-Key-Auth, Film- **und** Fotoalben-Endpunkte (`get_movies`, `get_movie`, `get_photo_albums`, `get_photo_album`, `get_photo_album_thumbs`), 50-Minuten-Datei-Caches (`api/temp/...`) inkl. Invalidierung, Checksummen-Logik, Streaming-/ZIP-Token-Auslieferung & Zusammenspiel mit `EroCMS/includes/cronjobs/`.

---

## 👤 4. Profile, 18+ Drehpartner-Verifizierung, KI-Compliance & Kommunikation
* [`profile-management.md`](architecture/profile-management.md) — Verwaltung und Lebenszyklus von Merchants (`merchants`), Darsteller-Profilen (`actors` inkl. `cronjobs/actor_reassign.php`) und Cloud-Usern.
* [`co-actor-verification-and-model-release.md`](architecture/co-actor-verification-and-model-release.md) — **Drehpartner- & 18+ ID-Check-System** (`CoActorManager`, QR-Code Smartphone-Self-Service, Model-Release-Verträge, Ausweis-/ID-Shot-Prüfung & Zuordnung zu Filmen/Fotoalben).
* [`ai-compliance-and-subtitle-pipeline.md`](architecture/ai-compliance-and-subtitle-pipeline.md) — Lokale KI-Moderations- & Compliance-Pipeline (`includes/ai_compliance_core.inc.php`, `cronjobs/ai_compliance_worker.php`, Whisper `.vtt`-Untertitel & Visa/Mastercard BRAM-Regel-Engine).
* [`ai-server-installation-guide.md`](architecture/ai-server-installation-guide.md) — Server-Runbook für `whisper.cpp`, FFmpeg, Sprachmodelle und CLI-Worker auf `t199.nlserver.net`.
* [`email-notification-and-magic-link-system.md`](architecture/email-notification-and-magic-link-system.md) — Zentrales E-Mail-, Auto-Login- (`EroCloudMagicLink`), 1-Klick-Unsubscribe- und Notification-System (`EroCloudMailer`, Postfix/DKIM/DMARC & DB-Impressum).

---

## 🌐 5. Weitere Plattform-Konzepte
* [`amoredea-next-gen-portal-architecture.md`](architecture/amoredea-next-gen-portal-architecture.md) — Produkt- & Architekturkonzept für den Neubau von `amoredea.com` (`web2`).

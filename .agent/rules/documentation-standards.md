---
name: documentation-standards
description: Core-Direktive für die Erstellung und Organisation von Markdown-Dateien zur Maximierung der Effizienz in der Agent-First-Umgebung.
usage: 'Wird automatisch angewendet, wenn neue Dokumentationen, Regeln, Skills oder Workflows erstellt werden.'
---

# Dokumentations-Standards (Core-Direktive)

Diese Regel definiert den Standard für alle neuen Markdown-Dateien im Projekt EroCloud, um die Indexierung und Nutzung durch KI-Agenten zu optimieren.

## 1. Sprache
- **Deutsch-Pflicht**: Alle neuen Markdown-Dateien müssen zwingend auf **Deutsch** erstellt werden.

## 2. Speicherort & Kategorisierung
Alle `.md`-Dateien müssen im Verzeichnis `.agent/` abgelegt werden:
- `.agent/rules/`: Für dauerhafte Verhaltensregeln und Coding-Standards (z. B. `PHP_Standards.md`). Diese sind "Always On".
- `.agent/knowledge/`: Für Architektur-Dokumentation, Fachwissen, Datenbanken und allgemeine Systeminfos (z. B. `schema.md`, `project-architecture.md`).
- `.agent/skills/`: Für spezifische Aufgabenbeschreibungen. Jeder Skill erhält einen eigenen Unterordner.
- `.agent/workflows/`: Für komplexe Prozesse, die mehrere Schritte erfordern.

## 3. Regeln für Benennungen (Naming Conventions)
- **Ordner**: Alle Ordnernamen innerhalb von `.agent/` werden kleingeschrieben (**lowercase**).
- **Dateien**: Nutze für `.md`-Dateien den **Kebab-Case** (z. B. `user-auth-logic.md`). Vermeide Leerzeichen und Umlaute.
- **Präfixe**:
    - **Skills/Workflows**: Sollten mit einem **Verb** beginnen (z. B. `add-...`, `check-...`, `deploy-...`).
    - **Regeln**: Sollten das **Thema** benennen (z. B. `ui-standards.md`).
- **Konsistenz**: Bei Referenzen strikt auf Groß-/Kleinschreibung achten (Case-Sensitivity).

## 4. Dateiaufbau (Pflicht-Header)
Jede Datei muss mit einem YAML-Frontmatter beginnen:
```yaml
---
name: [Eindeutiger Name]
description: [Kurzbeschreibung, wann dieser Skill/diese Regel aktiv werden soll]
usage: [Beispiel-Trigger für den User]
---
```

## 5. Arbeitsweise
- **Planung zuerst**: Erstelle vor jeder Umsetzung ein Artifact (einen Plan), in dem du bestätigst, welche Regeln aus `.agent/` du anwendest.
- **Kontext-Referenz**: Verweise in neuen Dateien immer mittels `@Dateiname` auf bestehende Regeln, um Redundanz zu vermeiden. (Hinweis: @master.md ist die primäre Blaupause).

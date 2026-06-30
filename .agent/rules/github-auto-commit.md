---
type: global-rules
description: Automatisches GitHub-Deployment nach Bearbeitungen durch die KI.
---

# 🤖 GIT AUTO-COMMIT REGEL

**WANN IMMER DU (die KI) EINE AUFGABE / EINEN TICKET-STRANG ABSCHLIESST:**
Sobald du (Antigravity) Modifikationen an Dateien vorgenommen hast und einen Task erfolgreich beendest (bevor du per `notify_user` auf den Nutzer wartest oder die Finalisierung meldest), bist du **verpflichtet**, diese Änderungen sofort an GitHub zu übertragen!

## Auszuführende Befehle (via `run_command` im Projekt-Root):
Führe nach Abschluss deiner Änderungen folgende Befehle sequenziell per Terminal aus:
1. `git add .`
2. `git commit -m "Auto-Commit (Antigravity): [Kurze Beschreibung deiner durchgeführten Änderungen]"`
3. `git push origin main` (oder den jeweiligen aktuellen Branch)

## Verhalten bei Konflikten
Sollte der `git push` fehlschlagen (z.B. weil der Remote-Branch neuere Commits enthält), führe ein `git pull --rebase` aus und pushe anschließend erneut.
Informiere den Nutzer in deiner Abschlussnachricht kurz und prägnant darüber, dass die Änderungen erfolgreich ins Repository gepusht wurden ("✅ GitHub synchronisiert").

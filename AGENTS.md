# Regeln für flz_elternsprechtag

Dieses Repository enthält ausschließlich das Fachplugin
`flz_elternsprechtag`. Personenbezogene Daten, Terminvergabe, CSV, E-Mail,
Tokens, Rollen, Capabilities und Schemaänderungen sind Risikogrenzen.

Der Stand wurde am 20.10.2026 aus dem verifizierten Release-Artefakt
`1.2.0-rc.14` wiederhergestellt. Tests, CI-Konfiguration und frühere Git-
Historie waren nicht Bestandteil des ZIP und gelten bis zu ihrer unabhängigen
Wiederherstellung als nicht vorhanden. Das Quality-Gate bleibt deshalb in
Wiederherstellungsphase 0.

- Admin-Schreibpfade prüfen Capability und Nonce; öffentliche Buchungen prüfen
  Nonce, Eingaben, freien Slot und Nebenläufigkeit serverseitig.
- Buchung und Import sind atomar; Deaktivierung löscht keine Daten.
- Übersetzbare Texte verwenden `flz-elternsprechtag`.
- Mindestens `./scripts/check-fast` ausführen. DDEV-/WordPress-Prüfungen
  werden separat benannt.
- Keine Commits, Pushes, Aktivierungen, Imports oder Deployments ohne
  ausdrückliche Freigabe; nie `git add .` verwenden.

## Commit-, Coverage- und Release-Gates

- 85 Prozent bleiben das Ziel für neuen oder wesentlich geänderten Code.
- Die frühere Test- und Coverage-Baseline ist nach dem Quellverlust nicht
  belegbar; bis zur Wiederherstellung sind Commit und Release blockiert.
- `docs/manual-acceptance.md` dokumentiert die letzte fachliche Abnahme; sie
  ersetzt keinen Nachweis für den wiederhergestellten Quellstand.

## Parent-Governance-Vertrag: 1

- Die für dieses Komponenten-Repository anwendbaren Regeln des
  Parent-Workspaces sind verbindlich. Dazu gehören insbesondere gemeinsame
  Architektur-, Sicherheits-, Plugin- und Theme-Verträge,
  Repositorygrenzen sowie Workspace-, Quality-, Delivery- und Release-Gates.
- Diese lokale `AGENTS.md` und die lokalen Skills bleiben die vollständige,
  ohne Parent-Checkout arbeitsfähige Repository-Steuerung. Die anwendbaren
  Parent-Regeln werden dafür hier oder in den lokalen Skills mitgeführt.
- Vor Arbeit an der Komponente ist der lokale Skill
  `work-in-wordpress-extension` zu verwenden. Für beobachtbare Änderungen
  gilt zusätzlich der lokale Skill `test-driven-wordpress-change`.
- Repository-lokale Regeln dürfen Parent-Verträge konkretisieren und
  verschärfen, aber nicht abschwächen oder umgehen.
- Bei einem Widerspruch gilt bis zur Klärung die strengere Regel. Die Arbeit
  stoppt, bis die kanonische Quelle bestimmt, die Regelprojektionen
  synchronisiert und eine erforderliche Entscheidung dokumentiert ist.
- Ist der Parent-Workspace nicht verfügbar, bleibt die lokale Steuerung
  wirksam. Vor Cross-Component-, Release- oder Delivery-Arbeit muss ein
  vermuteter neuerer Parent-Stand oder eine Regelungslücke zuerst gegen den
  Parent geprüft werden.

# Regeln für flz_elternsprechtag

Dieses Repository enthält ausschließlich das Fachplugin
`flz_elternsprechtag`. Es verwaltet Lehrkräfte, Eltern, Kinder und Termine.
Personenbezogene Daten, Terminvergabe, CSV, E-Mail, Tokens, Rollen,
Capabilities und Schemaänderungen sind Risikogrenzen.

Harte Abhängigkeiten sind die öffentlichen, versionierten Verträge von
`flz_wpdb_objects` und `flz_ui_components`. Der Header `Requires Plugins` wird
durch defensive Klassen-/Funktions-/Versionsprüfungen ergänzt. Interne Dateien
anderer Repositories werden nie direkt eingebunden.

- Admin-Schreibpfade prüfen Capability und Nonce; öffentliche Buchungen prüfen
  Nonce, Eingaben, freien Slot und Nebenläufigkeit serverseitig.
- Buchung und Import sind atomar; ein Slot kann nicht doppelt belegt werden.
- Deaktivierung löscht keine Daten. Uninstall ist ein eigener genehmigter Pfad.
- Lehrkräfte sowie Termine/Buchungen besitzen versionierten CSV-Import und
  -Export mit Dry-Run; Exporte werden geschützt direkt gestreamt.
- Aufbewahrung, Auskunft, Anonymisierung und Löschung sind dokumentiert und
  testbar. Logs enthalten keine Personen- oder Tokenwerte.
- Übersetzbare Texte verwenden `flz-elternsprechtag`.

Beobachtbare Änderungen testgetrieben umsetzen. Sicherheitsgrenzen brauchen
Allow-/Deny-Fälle und das Ausbleiben verbotener Nebenwirkungen. Den vorhandenen
PHPUnit-Test sowie `./scripts/check-fast` ausführen; fehlenden Runner und
WordPress-/DDEV-Prüfung ehrlich benennen. Keine Commits, Pushes, Aktivierungen,
Imports oder Deployments ohne ausdrückliche Freigabe; nie `git add .` verwenden.

## Commit-, Coverage- und Release-Gates

- Der aktuelle Übernahmestand ist Phase 1: PR-/Main-CI, Lizenz, Changelog und
  branchgleiche Provider-Checkouts sind lokal konfiguriert. Bis zum ersten
  grünen Remote-Lauf und dem Coverage-Gate bleiben normale Produkt- und
  Releasecommits blockiert; ausdrücklich beauftragte Quality-Rollout-Commits
  dürfen die fehlende Infrastruktur schrittweise herstellen.
- Vor einem späteren normalen Commit sind Status, Diff-Statistik und vollständige
  Dateiliste zu zeigen; fokussierte Tests, `./scripts/check-fast`, Shared-
  Provider-/Consumer-Tests, CI und Coverage-Gates müssen grün sein. Dateien
  werden einzeln gestaged; `git add .` bleibt verboten.
- PHP-Line-Coverage wird gegen eine gemessene No-Regression-Baseline geprüft.
  Neuer oder wesentlich geänderter Code erreicht mindestens 85 Prozent;
  Sicherheits-, Datenschutz-, Migrations- und Nebenläufigkeitsinvarianten sind
  unabhängig davon vollständig abgedeckt.
- Der PHPCOV-/Xdebug-Messjob ist vorbereitet; die PHP-Baseline bleibt bis zum
  ersten reproduzierbaren Remote-Lauf ausdrücklich `pending`.
- Ein Fast- oder Diagnosecheck ist kein Releaseurteil. Ein Release braucht ein
  sauberes Repository, konsistente Version/Changelog/Lizenz, vollständig
  ausgefülltes `docs/manual-acceptance.md`, ein reproduzierbares Ein-Wurzel-
  Archiv, Manifest und SHA-256 sowie geprüfte Installation, Upgrade,
  Deaktivierung, Datenschutz, Mail, sichtbare UI und Rückbau aus dem Artefakt.
- Bauen, Signieren, Taggen, Pushen, Publizieren und Deployen bleiben getrennte,
  ausdrücklich zu autorisierende Aktionen.

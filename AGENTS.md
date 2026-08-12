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

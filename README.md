# flz_elternsprechtag

WordPress-Plugin für Lehrkräfte, Terminserien, öffentliche Buchung,
Bestätigungslinks und geschützte CSV-Portabilität des Elternsprechtags.

Erforderliche Plugins: `flz_wpdb_objects` und `flz_ui_components`. Der aktuelle
Härtungs- und Migrationsstand steht in `ROADMAP.md`.

Prüfung: `./scripts/check-fast`. WordPress-, Datenbank-, Mail- und UI-Verhalten
muss zusätzlich über die lokale DDEV-Instanz verifiziert werden.

## Neu in 1.1.0

- Die eigene DB-Version 2.0.0 migriert die vier Legacy-Tabellen additiv und
  idempotent in das aktuelle Modell. Legacy-Tabellen bleiben als Rückfallpfad
  erhalten; Konflikte brechen die Migration ohne stilles Überschreiben ab.
- Deaktivierung erhält Tabellen, Daten, Rolle und Capability. Der lokale
  Aktivieren–Deaktivieren–Aktivieren-Test bewahrt 85 Lehrkräfte, 5 Elternteile
  und 1004 Termine vollständig.
- WordPress-Privacy-Exporter und -Eraser decken Eltern-/Kind- und
  Lehrkräftedaten ab. Automatische Aufbewahrung ist standardmäßig aus; der
  konfigurierbare Vorschlag beträgt 24 Monate und kann zusätzlich bestätigt
  manuell ausgeführt werden.
- Absender, Terminrahmen, Slotlänge und öffentliche Eltern-/Kinddaten werden
  serverseitig validiert. Ungültige Legacy-Zeitwerte können keine Endlosschleife
  mehr auslösen.
- Frontend- und Admin-Assets der gemeinsamen UI werden nur auf passenden
  Elternsprechtagsseiten angefordert.

## CSV-Portabilität

Beide Adminbereiche exportieren CSV direkt über Capability- und Nonce-
geschützte Downloads. Ein Import ist erst möglich, wenn dieselbe unveränderte
Datei innerhalb von 15 Minuten erfolgreich als Dry-Run geprüft wurde.

- Lehrkräfte v1: `format_version; record_type; gender; last_name; first_name; email`.
  Der Import aktualisiert oder ergänzt anhand der E-Mail-Adresse. Nicht
  aufgeführte Bestandslehrkräfte werden nicht gelöscht.
- Termine/Buchungen v1: `format_version; record_type; teacher_email;
  start_timestamp; end_timestamp; parent_gender; parent_last_name;
  parent_first_name; parent_email; student_name; student_class; gdpr_checked;
  confirmed`. Der Import verlangt einen vollständigen Snapshot sämtlicher
  vorhandener Slots und ersetzt die Buchungsdaten atomar.

Bestätigungstoken und Ablaufzeiten gehören absichtlich nicht zum Export und
werden beim Terminimport zurückgesetzt. CSV v1 verwendet Semikolon als
Trennzeichen und Unix-Zeitstempel für verlustfreie Terminreferenzen.

Unversionierte Altdateien und der frühere vier-spaltige Vorbelegungsimport
werden bewusst nicht als v1 interpretiert. Sie werden ohne Datenänderung
abgewiesen; vor einer Freigabe ist zu entscheiden, ob dafür ein eigener
versionierter `prebooking`-Vertrag oder ein einmaliger Konverter benötigt wird.

## Datenschutz

Die automatische Löschung ist nach Installation und Upgrade deaktiviert. Wird
sie bewusst eingeschaltet, gilt eine Frist von 1 bis 120 Monaten; voreingestellt
sind 24 Monate. Der WordPress-Eraser gibt gebuchte Termine zuerst frei und
löscht anschließend Eltern-/Kinddaten. Lehrkräftedaten werden anonymisiert,
damit das Terminschema konsistent bleibt.

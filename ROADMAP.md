# Roadmap: flz_elternsprechtag

## Prüfstatus

**Nicht regelkonform / P0.** Das Plugin schützt viele Admin-POST-Pfade zentral
mit Capability und Nonce und escaped die geprüften Templates überwiegend.
Dem stehen unmittelbare Datenverlust- und Datenschutzrisiken gegenüber. Der
vorhandene PHPUnit-Test hat im Repository keinen installierten Runner; eine
WordPress-/Datenbankprüfung wurde nicht ausgeführt.

## P0

1. Deaktivierung nichtdestruktiv machen: keine Tabellen, Rollen oder
   Capabilities beim bloßen Deaktivieren löschen. Separaten, standardmäßig
   datenbewahrenden Uninstall-Vertrag erst nach Produktentscheidung ergänzen.
   Abnahme: Aktivieren–Deaktivieren–Aktivieren erhält synthetische Termine und
   Elternangaben vollständig.
2. Lehrkräfte- und Terminexport nicht mehr per
   `flz_wpdb_objects_create_csv_file()` als öffentliche `teachers.csv` bzw.
   `appointments.csv` erzeugen. Capability- und nonce-geschützten Direktdownload
   verwenden; keine Datei darf im Upload-Verzeichnis verbleiben.
3. Private Gmail-Adresse aus dem Testmodus entfernen. Mailtests ausschließlich
   lokal über Mail-Capture oder einen ausdrücklich konfigurierten Filter mit
   synthetischen Empfängern durchführen.
4. Terminbuchung gegen Parallelzugriffe sichern. Auswahl, Freiheitsprüfung,
   Elternspeicherung und Terminbelegung in einer atomaren Operation mit
   Datenbankinvariante ausführen. Abnahme: Zwei parallele Buchungen desselben
   Slots ergeben genau eine Buchung und keinen verwaisten Elterndatensatz.

## P1

1. Lehrkräfte sowie Termine/Buchungen jeweils als versionierten, vollständigen
   CSV-Roundtrip mit Dry-Run, Referenzprüfung und atomarem Import definieren;
   Einstellungen erhalten nach fachlicher Entscheidung einen Portabilitätspfad.
2. Schema-Version und additive, idempotente Upgrades einführen; bereits
   ausgelieferte Migrationen nicht verändern. Frischinstallation, Upgrade,
   Wiederholung und ungültige Altdaten testen.
3. Aufbewahrung, Auskunft, Bestätigung, Widerruf, Anonymisierung und Löschung
   der Eltern-/Kind-/Lehrkräftedaten fachlich festlegen und WordPress-Privacy-
   Exporter/-Eraser ergänzen.
4. E-Mail- und Bestätigungstoken-Vertrag härten: Absender konfigurierbar,
   Adressen validiert, Tokens nach Nutzung gelöscht, Fehler ohne Personen- oder
   Tokenwerte protokolliert.
5. Direkte Includes aus Geschwister-Plugins entfernen und nur deren
   dokumentierte öffentliche Bootstrap-/Funktionsverträge verwenden. Bei
   fehlendem `flz_wpdb_objects` ebenso defensiv abbrechen wie bei fehlender UI.
6. PHPUnit-Runner bereitstellen und Allow-/Deny-Tests für Adminaktionen,
   CSV-Import, öffentliche Buchung, Tokenbestätigung und Fehlernebenwirkungen
   ergänzen.

## P2

1. Controllerfunktionen, Buchungsservice, Datenzugriff und Templates trennen;
   globale, uneinheitlich benannte Funktionen schrittweise präfixen.
2. Sämtliche UI-Texte mit der komponenteneigenen Textdomain übersetzbar machen
   und `Text Domain` im Plugin-Header ergänzen.
3. Datums-/Zeitzonenlogik auf WordPress-Zeitfunktionen vereinheitlichen und
   Start-/Ende-/Slotlänge fachlich validieren.
4. Öffentliche Mehrschrittbuchung in DDEV per Tastatur, mobil, mit sichtbaren
   Fehlern und abgelaufenen/manipulierten Links prüfen.

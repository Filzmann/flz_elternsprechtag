# flz_elternsprechtag

WordPress-Plugin für Lehrkräfte, Terminserien, öffentliche Buchung,
Bestätigungslinks und geschützte CSV-Portabilität des Elternsprechtags.

## Neutrale Demoversion

Diese Variante enthält keine schulgebundenen Texte in Pluginbeschreibung,
Buchungsformular oder Buchungsmails. Technische Plugin-IDs, Abhängigkeiten,
Paket-Herkunft und Lizenzangaben bleiben erhalten.

In einer separaten, leeren WordPress-Demo-Installation unter
**Elternsprechtag → Einstellungen → Demo-Daten anlegen**
den vorhandenen Seed ausführen. Keine Datenbankkopie oder CSV-Datei einer
echten Schule übernehmen: Die Aktivierung kann vorhandene Legacy-Daten
migrieren; der Seed anonymisiert oder entfernt keine Bestandsdaten.

Der Seed erzeugt ausschließlich synthetische Datensätze:

- drei ausdrücklich mit `Demo-` bezeichnete Lehrkräfte;
- zwei Demo-Elternteile mit je einem synthetischen Kind;
- 18 Slots von je 20 Minuten zwischen 16 und 18 Uhr, davon zwei bestätigt gebucht;
- alle sechs Plugin-Einstellungen: Datum in 30 Tagen, Terminrahmen,
  Slotlänge, eingeschalteter Testmodus und `demo.elternsprechtag@example.test`
  als Absender. Sämtliche Personenadressen verwenden ebenfalls `example.test`.

Wiederholtes Anlegen verändert weder vorhandene Demo-Daten noch Einstellungen.
Unter **Demo-Daten entfernen** werden nach Löschbestätigung ausschließlich
die nachweislich von diesem Seed erzeugten, unveränderten Datensätze entfernt.
Nachträgliche Buchungen, Personenänderungen oder zusätzliche Termine mit
Demo-Personen blockieren die gesamte Entfernung ohne Teillöschung.
Vorherige Einstellungswerte werden wiederhergestellt; manuell geänderte Werte
bleiben erhalten. Erneutes Entfernen ist folgenlos, danach ist ein neuer
Seed-Lauf möglich. Der Herkunftsnachweis liegt als nicht automatisch geladene
WordPress-Option `flzest_demo_manifest` vor. Er enthält Datensatz-IDs,
Prüfsummen und die zuvor überschriebenen Einstellungswerte.

Ältere Demo-Daten ohne diesen Herkunftsnachweis werden weder automatisch
übernommen noch gelöscht. Bereits vorhandene reservierte Demo-Adressen
blockieren das Anlegen vollständig; dafür eine leere Demo-Installation nutzen.

Er sendet selbst keine E-Mails. Bei manuellen Buchungen ruft der Testmodus
weiterhin `wp_mail()` mit synthetischen Empfängern auf; für die Demo einen
lokalen Mailfänger verwenden.

Website-Titel, Theme, Seiten, Datenschutzerklärung und WordPress-Benutzer
gehören zur Demo-Installation und werden vom Plugin-Seed nicht verändert.
Der Mail-Absendername stammt weiterhin aus dem Website-Titel. Auch dort
ausschließlich neutrale Bezeichnungen und synthetische Daten verwenden.

Quellcodeprüfung: `php tests/neutral-demo-smoke.php` prüft die synthetischen
Seed-Werte und neutralisierten Quelldateien. Die Tests
`tests/demo-seed-integration.php`, `tests/demo-lifecycle-integration.php` und
`tests/demo-admin-integration.php` prüfen mit `wp eval-file` eine ausdrücklich
mit `FLZ_EST_DEMO_TEST=true` markierte, entbehrliche WordPress-Installation.
Sie benötigen jeweils einen frischen Plugin-Datenbestand und verwenden
ausschließlich synthetische Testdaten. Browsernachweise bleiben separat.

Erforderliche Plugins: `flz_wpdb_objects` und `flz_ui_components`. Der aktuelle
Härtungs- und Migrationsstand steht in `ROADMAP.md`.

Prüfung: `./scripts/check-fast`. WordPress-, Datenbank-, Mail- und UI-Verhalten
muss zusätzlich über die lokale DDEV-Instanz verifiziert werden.

Commit-, CI- und Coverage-Gates sind in Übernahmephase 2 enforced. Ein Release
bleibt bis zur ausgefüllten Abnahme sowie zur Installation, zum Upgrade und
zum Rückbau aus dem exakten Artefakt blockiert. Das ausfüllbare
[Abnahmeprotokoll](docs/manual-acceptance.md) führt Installation, Upgrade,
Parallelbuchung, CSV, Datenschutz, Mail, Oberfläche und Rückbau zusammen.

Ein sauberer Commit wird reproduzierbar paketiert mit:

```bash
./scripts/build-release /tmp/flz_elternsprechtag-release
```

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

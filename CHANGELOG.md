# Changelog

Alle wesentlichen Änderungen an `flz_elternsprechtag` werden in dieser Datei
dokumentiert. Ein Datum wird erst bei einer tatsächlichen Veröffentlichung
ergänzt.

## Unreleased

## 1.2.0-rc.14

- Bearbeitbare Mailtexte und der Hinweis auf der Buchungsseite verwenden jetzt
  den kompakten WordPress-Editor; die Buchungsmails werden sicher als HTML
  versendet.

## 1.2.0-rc.13

- Die Betreff- und Textfelder jeder E-Mail-Vorlage stehen in den Einstellungen
  stets direkt untereinander.

## 1.2.0-rc.12

- Die Buchungsseite weist deutlich auf die notwendige Bestätigung per E-Mail
  hin; der Hinweis ist in den EST-Einstellungen bearbeitbar.

## 1.2.0-rc.11

- Betreff und Klartextinhalt beider Buchungs-E-Mails sind in den Einstellungen
  mit geprüften Platzhaltern bearbeitbar.
- Die Einstellungsspeicherung verwendet für Vorlagen `LONGTEXT`; bestehende
  Einstellungen bleiben beim additiven Upgrade erhalten.

## 1.2.0-rc.10

- Der Lehrkraft-Hinweis ist nun ein breites Editor-Detailfeld in der jeweiligen
  bearbeitbaren Tabellenzeile und wird mit deren regulärem Save-Button gespeichert.

## 1.2.0-rc.9

- Lehrkräfte können einen formatierten Hinweis für die Terminbuchung pflegen;
  dieser erscheint nach der Auswahl der Lehrkraft vor den freien Terminen.

## 1.2.0-rc.8

- Die Datenschutzbestätigung der öffentlichen Terminbuchung akzeptiert nun
  kompatibel die gültigen Checkboxwerte der UI-Komponenten und speichert sie
  zuverlässig.

## 1.2.0-rc.7

- Alle EST-CSV-Importe verwenden den gemeinsamen, einmalig konsumierbaren
  Prüfstand aus `flz_wpdb_objects`.

## 1.2.0-rc.6

- Termin-CSV: geprüfte Daten werden pro Admin höchstens 15 Minuten temporär
  vorgehalten und nach separater Bestätigung ohne erneute Dateiauswahl
  importiert; nach Import oder fehlgeschlagener Bestätigung werden sie gelöscht.

## 1.2.0-rc.5

- Termin- und Lehrkräfte-CSV-Importe zeigen sichere, konkrete Hinweise für
  Validierungs- und Uploadfehler; unerwartete technische Fehler nennen
  handlungsfähige nächste Schritte ohne interne Details preiszugeben.

## 1.2.0-rc.4

- Für die sichtbare Odd-/Even-Markierung editierbarer Tabellenzeilen wird
  `flz_ui_components` ab Version `0.2.1-rc.2` benötigt.

## 1.2.0-rc.3

- Die Prüfung des nächsten Elternsprechtags parst das gespeicherte Datum
  `TT.MM.JJ` strikt als Kalendertag; Monatswechsel werden nicht mehr als
  Uhrzeit am heutigen Tag fehlinterpretiert.
- Termin- und Lehrkräftetabelle markieren ihre logischen Datenzeilen wieder
  abwechselnd, unabhängig von aufklappbaren Detailzeilen.
- WordPress-Integrationstest-Infrastruktur mit isolierter Datenbank und
  eigenem CI-Job ergänzt.

## 1.2.0-rc.2

- Schulgebundene Texte durch neutrale Plugin-, Buchungs- und Kontakttexte ersetzt.
- Synthetische Demoseeds mit eigenem Herkunftsnachweis, idempotentem Anlegen
  und bestätigtem Entfernen über die Einstellungen ergänzt. Veränderte oder
  zusätzlich referenzierte Demodaten blockieren die Entfernung vollständig.
- Frühere Einstellungen werden beim Entfernen wiederhergestellt, spätere
  manuelle Änderungen bleiben erhalten. Die Seed-Terminsuche verwendet
  vollständige Zeitstempel statt einer fehleranfälligen Datumsinterpretation.
- WordPress-Integrationstests für Seed-Lebenszyklus und geschützte Adminaktionen ergänzt.
- Den übermittelten Wert der Einwilligungscheckbox mit der serverseitigen
  Elternvalidierung abgeglichen, damit gültige Buchungen abgeschlossen werden.
- Reproduzierbare PR-/Main-CI für PHP 8.1 und 8.5 ergänzt.
- Branchgleicher Checkout beider Shared-Plugins mit sicherem `main`-Fallback
  ergänzt.
- Formale Lizenz- und Abnahmenachweise in den Delivery-Vertrag aufgenommen.
- PHP-No-Regression-Ratsche bei 10,69 Prozent remote enforced.
- Reproduzierbaren Ein-Wurzel-ZIP-Bau mit Manifest, SHA-256 und CI-Prüfung
  ergänzt.

## 1.1.0

- Additive, idempotente Migration der vier Legacy-Tabellen ergänzt.
- Deaktivierung auf vollständigen Datenerhalt umgestellt.
- Geschützte CSV-Roundtrips mit Dry-Run sowie WordPress-Datenschutzwerkzeuge
  ergänzt.
- Buchungs-, Validierungs-, Mail- und Aufbewahrungsgrenzen gehärtet.

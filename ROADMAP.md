# Roadmap: flz_elternsprechtag

## Prüfstatus

**Teilweise regelkonform / P1.** Die unmittelbaren P0-Risiken bei Deaktivierung,
CSV-Downloads, Testmail und Parallelbuchung sind im Code abgesichert. Die
Komponenten-Smokes, PHPCS sowie die lokale WordPress-Einbindung wurden geprüft;
ein PHPUnit-Runner, echte Zwei-Prozess- und vollständige Datenbank-
Integrationstests fehlen noch.

## P0

1. **Codevertrag erledigt:** Deaktivierung löscht keine Tabellen, Rollen oder
   Capabilities mehr; ein Komponenten-Smoke verhindert die destruktiven
   Aufrufe. Die vollständige Aktivieren–Deaktivieren–Aktivieren-Abnahme mit
   synthetischen Bestandsdaten bleibt als DDEV-Integrationstest offen.
2. **Codevertrag erledigt:** Lehrkräfte- und Terminexport verwenden einen
   Capability- und nonce-geschützten Direktdownload; der Elternsprechtag legt
   keine neuen Exportdateien im Upload-Verzeichnis an. Die beiden lokal
   vorhandenen Altdateien wurden entfernt und liefern anschließend HTTP 404.
3. **Erledigt:** Der Testmodus verwendet ausschließlich den reservierten,
   synthetischen Empfänger `private-test@example.test`; ein Smoke-Test sperrt
   Gmail-/Googlemail-Adressen. Lokale Zustellung wird über DDEV-Mailpit geprüft.
4. **Codevertrag erledigt:** Elternspeicherung und Terminbelegung laufen in
   einer Transaktion; das Termin-Update greift atomar nur bei weiterhin leerer
   `parent_id`. Ein Konkurrenztest belegt Erfolgs- und Konfliktfall. Ein echter
   Zwei-Prozess-DDEV-Test bleibt als Integrationsnachweis offen.

## P1

1. **Codevertrag erledigt:** Lehrkräfte sowie Termine/Buchungen besitzen einen
   versionierten v1-Roundtrip mit obligatorischem Dry-Run, datei- und
   benutzergebundenem Prüfnachweis, Referenzprüfung und atomarem Import.
   Lehrkräfte werden nichtdestruktiv aktualisiert/ergänzt; Termine verlangen
   einen vollständigen Snapshot. Ein manueller Import mit synthetischem
   Komplettbestand, die Produktentscheidung zum früheren vier-spaltigen
   Vorbelegungsformat sowie ein Portabilitätspfad für Einstellungen bleiben
   offen.
2. Schema-Version und additive, idempotente Upgrades einführen; bereits
   ausgelieferte Migrationen nicht verändern. Frischinstallation, Upgrade,
   Wiederholung und ungültige Altdaten testen.
3. Aufbewahrung, Auskunft, Bestätigung, Widerruf, Anonymisierung und Löschung
   der Eltern-/Kind-/Lehrkräftedaten fachlich festlegen und WordPress-Privacy-
   Exporter/-Eraser ergänzen.
4. E-Mail- und Bestätigungstoken-Vertrag härten: Absender konfigurierbar,
   Adressen validiert, Tokens nach Nutzung gelöscht, Fehler ohne Personen- oder
   Tokenwerte protokolliert.
5. **Erledigt:** Direkte Includes aus Geschwister-Plugins entfernt; verzögerter
   Bootstrap prüft beide öffentlichen APIs und Mindestversionen defensiv.
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

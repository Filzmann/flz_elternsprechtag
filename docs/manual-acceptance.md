# Manuelles Abnahmeprotokoll – FLZ Elternsprechtag

Dieses Formular dokumentiert fachliche, visuelle und technische Abnahme des
exakten Release-Artefakts. Nur synthetische Eltern-, Kind- und Lehrkräftedaten
verwenden. Pro Fall genau ein Ergebnis markieren und Abweichungen begründen.

## Kopfdaten

| Feld | Eintrag |
|---|---|
| Datum / Prüfer*in | |
| Umgebung / WordPress / PHP / Datenbank | |
| Browser / Version / Viewport / Zoom | |
| Plugin-Version / vollständiger Git-Commit | |
| Ausgangsversion / Artefakt / SHA-256 | |
| DDEV-Snapshot / Rückbaupunkt | |

## Automatisierte Nachweise

| Nachweis | Kommando / Lauf | Ergebnis / Beleg |
|---|---|---|
| PR-/`main`-CI / PHP 8.1 und 8.5 | Workflow-Lauf / vollständiger Commit | |
| Komponenten-, Security- und Migrations-Smokes | `./scripts/check-fast` | |
| PHP-Line-Coverage / Baseline / Ziel 85 % | | |
| Shared-Provider-/Consumer-Verträge | | |
| Reproduzierbarkeit und Archivinhalt | | |

## Manuelle Prüffälle

### Technischer ZIP-Teilnachweis vom 22. August 2026

- Umgebung: DDEV, WordPress 7.1, PHP 8.3, MariaDB 10.11.
- Exaktes Artefakt: `flz_elternsprechtag-1.1.0.zip`, Commit
  `862320bbd4938ac7357c45b9132097edf56c2cad`, SHA-256
  `e26eb063d08b5b490e4c755843a74a615807a257fa79192347aa6504f277eb85`.
- Reproduzierbarkeit, Archivvertrag und installierter Dateibaum sowie
  WP-CLI-Installation, Aktivstatus, Deaktivierung, Reaktivierung und HTTP 200
  waren erfolgreich. Snapshot- und Symlink-Rückbau waren erfolgreich.
- Noch nicht belegt: saubere Frischinstallation, Upgrade aus der relevanten
  Vorversion mit synthetischem Bestand sowie EST-03 bis EST-09.

| ID | Prüfschritte | Erwartetes Ergebnis | Ergebnis | Warum / Beleg / Abweichung |
|---|---|---|---|---|
| EST-01 | Frischinstallation und Upgrade aus der relevanten Vorversion mit synthetischem Bestand durchführen. | Schema 2.0.0 ist vollständig; Daten und Beziehungen bleiben erhalten; Wiederholung ist idempotent. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| EST-02 | Deaktivieren, Bestand/Rolle/Capability prüfen und erneut aktivieren. | Keine Tabelle, Rolle, Capability oder Buchung wird gelöscht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| EST-03 | Letzten freien Slot nahezu gleichzeitig über zwei getrennte Requests buchen. | Höchstens eine Buchung entsteht; der abgewiesene Request hinterlässt keine Eltern-/Termin-Nebenwirkung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| EST-04 | Adminaktion berechtigt, unberechtigt und mit manipuliertem Nonce ausführen. | Nur berechtigte, bestätigte Aktion mutiert Daten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| EST-05 | Lehrkräfte- und Termin-CSV exportieren, Dry-Run und unveränderten Import prüfen; Datei danach verändern. | Roundtrip ist atomar; veränderte oder nicht geprüfte Datei wird abgewiesen; keine öffentliche Datei entsteht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| EST-06 | WordPress-Privacy-Export und -Löschung mit neutraler Adresse ausführen. | Auskunft ist vollständig; Eltern-/Kinddaten werden gelöscht, Termine freigegeben, Lehrkraft strukturerhaltend anonymisiert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| EST-07 | Aufbewahrung ausgeschaltet sowie bewusst aktiviert/manuell bestätigt prüfen. | Standard erzeugt keinen Cron; Löschung erfolgt nur nach dokumentierter Aktivierung beziehungsweise Bestätigung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| EST-08 | Test- und normale Mail in Mailpit prüfen. | Empfänger/Absender sind korrekt; Links funktionieren; keine internen Details oder Tokens erscheinen in Logs. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| EST-09 | Mehrschrittbuchung mobil und nur per Tastatur bedienen; Fehlerfälle auslösen. | Fokus, Labels, Fehler und Fortschritt sind verständlich; kein horizontales Seitenscrollen oder Tastaturfalle. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| EST-10 | Rückbau auf dokumentierten Snapshot beziehungsweise Vorartefakt durchführen. | Ausgangscode und dokumentierter Datenstand sind nachvollziehbar wiederherstellbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Erfolgreich / nicht erfolgreich / nicht geprüft | |
| Kritische Abweichungen / Tickets | |
| Datenschutz und Rückbau freigegeben | [ ] ja [ ] nein |
| Gesamtentscheidung | [ ] abgenommen [ ] mit Auflagen abgenommen [ ] nicht abgenommen |
| Name / Datum | |

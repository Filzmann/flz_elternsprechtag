# Roadmap: flz_elternsprechtag

## Prüfstatus

**Funktionsstand 1.1.0 ohne offene P0-Befunde; Release-Gate in
Übernahmephase 1 blockiert.** Deaktivierung,
geschützte CSV-Roundtrips, Testmail, Parallelbuchung, additive Legacy-Migration,
Datenschutz und Terminvalidierung sind im Code abgesichert. Komponenten-Smokes,
PHPCS, echte lokale Bestandsmigration und Aktivieren–Deaktivieren–Aktivieren
wurden geprüft. PR-/Main-CI mit branchgleichen Shared-Plugins ist lokal
konfiguriert; vor einem Tag fehlen ein grüner Remote-Lauf, gemessene
No-Regression-Coverage, ausgefüllte Browser-/Mail-/Zwei-Prozess-Abnahme und ein
reproduzierbar geprüftes Release-Artefakt. Das Protokoll liegt unter
`docs/manual-acceptance.md`.

## P0

1. **Codevertrag erledigt:** Deaktivierung löscht keine Tabellen, Rollen oder
   Capabilities mehr; ein Komponenten-Smoke verhindert die destruktiven
   Aufrufe. Die Aktivieren–Deaktivieren–Aktivieren-Abnahme erhielt lokal
   85 Lehrkräfte, 5 Elternteile und 1004 Termine sowie Rolle und Capability.
2. **Codevertrag erledigt:** Lehrkräfte- und Terminexport verwenden einen
   Capability- und nonce-geschützten Direktdownload; der Elternsprechtag legt
   keine neuen Exportdateien im Upload-Verzeichnis an. Die beiden lokal
   vorhandenen Altdateien wurden entfernt und liefern anschließend HTTP 404.
3. **Erledigt:** Der Testmodus verwendet ausschließlich den reservierten,
   synthetischen Empfänger `private-test@example.test`; ein Smoke-Test sperrt
   Gmail-/Googlemail-Adressen. Eine visuelle Zustellungsabnahme in Mailpit
   bleibt offen.
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
2. **Erledigt:** DB-Version 2.0.0 und additive, idempotente Migration übernehmen
   Legacy-Bestände; Alt-Tabellen bleiben erhalten. Frischinstallation,
   Wiederholung, Konfliktabbruch und der reale lokale Bestand wurden geprüft.
3. **Erledigt:** Aufbewahrung ist standardmäßig aus, 24 Monate sind der
   konfigurierbare Vorschlag. WordPress-Exporter/-Eraser sowie bestätigte
   manuelle und optionale geplante Löschung sind vorhanden.
4. **Erledigt:** Absender und Empfänger werden validiert, der Absender ist
   konfigurierbar, verwendete Tokens werden entfernt und Logs enthalten weder
   Personen- noch Tokenwerte.
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
3. **Erledigt:** Terminrahmen und Slotlänge werden fachlich validiert; die
   Berechnung verwendet die WordPress-Zeitzone und ist strikt begrenzt.
4. Öffentliche Mehrschrittbuchung in DDEV per Tastatur, mobil, mit sichtbaren
   Fehlern und abgelaufenen/manipulierten Links prüfen.

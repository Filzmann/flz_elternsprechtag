# Automatisierte Demo-Prüfung – 8. September 2026

Geprüft wurde der uncommittete Arbeitsstand auf `feature/neutral-demo-seeds`,
ausgehend von `0aa3b1aa7edc3ea78b927020662eb94f4b302125`.
Die UI-Abhängigkeit basiert auf `53d85dc772bfe814a057205ec4c587f74c255ac3`
mit neutralisierten Anzeigetexten; `flz_wpdb_objects` ist unverändert bei
`47fd02ed900dd2df5039a0e60ac795c0dfd6372d`.
Dies ist ein Quellstandnachweis, keine Abnahme eines Release-Artefakts.

## Umgebung

Separate lokale Docker-Testcontainer, frische WordPress-Installation 7.1,
PHP 8.3.21, MariaDB 10.11, WordPress-Zeitzone Europe/Berlin.
Die Testinstallation verwendet ausschließlich synthetische Personen und
Adressen unter `example.test`. Die bestehende lokale WordPress-Installation
wurde weder gestartet noch mit Datenimporten verändert.

## Bestandene Prüfungen

- Workspace `scripts/check-fast`: Komponenten-Smokes, PHP-Syntax, PHPCS,
  JavaScript und Provider-/Consumer-Verträge.
- `wp eval-file tests/demo-seed-integration.php`: drei Demo-Lehrkräfte,
  zwei Elternteile mit Kinddaten, 18 Slots und zwei bestätigte Buchungen;
  synthetischer Absender, Testmodus, Wiederholung ohne Duplikate und korrekte
  Anzeige von Datum und WordPress-Zeitzone.
- `wp eval-file tests/demo-lifecycle-integration.php`: wiederholtes Anlegen,
  Entfernen und erneutes Anlegen; Erhalt fremder Datensätze; Rückgabe früherer
  Einstellungswerte und Erhalt manueller Änderungen. Bearbeitete Demo-Personen,
  zusätzliche referenzierende Termine und unregistrierte Adresskollisionen
  werden ohne unerlaubte Nebenwirkungen abgewiesen. Ein gezielt ausgelöster
  Datenbankfehler nach Beginn der Löschung rollt die Änderungen vollständig
  zurück und erhält den Herkunftsnachweis.
- `wp eval-file tests/demo-admin-integration.php`: Einstellungsaktionen mit
  echten WordPress-Berechtigungen und Nonces; Ablehnung ohne Berechtigung,
  mit falscher Nonce, ohne Löschbestätigung, mit falschem Bestätigungswert
  und bei kombinierten Aktionen. Die Löschcheckbox ist anfänglich leer.
- Chrome über DevTools: Anlegen und wiederholtes Anlegen über Einstellungen,
  Lehrkraft-/Terminauswahl, Feldlabels, Tab-Navigation, Fehler bei leerer
  Eingabe, neutrale Texte, mobile Darstellung bei 390 Pixeln ohne horizontales
  Scrollen sowie bestätigtes und wiederholtes Entfernen über Einstellungen.
- Deaktivierung/Reaktivierung: Datensätze, Seed-Herkunftsnachweis, Rolle und
  Capability bleiben erhalten; erneute Aktivierung ist erfolgreich.
- WordPress-Privacy-Exporter/-Eraser: Auskunft zu synthetischer Buchung,
  Löschung von Eltern-/Kinddaten mit Freigabe der Termine sowie Anonymisierung
  einer Lehrkraft ohne Verlust ihrer Termine. Aufbewahrung bleibt standardmäßig aus.
- Zwei getrennte Buchungsprozesse für denselben freien Slot: genau eine
  Buchung und ein Konflikt; kein zusätzlicher Elterndatensatz des Verlierers.
  Anschließendes Entfernen der Seeds schützt die neue Buchung und bricht ab.

Der neue Lebenszyklus in `includes/demo-lifecycle.php` wurde mit Xdebug und
PHPCOV im echten WordPress-Test gemessen: 72 von 76 ausführbaren Zeilen,
94,74 Prozent. Dies ist eine gezielte Messung, keine Gesamtquote des Plugins.
Die abschließenden Smoke-Coverage-Ratschen bestehen ebenfalls:
Elternsprechtag 13,24 Prozent PHP (Baseline 10,69), Datenbank-Plugin 58,24
Prozent PHP (58,18), UI-Plugin 55,11 Prozent PHP (54,34) und 54,05 Prozent
JavaScript (54,05).
Der vorhandene PHPUnit-Test wurde mit PHP 8.3 ausgeführt; seine neun Methoden
enthalten keine Assertions und werden als risky gemeldet. Er liefert keinen
zusätzlichen fachlichen Nachweis.

## Behobene Fehler

- Seed-Abbruch durch mehrdeutige Interpretation eines deutschen Kurzdatums;
  Termine werden anhand ihrer vollständigen Zeitstempel gefunden.
- Fehlende Deinstallation der Demo-Daten; jetzt mit Herkunftsnachweis,
  Konfliktschutz und Rückbau der unveränderten Demo-Einstellungen.
- Voreingestellte Löschcheckbox durch falsches Renderer-Argument;
  jetzt ausdrücklich leer, mit sichtbaren Aktionsbeschriftungen.
- UTC-Uhrzeiten und mehrdeutig umgewandelte Datumsanzeige;
  jetzt WordPress-Zeitzone und unveränderte validierte Datumseinstellung.

## Verbleibende Grenzen

Die Prüfungen erfolgten aus kopiertem Quellcode, nicht aus dem abschließenden
ZIP. Noch offen sind saubere Commits, Remote-CI für diese Änderungen,
reproduzierbarer Paketbau und Installation/Upgrade/Rückbau aus genau diesen
Archiven sowie die vollständige Gesamtentscheidung der Release-Abnahme.
Ein realer Mailtransport, Screenreader und eine vollständige visuelle
Barrierefreiheitsabnahme wurden nicht nachgewiesen. Die bestehenden
Release-Gates bleiben deshalb unverändert blockiert.

Frühere Demo-Daten ohne Herkunftsnachweis werden bewusst nicht automatisch
übernommen oder gelöscht. Urheberangaben und technische IDs der
Abhängigkeiten bleiben erhalten.

Keine Learning Candidates wurden als neue Regeln übernommen.

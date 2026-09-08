# Changelog

Alle wesentlichen Änderungen an `flz_elternsprechtag` werden in dieser Datei
dokumentiert. Ein Datum wird erst bei einer tatsächlichen Veröffentlichung
ergänzt.

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

# Changelog

Alle wesentlichen Änderungen an `flz_elternsprechtag` werden in dieser Datei
dokumentiert. Ein Datum wird erst bei einer tatsächlichen Veröffentlichung
ergänzt.

## Unreleased

- Reproduzierbare PR-/Main-CI für PHP 8.1 und 8.5 ergänzt.
- Branchgleicher Checkout beider Shared-Plugins mit sicherem `main`-Fallback
  ergänzt.
- Formale Lizenz- und Abnahmenachweise in den Delivery-Vertrag aufgenommen.
- Gepinnten PHPCOV-/Xdebug-Messjob für eine ehrliche PHP-Baseline ergänzt.

## 1.1.0

- Additive, idempotente Migration der vier Legacy-Tabellen ergänzt.
- Deaktivierung auf vollständigen Datenerhalt umgestellt.
- Geschützte CSV-Roundtrips mit Dry-Run sowie WordPress-Datenschutzwerkzeuge
  ergänzt.
- Buchungs-, Validierungs-, Mail- und Aufbewahrungsgrenzen gehärtet.

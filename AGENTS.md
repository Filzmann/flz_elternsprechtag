# Komponentenregeln: flz_elternsprechtag

Zusätzlich gelten die Root-Regeln und die dortigen Skills
`work-in-wordpress-extension` und `test-driven-wordpress-change`.

Dieses Fachplugin verwaltet Lehrkräfte, Eltern und Termine des
Elternsprechtags. Terminvergabe, personenbezogene Daten, Exporte,
Berechtigungen und Schemaänderungen sind Risikogrenzen. Abhängigkeiten zu
`flz_wpdb_objects` und `flz_ui_components` nur über deren öffentliche
Verträge verwenden. `tests/flzEstAppointmentTest.php` ist ein PHPUnit-Test,
für den im Repository derzeit kein Runner installiert ist; bis zur
Einrichtung diese Nachweislücke benennen. WordPress-/Datenbankverhalten
zusätzlich in DDEV prüfen.

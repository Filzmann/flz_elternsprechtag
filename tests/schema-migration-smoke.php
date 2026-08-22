<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname(__DIR__);
$bootstrap = file_get_contents($root . '/flz_elternsprechtag.php');
$activation = file_get_contents($root . '/activate-deactivate.php');
$appointment = file_get_contents($root . '/classes/FlzEstAppointment.php');
$migration_file = $root . '/includes/class-flz-est-schema-migrator.php';

if (false === $bootstrap || false === $activation || false === $appointment) {
	throw new RuntimeException('Die Elternsprechtag-Schemaquellen konnten nicht gelesen werden.');
}
if (!is_file($migration_file)) {
	throw new RuntimeException('Der versionierte Elternsprechtag-Schema-Migrator fehlt.');
}
$migration = file_get_contents($migration_file);

foreach (
	array(
		"const FLZ_EST_VERSION = '1.1.0'" => $bootstrap,
		"const FLZ_EST_DB_VERSION = '2.0.0'" => $bootstrap,
		'FlzEstSchemaMigrator::maybe_upgrade()' => $activation,
		"get_option('flzest_db_version'" => $migration,
		"update_option('flzest_db_version'" => $migration,
		"'flzestteachers'" => $migration,
		"'flzestparents'" => $migration,
		"'flzestappointments'" => $migration,
		"'flzestsettings'" => $migration,
	) as $needle => $source
) {
	if (!is_string($source) || !str_contains($source, $needle)) {
		throw new RuntimeException('Der additive Elternsprechtag-Migrationsvertrag fehlt: ' . $needle);
	}
}

if (str_contains((string) $migration, 'DROP TABLE') || str_contains((string) $migration, 'delete_table')) {
	throw new RuntimeException('Der Elternsprechtag-Migrator entfernt den Legacy-Rückbaupfad.');
}
if (str_contains($appointment, 'FOREIGN KEY')) {
	throw new RuntimeException('Das portable dbDelta-Schema enthält weiterhin nicht zuverlässig aktualisierbare Fremdschlüssel.');
}

echo "OK: flz_elternsprechtag schema migration smoke test\n";


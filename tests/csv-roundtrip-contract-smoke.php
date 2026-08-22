<?php

declare(strict_types=1);

// Exception-Texte sind ausschließlich lokale CLI-Testdiagnostik.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

function sanitize_text_field( $value ): string {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_email( $value ): string {
	return filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL );
}

function is_email( $value ): string|false {
	return filter_var( (string) $value, FILTER_VALIDATE_EMAIL );
}

require dirname( __DIR__ ) . '/includes/csv-contract.php';

$assert_throws = static function ( callable $callback, string $expected_fragment ): void {
	try {
		$callback();
	} catch ( UnexpectedValueException $error ) {
		if ( ! str_contains( $error->getMessage(), $expected_fragment ) ) {
			throw new RuntimeException( 'Unerwartete Fehlermeldung: ' . $error->getMessage() );
		}
		return;
	}

	throw new RuntimeException( 'Erwarteter Fehler blieb aus: ' . $expected_fragment );
};

$teacher_rows = array(
	flzest_teacher_csv_header(),
	array( '1', 'teacher', 'f', 'Beispiel', 'Ada', 'ada.teacher@example.test' ),
);
$teachers = flzest_parse_teacher_csv( $teacher_rows );
if ( 'ada.teacher@example.test' !== $teachers[0]['email'] ) {
	throw new RuntimeException( 'Gültige Lehrkräftezeile wurde nicht verlustfrei gelesen.' );
}

$wrong_teacher_version = $teacher_rows;
$wrong_teacher_version[1][0] = '2';
$assert_throws(
	static fn() => flzest_parse_teacher_csv( $wrong_teacher_version ),
	'Version'
);

$duplicate_teachers = array_merge( $teacher_rows, array( $teacher_rows[1] ) );
$assert_throws(
	static fn() => flzest_parse_teacher_csv( $duplicate_teachers ),
	'doppelt'
);

$wrong_header = $teacher_rows;
$wrong_header[0][2] = 'unexpected';
$assert_throws(
	static fn() => flzest_parse_teacher_csv( $wrong_header ),
	'Kopfzeile'
);

$appointment_rows = array(
	flzest_appointment_csv_header(),
	array(
		'1',
		'appointment',
		'ada.teacher@example.test',
		'1800000000',
		'1800001200',
		'f',
		'Beispiel',
		'Eva',
		'eva.parent@example.test',
		'Kind Beispiel',
		'7.1',
		'yes',
		'1',
	),
	array(
		'1',
		'appointment',
		'ada.teacher@example.test',
		'1800001200',
		'1800002400',
		'', '', '', '', '', '', '', '0',
	),
);
$appointments = flzest_parse_appointment_csv( $appointment_rows );
if ( true !== $appointments[0]['booked'] || false !== $appointments[1]['booked'] ) {
	throw new RuntimeException( 'Belegte und freie Termine wurden nicht korrekt unterschieden.' );
}

$invalid_period = $appointment_rows;
$invalid_period[1][4] = $invalid_period[1][3];
$assert_throws(
	static fn() => flzest_parse_appointment_csv( $invalid_period ),
	'Ende'
);

$incomplete_parent = $appointment_rows;
$incomplete_parent[1][8] = '';
$assert_throws(
	static fn() => flzest_parse_appointment_csv( $incomplete_parent ),
	'Eltern-E-Mail'
);

$duplicate_appointments = array_merge( $appointment_rows, array( $appointment_rows[1] ) );
$assert_throws(
	static fn() => flzest_parse_appointment_csv( $duplicate_appointments ),
	'doppelt'
);

$teacher_backend = file_get_contents( dirname( __DIR__ ) . '/backend/teachers.php' );
if ( false === $teacher_backend ) {
	throw new RuntimeException( 'Der Lehrkräfte-Importcontroller konnte nicht gelesen werden.' );
}
foreach (
	array(
		'flzest_teacher_csv_header()',
		"flz_wpdb_objects_read_uploaded_csv( 'teacher-csv', 'Importieren der Lehrkräfte-CSV-Datei', false )",
		"isset( \$_POST['submit_csv_dry_run'] )",
		'flzest_teacher_csv_proof_hash',
		'FlzWpdbTransaction::run',
	) as $required_contract
) {
	if ( ! str_contains( $teacher_backend, $required_contract ) ) {
		throw new RuntimeException( 'Lehrkräfte-Roundtripvertrag fehlt: ' . $required_contract );
	}
}
if ( str_contains( $teacher_backend, '$existing_teacher->delete()' ) ) {
	throw new RuntimeException( 'Der Lehrkräfte-Import löscht weiterhin nicht aufgeführte Bestandsdaten.' );
}

$appointment_backend = file_get_contents( dirname( __DIR__ ) . '/backend/appointments.php' );
if ( false === $appointment_backend ) {
	throw new RuntimeException( 'Der Termin-Importcontroller konnte nicht gelesen werden.' );
}
foreach (
	array(
		'flzest_appointment_csv_header()',
		"flz_wpdb_objects_read_uploaded_csv( 'appointments-csv', 'Importieren der Elternsprechtagstermine aus CSV', false )",
		"isset( \$_POST['submit_csv_dry_run'] )",
		'flzest_appointment_csv_proof_hash',
		'FlzWpdbTransaction::run',
		'$appointment->confirmationToken = null',
	) as $required_contract
) {
	if ( ! str_contains( $appointment_backend, $required_contract ) ) {
		throw new RuntimeException( 'Termin-Roundtripvertrag fehlt: ' . $required_contract );
	}
}

echo "OK: flz_elternsprechtag CSV roundtrip contract smoke test\n";

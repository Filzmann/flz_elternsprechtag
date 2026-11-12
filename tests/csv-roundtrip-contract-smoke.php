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
	array( '550e8400-e29b-41d4-a716-446655440000', 'f', 'Beispiel', 'Ada', 'ada.teacher@example.test' ),
);
$expected_teacher_header = array( 'Datensatz-ID', 'gender', 'last_name', 'first_name', 'email' );
if ( flzest_teacher_csv_header() !== $expected_teacher_header ) {
	throw new RuntimeException( 'Der Lehrkräfte-Export enthält weiterhin technische Metadatenspalten.' );
}
$teachers = flzest_parse_teacher_csv( $teacher_rows );
if ( 'ada.teacher@example.test' !== $teachers[0]['email'] ) {
	throw new RuntimeException( 'Gültige Lehrkräftezeile wurde nicht verlustfrei gelesen.' );
}
if ( '550e8400-e29b-41d4-a716-446655440000' !== $teachers[0]['record_uuid'] ) { throw new RuntimeException( 'Die Lehrkraft-ID geht verloren.' ); }
$previous_teacher_rows = array( array_slice( flzest_teacher_csv_header(), 1 ), array_slice( $teacher_rows[1], 1 ) );
if ( '' !== flzest_parse_teacher_csv( $previous_teacher_rows )[0]['record_uuid'] ) { throw new RuntimeException( 'Das bisherige CSV-Format wird nicht gelesen.' ); }
$invalid_uuid = $teacher_rows;
$invalid_uuid[1][0] = 'ungueltig';
$assert_throws( static fn() => flzest_parse_teacher_csv( $invalid_uuid ), 'Datensatz-ID' );

$legacy_teacher_rows = array(
	array( 'format_version', 'record_type', 'gender', 'last_name', 'first_name', 'email' ),
	array( '1', 'teacher', 'f', 'Beispiel', 'Ada', 'ada.teacher@example.test' ),
);
if ( 'ada.teacher@example.test' !== flzest_parse_teacher_csv( $legacy_teacher_rows )[0]['email'] ) {
	throw new RuntimeException( 'Ein älteres Lehrkräfte-Backup ist nicht mehr importierbar.' );
}

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
		'550e8400-e29b-41d4-a716-446655440001',
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
		'550e8400-e29b-41d4-a716-446655440002',
		'ada.teacher@example.test',
		'1800001200',
		'1800002400',
		'', '', '', '', '', '', '', '0',
	),
);
$expected_appointment_header = array( 'Datensatz-ID', 'teacher_email', 'start_timestamp', 'end_timestamp', 'parent_gender', 'parent_last_name', 'parent_first_name', 'parent_email', 'student_name', 'student_class', 'gdpr_checked', 'confirmed' );
if ( flzest_appointment_csv_header() !== $expected_appointment_header ) {
	throw new RuntimeException( 'Der Termin-Export enthält weiterhin technische Metadatenspalten.' );
}
$appointments = flzest_parse_appointment_csv( $appointment_rows );
if ( true !== $appointments[0]['booked'] || false !== $appointments[1]['booked'] ) {
	throw new RuntimeException( 'Belegte und freie Termine wurden nicht korrekt unterschieden.' );
}

$invalid_period = $appointment_rows;
$invalid_period[1][3] = $invalid_period[1][2];
$assert_throws(
	static fn() => flzest_parse_appointment_csv( $invalid_period ),
	'Ende'
);

$incomplete_parent = $appointment_rows;
$incomplete_parent[1][7] = '';
$assert_throws(
	static fn() => flzest_parse_appointment_csv( $incomplete_parent ),
	'Eltern-E-Mail'
);

$duplicate_appointments = array_merge( $appointment_rows, array( $appointment_rows[1] ) );
$assert_throws(
	static fn() => flzest_parse_appointment_csv( $duplicate_appointments ),
	'doppelt'
);

$legacy_appointment_rows = array(
	array( 'format_version', 'record_type', 'teacher_email', 'start_timestamp', 'end_timestamp', 'parent_gender', 'parent_last_name', 'parent_first_name', 'parent_email', 'student_name', 'student_class', 'gdpr_checked', 'confirmed' ),
	array( '1', 'appointment', 'ada.teacher@example.test', '1800000000', '1800001200', '', '', '', '', '', '', '', '0' ),
);
if ( false !== flzest_parse_appointment_csv( $legacy_appointment_rows )[0]['booked'] ) {
	throw new RuntimeException( 'Ein älteres Termin-Backup ist nicht mehr importierbar.' );
}

$teacher_backend = file_get_contents( dirname( __DIR__ ) . '/backend/teachers.php' );
$teacher_template = file_get_contents( dirname( __DIR__ ) . '/templates/teachers.php' );
if ( false === $teacher_backend || false === $teacher_template ) {
	throw new RuntimeException( 'Lehrkräfte-Importcontroller oder -template konnte nicht gelesen werden.' );
}
foreach (
	array(
		'flzest_teacher_csv_header()',
		"flz_wpdb_objects_read_uploaded_csv( 'teacher-csv', 'Importieren der Lehrkräfte-CSV-Datei', false )",
		"isset( \$_POST['submit_csv'] )",
		"isset( \$_POST['confirm_teacher_csv_import'] )",
		"FlzCsvImportStage::store( 'flzest:teachers'",
		"FlzCsvImportStage::consume( 'flzest:teachers'",
		'flzest_teacher_csv_proof_hash',
		'flzest_plan_missing_teacher_deletions',
		'30 * DAY_IN_SECONDS',
		"'unchanged' =>",
		'Lehrkräfte bleiben unverändert',
		'Die vorhandenen Lehrkräftedaten enthalten die E-Mail-Adresse "%s" mehrfach.',
		'flzest_requested_protected_teacher_csv_deletions',
		'flzest_delete_teacher_csv_candidates',
		'FlzEstAppointment::get_all_by()',
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
if ( str_contains( $teacher_template, "'name' => 'csv_dry_run'" ) ) {
	throw new RuntimeException( 'Die Lehrkräfte-CSV bietet weiterhin eine entbehrliche Dry-Run-Auswahl an.' );
}
foreach (
	array(
		"'confirm_teacher_csv_import' => '1'",
		'button_save( array(',
		'Lehrkräfte-CSV prüfen',
		'aktualisiert oder neu angelegt',
		"'name' => 'delete_missing_teachers'",
		"'name' => 'delete_protected_missing_teachers[]'",
		'Geschützte Lehrkräfte einschließlich aller ihrer Termine endgültig löschen',
		'flzest-teacher-csv-removable',
		'flzest-teacher-csv-protected',
		'dashicons-trash',
		'dashicons-lock',
		'Löschkandidat aus letzter CSV-Prüfung',
		'Geschützt wegen eines künftigen oder höchstens 30 Tage zurückliegenden Termins',
	) as $required_teacher_template_contract
) {
	if ( ! str_contains( $teacher_template, $required_teacher_template_contract ) ) {
		throw new RuntimeException( 'Lehrkräfte-CSV-Oberflächenvertrag fehlt: ' . $required_teacher_template_contract );
	}
}

$appointment_backend = file_get_contents( dirname( __DIR__ ) . '/backend/appointments.php' );
$appointment_template = file_get_contents( dirname( __DIR__ ) . '/templates/appointments.php' );
if ( false === $appointment_backend || false === $appointment_template ) {
	throw new RuntimeException( 'Termin-Importcontroller oder -template konnte nicht gelesen werden.' );
}
foreach (
	array(
		'flzest_appointment_csv_header()',
		"flz_wpdb_objects_read_uploaded_csv( 'appointments-csv', 'Importieren der Elternsprechtagstermine aus CSV', false )",
		"isset( \$_POST['submit_csv'] )",
		"isset( \$_POST['confirm_csv_import'] )",
		"FlzCsvImportStage::store( 'flzest:appointments'",
		"FlzCsvImportStage::consume( 'flzest:appointments'",
		'flzest_appointment_csv_proof_hash',
		'FlzWpdbTransaction::run',
		'$appointment->confirmationToken = null',
	) as $required_contract
) {
	if ( ! str_contains( $appointment_backend, $required_contract ) ) {
		throw new RuntimeException( 'Termin-Roundtripvertrag fehlt: ' . $required_contract );
	}
}
foreach ( array( "'confirm_csv_import' => '1'", 'button_save( array(' ) as $required_appointment_submit_contract ) {
	if ( ! str_contains( $appointment_template, $required_appointment_submit_contract ) ) {
		throw new RuntimeException( 'Die Termin-CSV-Bestätigung ist kein verlässlicher Submit-Request: ' . $required_appointment_submit_contract );
	}
}

echo "OK: flz_elternsprechtag CSV roundtrip contract smoke test\n";

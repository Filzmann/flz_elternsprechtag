<?php

defined( 'ABSPATH' ) || defined( 'PHPUNIT_COMPOSER_INSTALL' ) || PHP_SAPI === 'cli' || exit;

// Validierungsfehler sind interne/fachliche Exception-Texte; Escaping erfolgt in der UI-Notice.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

const FLZEST_CSV_VERSION = '1';

/**
 * @return array<int,string>
 */
function flzest_teacher_csv_header(): array {
	return array( 'format_version', 'record_type', 'gender', 'last_name', 'first_name', 'email' );
}

/**
 * @return array<int,string>
 */
function flzest_appointment_csv_header(): array {
	return array(
		'format_version',
		'record_type',
		'teacher_email',
		'start_timestamp',
		'end_timestamp',
		'parent_gender',
		'parent_last_name',
		'parent_first_name',
		'parent_email',
		'student_name',
		'student_class',
		'gdpr_checked',
		'confirmed',
	);
}

function flzest_csv_cell( $value ): string {
	$value = trim( (string) $value );
	if ( str_starts_with( $value, "'" ) && preg_match( '/^[=+\-@]/', substr( $value, 1 ) ) ) {
		return substr( $value, 1 );
	}

	return $value;
}

/**
 * @param array<int,mixed> $actual
 * @param array<int,string> $expected
 */
function flzest_assert_csv_header( array $actual, array $expected ): void {
	$actual = array_map( 'flzest_csv_cell', $actual );
	if ( isset( $actual[0] ) ) {
		$actual[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $actual[0] ) ?? $actual[0];
	}
	if ( $actual !== $expected ) {
		throw new UnexpectedValueException( 'Die CSV-Kopfzeile entspricht nicht dem erwarteten versionierten Format.' );
	}
}

/**
 * @param array<int,array<int,mixed>> $rows CSV einschließlich Kopfzeile.
 * @return array<int,array{gender:string,name:string,firstName:string,email:string}>
 */
function flzest_parse_teacher_csv( array $rows ): array {
	if ( empty( $rows ) ) {
		throw new UnexpectedValueException( 'Die Lehrkräfte-CSV ist leer.' );
	}

	flzest_assert_csv_header( array_shift( $rows ), flzest_teacher_csv_header() );
	if ( empty( $rows ) ) {
		throw new UnexpectedValueException( 'Die Lehrkräfte-CSV enthält keine Datensätze.' );
	}

	$result = array();
	$emails = array();
	foreach ( $rows as $index => $raw_row ) {
		$line = $index + 2;
		if ( 6 !== count( $raw_row ) ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Die Spaltenzahl ist ungültig.' );
		}
		$row = array_map( 'flzest_csv_cell', $raw_row );
		if ( FLZEST_CSV_VERSION !== $row[0] ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Die Format-Version wird nicht unterstützt.' );
		}
		if ( 'teacher' !== $row[1] ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Der Datensatztyp ist ungültig.' );
		}
		$gender = sanitize_text_field( $row[2] );
		if ( ! in_array( $gender, array( '', 'm', 'f' ), true ) ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Das Geschlecht muss leer, m oder f sein.' );
		}
		$name = sanitize_text_field( $row[3] );
		if ( '' === $name ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Der Nachname fehlt.' );
		}
		$email = strtolower( sanitize_email( $row[5] ) );
		if ( '' === $email || ! is_email( $email ) ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Die E-Mail-Adresse ist ungültig.' );
		}
		if ( isset( $emails[ $email ] ) ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Die E-Mail-Adresse kommt doppelt vor.' );
		}
		$emails[ $email ] = true;
		$result[] = array(
			'gender'    => $gender,
			'name'      => $name,
			'firstName' => sanitize_text_field( $row[4] ),
			'email'     => $email,
		);
	}

	return $result;
}

/**
 * @param array<int,array<int,mixed>> $rows CSV einschließlich Kopfzeile.
 * @return array<int,array<string,mixed>>
 */
function flzest_parse_appointment_csv( array $rows ): array {
	if ( empty( $rows ) ) {
		throw new UnexpectedValueException( 'Die Termin-CSV ist leer.' );
	}

	flzest_assert_csv_header( array_shift( $rows ), flzest_appointment_csv_header() );
	if ( empty( $rows ) ) {
		throw new UnexpectedValueException( 'Die Termin-CSV enthält keine Datensätze.' );
	}

	$result = array();
	$keys = array();
	foreach ( $rows as $index => $raw_row ) {
		$line = $index + 2;
		if ( 13 !== count( $raw_row ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Die Spaltenzahl ist ungültig.' );
		}
		$row = array_map( 'flzest_csv_cell', $raw_row );
		if ( FLZEST_CSV_VERSION !== $row[0] ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Die Format-Version wird nicht unterstützt.' );
		}
		if ( 'appointment' !== $row[1] ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Der Datensatztyp ist ungültig.' );
		}
		$teacher_email = strtolower( sanitize_email( $row[2] ) );
		if ( '' === $teacher_email || ! is_email( $teacher_email ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Die Lehrkraft-E-Mail ist ungültig.' );
		}
		if ( ! ctype_digit( $row[3] ) || ! ctype_digit( $row[4] ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Beginn und Ende müssen Zeitstempel sein.' );
		}
		$start = (int) $row[3];
		$end = (int) $row[4];
		if ( $start <= 0 || $end <= $start ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Das Ende muss nach dem Beginn liegen.' );
		}
		$key = $teacher_email . '|' . $start;
		if ( isset( $keys[ $key ] ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Der Termin kommt doppelt vor.' );
		}
		$keys[ $key ] = true;

		$parent_values = array_slice( $row, 5, 7 );
		$booked = '' !== implode( '', $parent_values );
		$confirmed = $row[12];
		if ( ! in_array( $confirmed, array( '0', '1' ), true ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Bestätigt muss 0 oder 1 sein.' );
		}
		if ( ! $booked && '0' !== $confirmed ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Ein freier Termin kann nicht bestätigt sein.' );
		}

		$parent_gender = sanitize_text_field( $row[5] );
		$parent_name = sanitize_text_field( $row[6] );
		$parent_email = strtolower( sanitize_email( $row[8] ) );
		$student_name = sanitize_text_field( $row[9] );
		$student_class = sanitize_text_field( $row[10] );
		$gdpr_checked = sanitize_text_field( $row[11] );
		if ( $booked ) {
			if ( ! in_array( $parent_gender, array( '', 'm', 'f' ), true ) ) {
				throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Das Eltern-Geschlecht ist ungültig.' );
			}
			if ( '' === $parent_name ) {
				throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Der Eltern-Nachname fehlt.' );
			}
			if ( '' === $parent_email || ! is_email( $parent_email ) ) {
				throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Die Eltern-E-Mail ist ungültig.' );
			}
			if ( '' === $student_name || '' === $student_class ) {
				throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Schülername und Klasse sind erforderlich.' );
			}
			if ( 'yes' !== $gdpr_checked ) {
				throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Die Einwilligung muss yes sein.' );
			}
		}

		$result[] = array(
			'teacher_email' => $teacher_email,
			'start' => $start,
			'end' => $end,
			'booked' => $booked,
			'parent' => $booked ? array(
				'gender' => $parent_gender,
				'name' => $parent_name,
				'firstName' => sanitize_text_field( $row[7] ),
				'email' => $parent_email,
				'studentName' => $student_name,
				'studentClass' => $student_class,
				'gdprChecked' => $gdpr_checked,
			) : null,
			'confirmed' => '1' === $confirmed,
		);
	}

	return $result;
}

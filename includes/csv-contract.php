<?php

defined( 'ABSPATH' ) || defined( 'PHPUNIT_COMPOSER_INSTALL' ) || PHP_SAPI === 'cli' || exit;

// Validierungsfehler sind interne/fachliche Exception-Texte; Escaping erfolgt in der UI-Notice.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

const FLZEST_CSV_VERSION = '1';

/**
 * @return array<int,string>
 */
function flzest_teacher_csv_header(): array {
	return array( 'gender', 'last_name', 'first_name', 'email' );
}

/** @return array<int,string> */
function flzest_legacy_teacher_csv_header(): array {
	return array( 'format_version', 'record_type', ...flzest_teacher_csv_header() );
}

/**
 * @return array<int,string>
 */
function flzest_appointment_csv_header(): array {
	return array(
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

/** @return array<int,string> */
function flzest_legacy_appointment_csv_header(): array {
	return array( 'format_version', 'record_type', ...flzest_appointment_csv_header() );
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
 * Erkennt den aktuellen, Excel-freundlichen Vertrag oder den alten Vertrag mit Metadaten.
 *
 * @return int Anzahl der technischen Vorspalten in jeder Datenzeile.
 */
function flzest_csv_metadata_offset( array $actual, array $current_header, array $legacy_header ): int {
	try {
		flzest_assert_csv_header( $actual, $current_header );
		return 0;
	} catch ( UnexpectedValueException ) {
		flzest_assert_csv_header( $actual, $legacy_header );
		return 2;
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

	$metadata_offset = flzest_csv_metadata_offset( array_shift( $rows ), flzest_teacher_csv_header(), flzest_legacy_teacher_csv_header() );
	if ( empty( $rows ) ) {
		throw new UnexpectedValueException( 'Die Lehrkräfte-CSV enthält keine Datensätze.' );
	}

	$result = array();
	$emails = array();
	foreach ( $rows as $index => $raw_row ) {
		$line = $index + 2;
		if ( 4 + $metadata_offset !== count( $raw_row ) ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Die Spaltenzahl ist ungültig.' );
		}
		$row = array_map( 'flzest_csv_cell', $raw_row );
		if ( 2 === $metadata_offset ) {
			if ( FLZEST_CSV_VERSION !== $row[0] ) {
				throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Die Format-Version wird nicht unterstützt.' );
			}
			if ( 'teacher' !== $row[1] ) {
				throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Der Datensatztyp ist ungültig.' );
			}
		}
		$gender = sanitize_text_field( $row[ $metadata_offset ] );
		if ( ! in_array( $gender, array( '', 'm', 'f' ), true ) ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Das Geschlecht muss leer, m oder f sein.' );
		}
		$name = sanitize_text_field( $row[ $metadata_offset + 1 ] );
		if ( '' === $name ) {
			throw new UnexpectedValueException( 'Lehrkräfte-CSV Zeile ' . $line . ': Der Nachname fehlt.' );
		}
		$email = strtolower( sanitize_email( $row[ $metadata_offset + 3 ] ) );
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
			'firstName' => sanitize_text_field( $row[ $metadata_offset + 2 ] ),
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

	$metadata_offset = flzest_csv_metadata_offset( array_shift( $rows ), flzest_appointment_csv_header(), flzest_legacy_appointment_csv_header() );
	if ( empty( $rows ) ) {
		throw new UnexpectedValueException( 'Die Termin-CSV enthält keine Datensätze.' );
	}

	$result = array();
	$keys = array();
	foreach ( $rows as $index => $raw_row ) {
		$line = $index + 2;
		if ( 11 + $metadata_offset !== count( $raw_row ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Die Spaltenzahl ist ungültig.' );
		}
		$row = array_map( 'flzest_csv_cell', $raw_row );
		if ( 2 === $metadata_offset ) {
			if ( FLZEST_CSV_VERSION !== $row[0] ) {
				throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Die Format-Version wird nicht unterstützt.' );
			}
			if ( 'appointment' !== $row[1] ) {
				throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Der Datensatztyp ist ungültig.' );
			}
		}
		$teacher_email = strtolower( sanitize_email( $row[ $metadata_offset ] ) );
		if ( '' === $teacher_email || ! is_email( $teacher_email ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Die Lehrkraft-E-Mail ist ungültig.' );
		}
		if ( ! ctype_digit( $row[ $metadata_offset + 1 ] ) || ! ctype_digit( $row[ $metadata_offset + 2 ] ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Beginn und Ende müssen Zeitstempel sein.' );
		}
		$start = (int) $row[ $metadata_offset + 1 ];
		$end = (int) $row[ $metadata_offset + 2 ];
		if ( $start <= 0 || $end <= $start ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Das Ende muss nach dem Beginn liegen.' );
		}
		$key = $teacher_email . '|' . $start;
		if ( isset( $keys[ $key ] ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Der Termin kommt doppelt vor.' );
		}
		$keys[ $key ] = true;

		$parent_values = array_slice( $row, $metadata_offset + 3, 7 );
		$booked = '' !== implode( '', $parent_values );
		$confirmed = $row[ $metadata_offset + 10 ];
		if ( ! in_array( $confirmed, array( '0', '1' ), true ) ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Bestätigt muss 0 oder 1 sein.' );
		}
		if ( ! $booked && '0' !== $confirmed ) {
			throw new UnexpectedValueException( 'Termin-CSV Zeile ' . $line . ': Ein freier Termin kann nicht bestätigt sein.' );
		}

		$parent_gender = sanitize_text_field( $row[ $metadata_offset + 3 ] );
		$parent_name = sanitize_text_field( $row[ $metadata_offset + 4 ] );
		$parent_email = strtolower( sanitize_email( $row[ $metadata_offset + 6 ] ) );
		$student_name = sanitize_text_field( $row[ $metadata_offset + 7 ] );
		$student_class = sanitize_text_field( $row[ $metadata_offset + 8 ] );
		$gdpr_checked = sanitize_text_field( $row[ $metadata_offset + 9 ] );
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
				'firstName' => sanitize_text_field( $row[ $metadata_offset + 5 ] ),
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

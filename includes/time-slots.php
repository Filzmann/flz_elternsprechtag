<?php

/**
 * Converts a configured local appointment date and time into the persisted
 * Unix timestamp. The timestamp is always derived in the WordPress timezone,
 * rather than from the PHP process timezone.
 */
function flzest_local_appointment_timestamp(
	string $date_value,
	string $time_value,
	?DateTimeZone $timezone = null
): int {
	$timezone = $timezone ?? ( function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( date_default_timezone_get() ) );
	$date     = null;
	foreach ( array( 'd.m.y', 'd.m.Y' ) as $date_format ) {
		$candidate = DateTimeImmutable::createFromFormat( '!' . $date_format, $date_value, $timezone );
		if ( $candidate && $candidate->format( $date_format ) === $date_value ) {
			$date = $candidate;
			break;
		}
	}
	if ( ! $date instanceof DateTimeImmutable ) {
		throw new UnexpectedValueException( 'Der Elternsprechtag besitzt kein gueltiges Datum.' );
	}

	$appointment = DateTimeImmutable::createFromFormat(
		'!Y-m-d H:i',
		$date->format( 'Y-m-d' ) . ' ' . $time_value,
		$timezone
	);
	if ( ! $appointment || $appointment->format( 'H:i' ) !== $time_value ) {
		throw new UnexpectedValueException( 'Die Terminzeit ist in der WordPress-Zeitzone ungueltig.' );
	}

	$wall_time = DateTimeImmutable::createFromFormat(
		'!Y-m-d H:i',
		$date->format( 'Y-m-d' ) . ' ' . $time_value,
		new DateTimeZone( 'UTC' )
	);
	$transitions = $timezone->getTransitions( $wall_time->getTimestamp() - 2 * 86400, $wall_time->getTimestamp() + 2 * 86400 );
	for ( $index = 1, $count = count( $transitions ); $index < $count; $index++ ) {
		$previous_offset = (int) $transitions[ $index - 1 ]['offset'];
		$current_offset  = (int) $transitions[ $index ]['offset'];
		if ( $current_offset >= $previous_offset ) {
			continue;
		}

		$repeated_start = (int) $transitions[ $index ]['ts'] + $current_offset;
		$repeated_end   = (int) $transitions[ $index ]['ts'] + $previous_offset;
		if ( $wall_time->getTimestamp() >= $repeated_start && $wall_time->getTimestamp() < $repeated_end ) {
			throw new UnexpectedValueException( 'Die Terminzeit ist beim Ende der Sommerzeit mehrdeutig.' );
		}
	}

	return $appointment->getTimestamp();
}

/**
 * Builds bounded appointment slots from validated date and time settings.
 *
 * @return array<int,array{start:int,end:int}>
 */
function flzest_build_appointment_slots(
	string $date_value,
	string $start_value,
	string $end_value,
	int $slot_length,
	?DateTimeZone $timezone = null
): array {
	if ( $slot_length < 5 || $slot_length > 120 ) {
		throw new UnexpectedValueException( 'Die Terminlaenge muss zwischen 5 und 120 Minuten liegen.' );
	}

	$timezone = $timezone ?? new DateTimeZone( date_default_timezone_get() );
	$start    = flzest_local_appointment_timestamp( $date_value, $start_value, $timezone );
	$end      = flzest_local_appointment_timestamp( $date_value, $end_value, $timezone );
	if ( $start >= $end ) {
		throw new UnexpectedValueException( 'Beginn und Ende des Elternsprechtags sind ungueltig.' );
	}

	$slot_seconds = $slot_length * 60;
	$slots        = array();
	$cursor       = $start;
	$end_time     = $end;
	while ( $cursor + $slot_seconds <= $end_time ) {
		$slots[] = array(
			'start' => $cursor,
			'end'   => $cursor + $slot_seconds,
		);
		$cursor += $slot_seconds;
	}

	if ( empty( $slots ) ) {
		throw new UnexpectedValueException( 'Das Zeitfenster ist kuerzer als ein Termin.' );
	}

	return $slots;
}

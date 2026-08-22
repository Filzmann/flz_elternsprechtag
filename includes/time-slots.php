<?php

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

	$start = DateTimeImmutable::createFromFormat(
		'!Y-m-d H:i',
		$date->format( 'Y-m-d' ) . ' ' . $start_value,
		$timezone
	);
	$end   = DateTimeImmutable::createFromFormat(
		'!Y-m-d H:i',
		$date->format( 'Y-m-d' ) . ' ' . $end_value,
		$timezone
	);
	if (
		! $start
		|| ! $end
		|| $start->format( 'H:i' ) !== $start_value
		|| $end->format( 'H:i' ) !== $end_value
		|| $start >= $end
	) {
		throw new UnexpectedValueException( 'Beginn und Ende des Elternsprechtags sind ungueltig.' );
	}

	$slot_seconds = $slot_length * 60;
	$slots        = array();
	$cursor       = $start->getTimestamp();
	$end_time     = $end->getTimestamp();
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

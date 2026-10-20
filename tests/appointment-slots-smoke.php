<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

require_once dirname( __DIR__ ) . '/includes/time-slots.php';

$slots = flzest_build_appointment_slots( '22.08.2026', '16:00', '17:00', 20 );
if ( 3 !== count( $slots ) ) {
	throw new RuntimeException( 'Ein gueltiges Zeitfenster erzeugt nicht exakt drei Termine.' );
}
if ( 20 * 60 !== $slots[0]['end'] - $slots[0]['start'] ) {
	throw new RuntimeException( 'Die Terminlaenge wurde nicht in Sekunden abgebildet.' );
}

$berlin = new DateTimeZone( 'Europe/Berlin' );
foreach ( array( '29.03.2026', '25.10.2026' ) as $dst_date ) {
	$dst_slots = flzest_build_appointment_slots( $dst_date, '16:00', '17:00', 20, $berlin );
	if ( 3 !== count( $dst_slots ) ) {
		throw new RuntimeException( 'Ein Terminrahmen am Umstellungstag erzeugt nicht alle geplanten Slots.' );
	}
	foreach ( $dst_slots as $index => $slot ) {
		$expected_time = sprintf( '16:%02d', $index * 20 );
		$stored_time   = ( new DateTimeImmutable( '@' . $slot['start'] ) )->setTimezone( $berlin )->format( 'H:i' );
		if ( $expected_time !== $stored_time ) {
			throw new RuntimeException( 'Ein geplanter Slot verrutscht bei der Zeitumstellung.' );
		}
	}
}

foreach ( array(
	array( '29.03.2026', '16:00' ),
	array( '25.10.2026', '16:00' ),
) as $case ) {
	$timestamp = flzest_local_appointment_timestamp( $case[0], $case[1], $berlin );
	if ( ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $berlin )->format( 'd.m.Y H:i' ) !== $case[0] . ' ' . $case[1] ) {
		throw new RuntimeException( 'Die Slot-Suche bildet einen lokalen Termin am Umstellungstag nicht verlustfrei ab.' );
	}
}

try {
	flzest_local_appointment_timestamp( '25.10.2026', '02:30', $berlin );
	throw new RuntimeException( 'Eine mehrdeutige lokale Zeit beim Zurueckstellen wurde akzeptiert.' );
} catch ( UnexpectedValueException $error ) {
	// Die doppelte Stunde am Ende der Sommerzeit braucht eine explizite Aufloesung und wird daher abgewiesen.
}

$invalid_cases = array(
	array( '22.08.2026', '16:00', '17:00', 0 ),
	array( '22.08.2026', '17:00', '16:00', 20 ),
	array( 'kein-datum', '16:00', '17:00', 20 ),
	array( '22.08.2026', 'keine-zeit', '17:00', 20 ),
);

foreach ( $invalid_cases as $case ) {
	try {
		flzest_build_appointment_slots( $case[0], $case[1], $case[2], $case[3] );
	} catch ( UnexpectedValueException $error ) {
		continue;
	}

	throw new RuntimeException( 'Ein ungueltiger Terminrahmen wurde akzeptiert.' );
}

echo "OK: flz_elternsprechtag appointment slots smoke test\n";

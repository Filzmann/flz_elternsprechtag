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

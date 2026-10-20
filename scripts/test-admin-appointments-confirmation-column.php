<?php

declare( strict_types=1 );

$template = file_get_contents( dirname( __DIR__ ) . '/templates/appointments.php' );

if ( false === $template ) {
	fwrite( STDERR, "Could not read appointments template.\n" );
	exit( 1 );
}

$expectations = array(
	"booked appointments append a human-readable confirmation status to the parent summary" => "\$parent_summary .= \$appointment->isConfirmed ? ' – bestätigt' : ' – nicht bestätigt';",
);

foreach ( $expectations as $description => $expected ) {
	if ( false === strpos( $template, $expected ) ) {
		fwrite( STDERR, "Missing: {$description}.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $template, '<th>Bestätigt</th>' ) ) {
	fwrite( STDERR, "Confirmation status must not use its own table column.\n" );
	exit( 1 );
}

echo "OK: appointments confirmation column contract verified.\n";

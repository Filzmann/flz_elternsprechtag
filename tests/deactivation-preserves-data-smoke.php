<?php

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

$source = file_get_contents( dirname( __DIR__ ) . '/activate-deactivate.php' );
if ( false === $source ) {
	throw new RuntimeException( 'Der Aktivierungsvertrag konnte nicht gelesen werden.' );
}

$start = strpos( $source, 'function flz_est_elternsprechtag_deactivate' );
if ( false === $start ) {
	throw new RuntimeException( 'Der Deaktivierungs-Handler fehlt.' );
}

$deactivation = substr( $source, $start );
$forbidden     = array(
	'delete_table',
	'remove_role',
	'remove_cap',
);

foreach ( $forbidden as $call ) {
	if ( str_contains( $deactivation, $call ) ) {
		throw new RuntimeException( 'Die Deaktivierung enthält weiterhin einen destruktiven Aufruf: ' . $call );
	}
}

echo "OK: flz_elternsprechtag deactivation preservation smoke test\n";

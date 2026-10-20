<?php

declare(strict_types=1);

// Exception-Texte sind ausschließlich lokale CLI-Testdiagnostik.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['flzest_test_can_export'] = false;
$GLOBALS['flzest_test_nonce_valid'] = false;
$GLOBALS['flzest_test_nonce_checks'] = 0;

function current_user_can( string $capability ): bool {
	global $flzest_test_can_export;

	if ( 'flz_est' !== $capability ) {
		throw new RuntimeException( 'Unerwartete Capability: ' . $capability );
	}

	return $flzest_test_can_export;
}

function wp_die( string $message ): void {
	throw new RuntimeException( $message );
}

function esc_html__( string $message, string $text_domain ): string {
	return $message;
}

function check_admin_referer( string $action ): void {
	global $flzest_test_nonce_valid, $flzest_test_nonce_checks;

	++$flzest_test_nonce_checks;
	if ( 'flzest_export_teachers_csv' !== $action ) {
		throw new RuntimeException( 'Unerwartete Nonce-Action: ' . $action );
	}
	if ( ! $flzest_test_nonce_valid ) {
		throw new RuntimeException( 'Ungueltige Nonce.' );
	}
}

require dirname( __DIR__ ) . '/error-handling.php';

$assert_throws = static function ( callable $callback, string $message ): void {
	try {
		$callback();
	} catch ( RuntimeException $error ) {
		return;
	}

	throw new RuntimeException( $message );
};

$assert_throws(
	static fn() => flzest_assert_csv_export_request( 'flzest_export_teachers_csv' ),
	'CSV-Export ohne Berechtigung wurde nicht verweigert.'
);
if ( 0 !== $GLOBALS['flzest_test_nonce_checks'] ) {
	throw new RuntimeException( 'Die Nonce wurde vor der Capability geprüft.' );
}

$GLOBALS['flzest_test_can_export'] = true;
$assert_throws(
	static fn() => flzest_assert_csv_export_request( 'flzest_export_teachers_csv' ),
	'CSV-Export mit ungültiger Nonce wurde nicht verweigert.'
);

$GLOBALS['flzest_test_nonce_valid'] = true;
flzest_assert_csv_export_request( 'flzest_export_teachers_csv' );

$plugin_root = dirname( __DIR__ );
$sources = array(
	$plugin_root . '/backend/teachers.php' => 'flzest_export_teachers_csv',
	$plugin_root . '/backend/appointments.php' => 'flzest_export_appointments_csv',
);
$combined_source = '';
foreach ( $sources as $source_file => $nonce_action ) {
	$source = file_get_contents( $source_file );
	if ( false === $source ) {
		throw new RuntimeException( 'Quelldatei konnte nicht gelesen werden: ' . $source_file );
	}
	$assert_position = strpos( $source, "flzest_assert_csv_export_request( '" . $nonce_action . "' )" );
	$send_position = strpos( $source, 'flz_wpdb_objects_send_csv_download', $assert_position ?: 0 );
	if ( false === $assert_position || false === $send_position || $assert_position > $send_position ) {
		throw new RuntimeException( 'Ein CSV-Handler streamt ohne vorgelagerte Zugriffskontrolle: ' . $source_file );
	}
	$combined_source .= $source;
}

if ( str_contains( $combined_source, 'flz_wpdb_objects_create_csv_file' ) ) {
	throw new RuntimeException( 'Der Elternsprechtag erzeugt weiterhin öffentliche CSV-Dateien.' );
}
if ( substr_count( $combined_source, 'flz_wpdb_objects_send_csv_download' ) < 2 ) {
	throw new RuntimeException( 'Beide CSV-Exporte müssen direkte Downloads verwenden.' );
}

echo "CSV export security smoke passed.\n";

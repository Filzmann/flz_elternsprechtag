<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/admin-tables.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-Testdiagnostik.
		throw new RuntimeException( $message );
	}
};

$assert(
	flzest_admin_table_matches_search( '', array( 'Muster', 'Ada' ) ),
	'Ein leerer Tabellenfilter muss alle Zeilen erhalten.'
);
$assert(
	flzest_admin_table_matches_search( 'ada', array( 'Muster', 'Ada', 'ada@example.test' ) ),
	'Der gemeinsame Tabellenfilter muss Teiltreffer unabhängig von Groß-/Kleinschreibung finden.'
);
$assert(
	flzest_admin_table_matches_search( '7.1', array( 'Beispiel, Eva', 'Kind Beispiel – 7.1' ) ),
	'Der gemeinsame Tabellenfilter muss alle sichtbaren Fachwerte berücksichtigen.'
);
$assert(
	! flzest_admin_table_matches_search( 'nicht vorhanden', array( 'Muster', 'Ada' ) ),
	'Der gemeinsame Tabellenfilter darf keine nicht passenden Zeilen anzeigen.'
);
$assert(
	flzest_admin_table_confirmation_filter( 'confirmed' ) === 'confirmed'
		&& flzest_admin_table_confirmation_filter( 'unconfirmed' ) === 'unconfirmed',
	'Die erlaubten Bestätigungsfilter müssen erhalten bleiben.'
);
$assert(
	flzest_admin_table_confirmation_filter( 'manipuliert' ) === 'all',
	'Ungültige Bestätigungsfilter müssen sicher auf alle Termine zurückfallen.'
);
$assert(
	flzest_admin_table_matches_confirmation_filter( 'confirmed', true, true )
		&& ! flzest_admin_table_matches_confirmation_filter( 'confirmed', true, false ),
	'Der Bestätigt-Filter muss ausschließlich bestätigte Buchungen zeigen.'
);
$assert(
	flzest_admin_table_matches_confirmation_filter( 'unconfirmed', true, false )
		&& ! flzest_admin_table_matches_confirmation_filter( 'unconfirmed', true, true )
		&& ! flzest_admin_table_matches_confirmation_filter( 'unconfirmed', false, false ),
	'Der Nicht-bestätigt-Filter muss ausschließlich unbestätigte Buchungen zeigen.'
);

foreach (
	array(
		'teachers.php' => array( 'gender', 'name', 'firstName', 'email' ),
		'appointments.php' => array( 'start', 'end', 'teacher', 'parent' ),
	) as $template_name => $sort_columns
) {
	$template = file_get_contents( dirname( __DIR__ ) . '/templates/' . $template_name );
	$assert( false !== $template, 'Das Tabellentemplate konnte nicht gelesen werden: ' . $template_name );
	$assert( false !== strpos( $template, "'name' => 'table_search'" ), 'Der gemeinsame Suchparameter fehlt in ' . $template_name );
	$assert( false !== strpos( $template, "'Filter zurücksetzen'" ), 'Der Filter-Reset fehlt in ' . $template_name );
	foreach ( $sort_columns as $sort_column ) {
		$assert(
			false !== strpos( $template, "_sort_link( '" ) && false !== strpos( $template, "', '" . $sort_column . "' )" ),
			'Die Sortierfunktion für die Spalte ' . $sort_column . ' fehlt in ' . $template_name
		);
	}
}

$appointment_template = file_get_contents( dirname( __DIR__ ) . '/templates/appointments.php' );
$assert( false !== strpos( $appointment_template, "'name' => 'confirmation_filter'" ), 'Die Auswahl für den Bestätigungsstatus fehlt.' );
$assert( false !== strpos( $appointment_template, "'label'  => 'Buchung bestätigen'" ) && false !== strpos( $appointment_template, "'label'  => 'Termin leeren'" ), 'Die Textbeschriftungen für die Terminaktionen fehlen.' );
$assert( 2 === preg_match_all( "/'icon'\\s*=>\\s*'',\\s*'hide_label'\\s*=>\\s*false/", $appointment_template ), 'Terminaktionen müssen sichtbare Textbuttons ohne Icons sein.' );

echo "OK: flz_elternsprechtag admin tables contract smoke test\n";

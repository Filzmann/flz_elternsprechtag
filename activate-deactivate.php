<?php
// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
require_once( "classes/FlzEstEstParent.php" );
require_once( "classes/FlzEstTeacher.php" );
require_once( "classes/FlzEstAppointment.php" );
require_once( "classes/FlzEstSetting.php" );



// Funktion zur Erstellung der Tabellen beim Aktivieren des Plugins
function flz_est_elternsprechtag_activate(): void
{
	try {
		FlzEstSetting::create_table();
		FlzEstTeacher::create_table();
		FlzEstParent::create_table();
		FlzEstAppointment::create_table();

		add_role(
			'flz_est_editor',
			'Elternsprechtagsbeauftragte_r',
			array(
				'flz_est' => true,
			),
		);
		$role = get_role( 'administrator' );
		if ( ! $role instanceof \WP_Role ) {
			throw new \RuntimeException( 'Die Administratorrolle wurde nicht gefunden.' );
		}
		$role->add_cap( 'flz_est' );
	} catch ( \Throwable $error ) {
		throw \flzest_operation_error( $error, 'Aktivieren des Elternsprechtags-Plugins' );
	}
}

// Deaktivierung trennt nur die WordPress-Hooks des Plugins. Persistente Daten,
// Rollen und Capabilities bleiben für eine spätere Reaktivierung erhalten.
function flz_est_elternsprechtag_deactivate(): void
{
}

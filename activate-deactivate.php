<?php
require_once( "classes/FlzEstEstParent.php" );
require_once( "classes/FlzEstTeacher.php" );
require_once( "classes/FlzEstAppointment.php" );
require_once( "classes/FlzEstSetting.php" );



// Funktion zur Erstellung der Tabellen beim Aktivieren des Plugins
function flz_est_elternsprechtag_activate(): void
{
	FlzEstSetting::create_table();
	FlzEstTeacher::create_table();
	FlzEstParent::create_table();
	FlzEstAppointment::create_table();


	add_role(
		'flz_est_editor',
		'Elternsprechtagsbeauftragte_r',
		array(
			'flz_est'         => true,
		),
	);
	$role = get_role( 'administrator' );
	$role->add_cap( 'flz_est' );
}

// Funktion zum Löschen der Tabellen beim Deaktivieren des Plugins
function flz_est_elternsprechtag_deactivate(): void
{
	flzEstAppointment::delete_table();
	FlzEstTeacher::delete_table();
    FlzEstParent::delete_table();
	FlzEstSetting::delete_table();

	remove_role( 'flz_est_editor' );
	$role = get_role( 'administrator' );
	$role->remove_cap( 'flz_est' );
}
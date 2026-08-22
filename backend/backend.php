<?php


require_once(plugin_dir_path(__FILE__) .'../classes/FlzEstAppointment.php');
require_once( plugin_dir_path(__FILE__) .'../classes/FlzEstTeacher.php' );
require_once( plugin_dir_path( __FILE__ ) . '../classes/FlzEstEstParent.php' );

// Funktion zur Erstellung des Backend-Menüs
function flzest_elternsprechtag_menu(): void
{
    add_menu_page(
        'FLZ Elternsprechtag',
        'FLZ Elternsprechtag',
        'flz_est',
        'flzest_appointments',
        'flzest_appointments_page',
        'dashicons-calendar-alt',
        27
    );
    add_submenu_page(
        'flzest_appointments',
        'Lehrer',
        'Lehrer',
        'flz_est',
        'flzest_teachers',
        'flzest_teachers_page'
    );
    add_submenu_page(
        'flzest_appointments',
        'Einstellungen',
        'Einstellungen',
        'flz_est',
        'flzest_settings',
        'flzest_settings_page'
    );
}

function getAppointmentsSlots(): array {
	$nextParentsDay = FlzEstSetting::get_value_by_name( "NextParentsDay" );
	$slotLength=FlzEstSetting::get_value_by_name( "SlotLength" );
	$startTime      = strtotime( $nextParentsDay . " " . FlzEstSetting::get_value_by_name( "ParentsDayBegin" ) );
	$endTime        = strtotime( $nextParentsDay . " " . FlzEstSetting::get_value_by_name( "ParentsDayEnd" ) );

	$slots = [];
	while ( $startTime < $endTime ) {
		$slotEnd   = $startTime + $slotLength * SECONDS_IN_MINUTE;
		$slots[]   = [ "start" => $startTime, "end" => $slotEnd ];
		$startTime = $slotEnd;
	}

	return $slots;
}

require_once ("settings.php");
require_once ("teachers.php");
require_once ("appointments.php");

// Hinzufügen der Backend-Menüs
add_action( 'admin_menu', 'flzest_elternsprechtag_menu' );
add_action( 'admin_post_flzest_export_teachers_csv', 'flzest_export_teachers_csv' );
add_action( 'admin_post_flzest_export_appointments_csv', 'flzest_export_appointments_csv' );

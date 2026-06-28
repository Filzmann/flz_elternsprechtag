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

/**
 * Liest einen erlaubten Sortierschlüssel aus der Admin-URL.
 *
 * @param array<int,string> $allowed Erlaubte Spalten.
 */
function flzest_admin_orderby(array $allowed, string $default): string
{
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : $default;
	return in_array( $orderby, $allowed, true ) ? $orderby : $default;
}

/**
 * Liest die Sortierrichtung aus der Admin-URL.
 */
function flzest_admin_order(): string
{
	$order = isset( $_GET['order'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'asc';
	return 'desc' === $order ? 'desc' : 'asc';
}

/**
 * Liest einen kurzen Textfilter aus der Admin-URL.
 */
function flzest_admin_filter_text(string $key): string
{
	return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
}

/**
 * Sortiert zwei skalare Werte stabil für Admin-Tabellen.
 *
 * @param int|string $left  Erster Wert.
 * @param int|string $right Zweiter Wert.
 */
function flzest_admin_compare($left, $right, string $order): int
{
	if ( is_numeric( $left ) && is_numeric( $right ) ) {
		$result = (int) $left <=> (int) $right;
	} else {
		$result = strnatcasecmp( (string) $left, (string) $right );
	}

	return 'desc' === $order ? -$result : $result;
}

require_once ("settings.php");
require_once ("teachers.php");
require_once ("appointments.php");

// Hinzufügen der Backend-Menüs
add_action( 'admin_menu', 'flzest_elternsprechtag_menu' );

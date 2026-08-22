<?php


require_once(plugin_dir_path(__FILE__) .'../classes/FlzEstAppointment.php');
require_once( plugin_dir_path(__FILE__) .'../classes/FlzEstTeacher.php' );
require_once( plugin_dir_path( __FILE__ ) . '../classes/FlzEstEstParent.php' );
require_once( plugin_dir_path( __FILE__ ) . '../includes/time-slots.php' );

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
	return flzest_build_appointment_slots(
		(string) FlzEstSetting::get_value_by_name( 'NextParentsDay' ),
		(string) FlzEstSetting::get_value_by_name( 'ParentsDayBegin' ),
		(string) FlzEstSetting::get_value_by_name( 'ParentsDayEnd' ),
		(int) FlzEstSetting::get_value_by_name( 'SlotLength' ),
		function_exists( 'wp_timezone' ) ? wp_timezone() : null
	);
}

require_once ("settings.php");
require_once ("teachers.php");
require_once ("appointments.php");

// Hinzufügen der Backend-Menüs
add_action( 'admin_menu', 'flzest_elternsprechtag_menu' );
add_action('admin_enqueue_scripts', 'flzest_maybe_enqueue_admin_ui_assets');
add_action( 'admin_post_flzest_export_teachers_csv', 'flzest_export_teachers_csv' );
add_action( 'admin_post_flzest_export_appointments_csv', 'flzest_export_appointments_csv' );

function flzest_maybe_enqueue_admin_ui_assets(string $hook_suffix): void
{
	if (str_contains($hook_suffix, 'flzest_') && function_exists('flz_ui_components_enqueue_assets')) {
		flz_ui_components_enqueue_assets();
	}
}

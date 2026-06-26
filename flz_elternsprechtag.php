<?php
/*
Plugin Name: flz_elternsprechtag
Plugin URI: Deine Plugin-URI
Description: Elternsprechtag am Tagore-Gymnasium
Version: 1.0
Author: Filzmann
Author URI: Deine Autor-URI
License: GPLv2 or later
Requires Plugins: flz_wpdb_objects, flz_ui_components
*/
namespace flz_est;

// Aktivierungshook, um die Tabellen zu erstellen
register_activation_hook( __FILE__, 'flz_est_elternsprechtag_activate' );
// Deaktivierungshook, um die Daten zu löschen
register_deactivation_hook( __FILE__, 'flz_est_elternsprechtag_deactivate' );


require_once(WP_PLUGIN_DIR . '/flz_wpdb_objects/FlzWpdbObject.php');
require_once(WP_PLUGIN_DIR . '/flz_wpdb_objects/FlzPerson.php');

$flzest_ui_components_file = WP_PLUGIN_DIR . '/flz_ui_components/flz_ui_components.php';
if ( ! function_exists( 'flz_ui' ) && is_readable( $flzest_ui_components_file ) ) {
	require_once $flzest_ui_components_file;
}

if ( ! function_exists( 'flz_ui' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'flz_elternsprechtag benötigt das aktive Plugin flz_ui_components.', 'flz-elternsprechtag' ) . '</p></div>';
		}
	);
	return;
}

require_once plugin_dir_path(__FILE__) . 'error-handling.php';



// Aktivierung/Deaktivierung-Modul einbinden
require_once(plugin_dir_path(__FILE__) . 'activate-deactivate.php');
//
// Backend-Modul einbinden
require_once( plugin_dir_path( __FILE__ ) . 'backend/backend.php' );

// Frontend-Modul einbinden
require_once( plugin_dir_path( __FILE__ ) . 'frontend/frontend.php' );



?>

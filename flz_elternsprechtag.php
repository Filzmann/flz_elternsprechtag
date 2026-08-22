<?php
/*
Plugin Name: FLZ Elternsprechtag
Plugin URI: Deine Plugin-URI
Description: Elternsprechtag am Tagore-Gymnasium
Version: 1.0
Author: Filzmann
Author URI: Deine Autor-URI
License: GPLv2 or later
Requires Plugins: flz_wpdb_objects, flz_ui_components
*/
namespace flz_est;

defined( 'ABSPATH' ) || exit;

const FLZ_EST_MIN_WPDB_OBJECTS_VERSION = '1.4.0';
const FLZ_EST_MIN_UI_COMPONENTS_VERSION = '0.1.11';

function flz_est_dependencies_available(): bool {
	return defined( 'FLZ_WPDB_OBJECTS_VERSION' )
		&& version_compare( FLZ_WPDB_OBJECTS_VERSION, FLZ_EST_MIN_WPDB_OBJECTS_VERSION, '>=' )
		&& class_exists( 'flz_wpdb_objects\\FlzWpdbObject' )
		&& defined( 'FLZ_UI_COMPONENTS_VERSION' )
		&& version_compare( FLZ_UI_COMPONENTS_VERSION, FLZ_EST_MIN_UI_COMPONENTS_VERSION, '>=' )
		&& function_exists( 'flz_ui' );
}

function flz_est_dependency_notice(): void {
	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'flz_elternsprechtag benötigt aktuelle, aktive Versionen von flz_wpdb_objects und flz_ui_components.', 'flz-elternsprechtag' )
		. '</p></div>';
}

function flz_est_bootstrap(): bool {
	static $loaded = false;

	if ( $loaded ) {
		return true;
	}
	if ( ! flz_est_dependencies_available() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\flz_est_dependency_notice' );
		return false;
	}

	require_once plugin_dir_path( __FILE__ ) . 'error-handling.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/csv-contract.php';
	require_once plugin_dir_path( __FILE__ ) . 'activate-deactivate.php';
	require_once plugin_dir_path( __FILE__ ) . 'backend/backend.php';
	require_once plugin_dir_path( __FILE__ ) . 'frontend/frontend.php';
	$loaded = true;

	return true;
}

function flz_est_activate(): void {
	if ( ! flz_est_bootstrap() ) {
		wp_die( esc_html__( 'Aktivierung abgebrochen: Erforderliche FLZ-Plugins fehlen oder sind zu alt.', 'flz-elternsprechtag' ) );
	}
	\flz_est_elternsprechtag_activate();
}

function flz_est_deactivate(): void {
	if ( flz_est_bootstrap() ) {
		\flz_est_elternsprechtag_deactivate();
	}
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\flz_est_activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\flz_est_deactivate' );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\flz_est_bootstrap', 20 );

if ( did_action( 'plugins_loaded' ) ) {
	flz_est_bootstrap();
}

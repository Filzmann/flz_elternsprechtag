<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Test-Exceptions werden ausschließlich von der CLI ausgewertet.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname(__DIR__);
$privacy_file = $root . '/includes/privacy.php';
$bootstrap = file_get_contents($root . '/flz_elternsprechtag.php');
$activation = file_get_contents($root . '/activate-deactivate.php');
$settings = file_get_contents($root . '/templates/settings.php');

if (!is_file($privacy_file)) {
	throw new RuntimeException('Der Elternsprechtag-Privacy-Vertrag fehlt.');
}
$privacy = file_get_contents($privacy_file);

foreach (
	array(
		"get_option('flzest_retention_enabled', 0)" => $privacy,
		"get_option('flzest_retention_months', 24)" => $privacy,
		'wp_privacy_personal_data_exporters' => $privacy,
		'wp_privacy_personal_data_erasers' => $privacy,
		'FlzWpdbTransaction::run' => $privacy,
		'check_admin_referer' => $privacy,
		'current_user_can' => $privacy,
		"add_option('flzest_retention_enabled', 0)" => $activation,
		"add_option('flzest_retention_months', 24)" => $activation,
		"require_once plugin_dir_path( __FILE__ ) . 'includes/privacy.php'" => $bootstrap,
		'Automatische Löschung ist standardmäßig ausgeschaltet' => $settings,
	) as $needle => $source
) {
	if (!is_string($source) || !str_contains($source, $needle)) {
		throw new RuntimeException('Der Elternsprechtag-Privacy-Vertrag fehlt: ' . $needle);
	}
}

echo "OK: flz_elternsprechtag privacy retention smoke test\n";


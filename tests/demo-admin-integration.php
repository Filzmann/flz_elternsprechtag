<?php

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
if (!defined('WP_CLI') || !WP_CLI || !defined('FLZ_EST_DEMO_TEST') || !FLZ_EST_DEMO_TEST) {
	throw new RuntimeException('Requires an opted-in disposable WordPress site.');
}
function flzest_admin_test_state(): string {
	return serialize(array(FlzEstTeacher::get_all_by(), FlzEstParent::get_all_by(), FlzEstAppointment::get_all_by(), FlzEstSetting::get_all_by(), get_option('flzest_demo_manifest')));
}
add_filter('wp_die_handler', static fn() => static function () { throw new RuntimeException('Request denied'); }, PHP_INT_MAX);
function flzest_admin_test_request(int $user, array $post, bool $valid_nonce, bool $allowed): void {
	wp_set_current_user($user);
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = $post;
	$_REQUEST = array('_wpnonce' => $valid_nonce ? wp_create_nonce('flzest_admin_action') : 'invalid-demo-nonce');
	$before = flzest_admin_test_state();
	$denied = false;
	ob_start();
	try { flzest_settings_page_content(); } catch (Throwable $error) { $denied = true; }
	$html = ob_get_clean();
	if (!$denied && preg_match('/<input[^>]*name="flzest_confirm_remove_demo"[^>]*>/', $html, $checkbox) && preg_match('/\schecked(?:\s|=|>)/', $checkbox[0])) {
		throw new RuntimeException('Removal confirmation must initially be unchecked.');
	}
	if ($denied === $allowed || (!$allowed && $before !== flzest_admin_test_state())) {
		throw new RuntimeException('Admin allow/deny or side-effect contract failed.');
	}
}
$admin = get_user_by('login', 'demo-admin')->ID;
flzest_admin_test_request(0, array('flzest_install_demo' => '1'), true, false);
flzest_admin_test_request($admin, array('flzest_install_demo' => '1'), false, false);
flzest_admin_test_request($admin, array('flzest_install_demo' => '1'), true, true);
if (count(FlzEstTeacher::get_all_by()) !== 3 || count(FlzEstAppointment::get_all_by()) !== 18) { throw new RuntimeException('Admin install counts failed'); }
$remove = array('flzest_remove_demo' => '1', 'flzest_confirm_remove_demo' => '1');
flzest_admin_test_request(0, $remove, true, false);
flzest_admin_test_request($admin, $remove, false, false);
flzest_admin_test_request($admin, array('flzest_remove_demo' => '1'), true, false);
flzest_admin_test_request($admin, array('flzest_remove_demo' => '1', 'flzest_confirm_remove_demo' => '0'), true, false);
flzest_admin_test_request($admin, $remove + array('flzest_install_demo' => '1'), true, false);
flzest_admin_test_request($admin, $remove + array('settings' => array()), true, false);
flzest_admin_test_request($admin, $remove, true, true);
flzest_admin_test_request($admin, $remove, true, true);
if (count(FlzEstTeacher::get_all_by()) !== 0 || count(FlzEstParent::get_all_by()) !== 0 || count(FlzEstAppointment::get_all_by()) !== 0) { throw new RuntimeException('Admin removal counts failed'); }
echo "PASS: settings install/remove, capability deny, nonce deny, confirmation deny, no forbidden effects\n";

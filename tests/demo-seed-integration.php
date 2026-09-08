<?php

/** Run with wp eval-file only in an empty, disposable WordPress test site. */
if (!defined('WP_CLI') || !WP_CLI || !defined('FLZ_EST_DEMO_TEST') || !FLZ_EST_DEMO_TEST || get_option('blogname') !== 'Elternsprechtag Demo') {
	throw new RuntimeException('Requires the isolated Elternsprechtag Demo test site.');
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
function flzest_demo_check(bool $condition, string $message): void {
	if (!$condition) { throw new RuntimeException($message); }
}
update_option('timezone_string', 'Europe/Berlin');
$first = flzest_install_demo_content();
flzest_demo_check($first === array('teachers' => 3, 'appointments' => 2), 'Fresh seed counts differ.');
$teachers = FlzEstTeacher::get_all_by();
$parents = FlzEstParent::get_all_by();
$appointments = FlzEstAppointment::get_all_by();
flzest_demo_check(count($teachers) === 3 && count($parents) === 2 && count($appointments) === 18, 'Unexpected database counts.');
foreach (array_merge($teachers, $parents) as $person) {
	flzest_demo_check(str_ends_with($person->email, '@example.test'), 'Non-synthetic address.');
}
$slots = getAppointmentsSlots();
$expected = array_column($slots, 'start');
foreach ($appointments as $appointment) {
	flzest_demo_check(in_array($appointment->start, $expected, true), 'Slot timezone mismatch.');
}
flzest_demo_check(flzest_install_demo_content() === array('teachers' => 0, 'appointments' => 0), 'Repeated seed is not idempotent.');
flzest_demo_check(count(FlzEstAppointment::get_all_by()) === 18, 'Repeated seed duplicates slots.');
flzest_demo_check(FlzEstSetting::get_value_by_name('MailFrom') === 'demo.elternsprechtag@example.test', 'Sender is not synthetic.');
flzest_demo_check(FlzEstSetting::get_value_by_name('TestMode') === '1', 'Test mode not enabled.');
$selected = $appointments[0];
$step3 = preg_replace('/\s+/', ' ', wp_strip_all_tags(flzest_render_step3($selected)));
flzest_demo_check(str_contains($step3, 'um ' . wp_date('H:i', $selected->start)), 'Booking form must show the WordPress timezone.');
$form = preg_replace('/\s+/', ' ', wp_strip_all_tags(do_shortcode('[flzest]')));
flzest_demo_check(str_contains($form, 'findet am ' . FlzEstSetting::get_value_by_name('NextParentsDay') . ' statt'), 'Displayed date must equal the configured demo date.');
echo "PASS: fresh synthetic seeds, real database, Europe/Berlin, repeated seed\n";

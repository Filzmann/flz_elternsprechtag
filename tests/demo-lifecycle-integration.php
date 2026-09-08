<?php

// Destructive test: only an explicitly opted-in disposable WordPress database.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
if (!defined('WP_CLI') || !WP_CLI || !defined('FLZ_EST_DEMO_TEST') || !FLZ_EST_DEMO_TEST) {
	throw new RuntimeException('Requires FLZ_EST_DEMO_TEST in a disposable test site.');
}
function flzest_lifecycle_assert(bool $ok, string $message): void {
	if (!$ok) { throw new RuntimeException($message); }
	echo 'PASS: ' . esc_html($message) . "\n";
}
function flzest_lifecycle_state(): string {
	return serialize(array(FlzEstTeacher::get_all_by(), FlzEstParent::get_all_by(), FlzEstAppointment::get_all_by(), FlzEstSetting::get_all_by()));
}
flzest_lifecycle_assert(function_exists('flzest_remove_demo_content'), 'Demo removal API exists');
update_option('timezone_string', 'Europe/Berlin');
$other = new FlzEstTeacher(array('name' => 'Synthetic-Other', 'firstName' => 'Test', 'gender' => 'd', 'email' => 'other@example.test'));
$other->save();
$initial = flzest_lifecycle_state();
$initial_slots = count(FlzEstAppointment::get_all_by());
flzest_install_demo_content();
$seeded = flzest_lifecycle_state();
flzest_install_demo_content();
flzest_lifecycle_assert($seeded === flzest_lifecycle_state(), 'Repeated installation leaves all records and settings unchanged');
flzest_lifecycle_assert(count(FlzEstAppointment::get_all_by()) === $initial_slots + 18, 'Eighteen additional demo slots');
$demo_teacher = FlzEstTeacher::get_by_email('demo.elternsprechtag.anna@example.test');
$demo_teacher->name = 'Edited-Demo';
$demo_teacher->save();
$changed = flzest_lifecycle_state();
try { flzest_remove_demo_content(); throw new LogicException('Removal accepted edited seed'); }
catch (flz_wpdb_objects\FlzWpdbObjectsException $error) {}
flzest_lifecycle_assert($changed === flzest_lifecycle_state(), 'Edited seed blocks removal without side effects');
$demo_teacher->name = 'Demo-Beispiel'; $demo_teacher->save();
$foreign_slot = new FlzEstAppointment(array('teacher' => $demo_teacher, 'start' => time() + 60, 'end' => time() + 1260));
$foreign_slot->save();
$changed = flzest_lifecycle_state();
try { flzest_remove_demo_content(); throw new LogicException('Removal accepted foreign reference'); }
catch (flz_wpdb_objects\FlzWpdbObjectsException $error) {}
flzest_lifecycle_assert($changed === flzest_lifecycle_state(), 'Additional appointment blocks removal without side effects');
$foreign_slot->delete();
$before_failure = flzest_lifecycle_state();
$manifest_before_failure = get_option('flzest_demo_manifest');
$inject_failure = static function (string $query): string {
	return str_starts_with($query, 'DELETE FROM') && str_contains($query, 'flz_est_parents') ? 'INVALID DEMO TEST QUERY' : $query;
};
global $wpdb;
$previous_suppression = $wpdb->suppress_errors(true);
add_filter('query', $inject_failure);
try { flzest_remove_demo_content(); throw new LogicException('Injected database failure was ignored'); }
catch (flz_wpdb_objects\FlzWpdbObjectsException $error) {}
finally { remove_filter('query', $inject_failure); $wpdb->suppress_errors($previous_suppression); }
flzest_lifecycle_assert($before_failure === flzest_lifecycle_state() && $manifest_before_failure === get_option('flzest_demo_manifest'), 'Database failure rolls back deleted slots and retains ownership');
$removed = flzest_remove_demo_content();
flzest_lifecycle_assert($removed['teachers'] === 3 && $removed['parents'] === 2 && $removed['appointments'] === 18, 'Removal deletes exactly the created demo records');
flzest_lifecycle_assert($initial === flzest_lifecycle_state(), 'Removal restores settings and preserves unrelated records');
flzest_remove_demo_content();
flzest_lifecycle_assert($initial === flzest_lifecycle_state(), 'Repeated removal has no side effects');
flzest_install_demo_content();
flzest_save_setting_value('MailFrom', 'changed@example.test');
flzest_remove_demo_content();
flzest_lifecycle_assert(FlzEstSetting::get_value_by_name('MailFrom') === 'changed@example.test', 'Manual settings changes survive removal');
$collision = new FlzEstTeacher(array('name' => 'Synthetic-Collision', 'firstName' => 'Test', 'gender' => 'd', 'email' => 'demo.elternsprechtag.anna@example.test'));
$collision->save();
$before_collision = flzest_lifecycle_state();
try { flzest_install_demo_content(); throw new LogicException('Reserved email collision accepted'); }
catch (flz_wpdb_objects\FlzWpdbObjectsException $error) {}
flzest_lifecycle_assert($before_collision === flzest_lifecycle_state(), 'Unowned email collision rolls back installation completely');
$collision->delete();
$other->delete();
echo "PASS: real-database demo lifecycle completed\n";

<?php

defined('ABSPATH') || exit;

add_filter('wp_privacy_personal_data_exporters', 'flzest_register_privacy_exporter');
add_filter('wp_privacy_personal_data_erasers', 'flzest_register_privacy_eraser');
add_action('flzest_daily_privacy_cleanup', 'flzest_run_privacy_cleanup');
add_action('admin_post_flzest_delete_expired_bookings', 'flzest_handle_manual_privacy_cleanup');

function flzest_register_privacy_exporter(array $exporters): array
{
	$exporters['flz-elternsprechtag'] = array(
		'exporter_friendly_name' => __('FLZ Elternsprechtag', 'flz-elternsprechtag'),
		'callback' => 'flzest_privacy_exporter',
	);

	return $exporters;
}

function flzest_register_privacy_eraser(array $erasers): array
{
	$erasers['flz-elternsprechtag'] = array(
		'eraser_friendly_name' => __('FLZ Elternsprechtag', 'flz-elternsprechtag'),
		'callback' => 'flzest_privacy_eraser',
	);

	return $erasers;
}

function flzest_privacy_exporter(string $email_address, int $page = 1): array
{
	if ($page > 1 || !is_email($email_address)) {
		return array('data' => array(), 'done' => true);
	}

	$data = array();
	foreach (FlzEstParent::get_all_by(array('email' => sanitize_email($email_address))) as $parent) {
		if (!$parent instanceof FlzEstParent) {
			continue;
		}
		foreach (FlzEstAppointment::get_all_by(array('parent_id' => (int) $parent->id)) as $appointment) {
			if (!$appointment instanceof FlzEstAppointment) {
				continue;
			}
			$data[] = array(
				'group_id' => 'flz-elternsprechtag',
				'group_label' => __('Elternsprechtagsbuchungen', 'flz-elternsprechtag'),
				'item_id' => 'flzest-booking-' . (int) $appointment->id,
				'data' => array(
					array('name' => __('Name', 'flz-elternsprechtag'), 'value' => trim((string) $parent->firstName . ' ' . (string) $parent->name)),
					array('name' => __('E-Mail', 'flz-elternsprechtag'), 'value' => (string) $parent->email),
					array('name' => __('Kind', 'flz-elternsprechtag'), 'value' => (string) $parent->studentName),
					array('name' => __('Klasse', 'flz-elternsprechtag'), 'value' => (string) $parent->studentClass),
					array('name' => __('Termin', 'flz-elternsprechtag'), 'value' => wp_date('d.m.Y H:i', (int) $appointment->start)),
					array('name' => __('Bestätigt', 'flz-elternsprechtag'), 'value' => $appointment->isConfirmed ? __('Ja', 'flz-elternsprechtag') : __('Nein', 'flz-elternsprechtag')),
				),
			);
		}
	}

	foreach (FlzEstTeacher::get_all_by(array('email' => sanitize_email($email_address))) as $teacher) {
		if (!$teacher instanceof FlzEstTeacher) {
			continue;
		}
		$data[] = array(
			'group_id' => 'flz-elternsprechtag-teachers',
			'group_label' => __('Elternsprechtag-Lehrkräfte', 'flz-elternsprechtag'),
			'item_id' => 'flzest-teacher-' . (int) $teacher->id,
			'data' => array(
				array('name' => __('Name', 'flz-elternsprechtag'), 'value' => trim((string) $teacher->firstName . ' ' . (string) $teacher->name)),
				array('name' => __('E-Mail', 'flz-elternsprechtag'), 'value' => (string) $teacher->email),
			),
		);
	}

	return array('data' => $data, 'done' => true);
}

function flzest_privacy_eraser(string $email_address, int $page = 1): array
{
	$result = array(
		'items_removed' => false,
		'items_retained' => false,
		'messages' => array(),
		'done' => true,
	);
	if ($page > 1 || !is_email($email_address)) {
		return $result;
	}

	try {
		foreach (FlzEstParent::get_all_by(array('email' => sanitize_email($email_address))) as $parent) {
			if ($parent instanceof FlzEstParent) {
				flzest_erase_parent($parent);
				$result['items_removed'] = true;
			}
		}
		foreach (FlzEstTeacher::get_all_by(array('email' => sanitize_email($email_address))) as $teacher) {
			if ($teacher instanceof FlzEstTeacher) {
				flzest_anonymize_teacher($teacher);
				$result['items_removed'] = true;
			}
		}
	} catch (Throwable $error) {
		flzest_log_error($error, 'Löschen oder Anonymisieren eines Elternsprechtag-Datensatzes über den WordPress-Privacy-Eraser');
		$result['items_retained'] = true;
		$result['messages'][] = __('Ein Elternsprechtag-Datensatz konnte nicht sicher entfernt werden.', 'flz-elternsprechtag');
	}

	return $result;
}

function flzest_erase_parent(FlzEstParent $parent): void
{
	if (empty($parent->id)) {
		throw new UnexpectedValueException('Der zu löschende Elterndatensatz wurde nicht gefunden.');
	}

	flz_wpdb_objects\FlzWpdbTransaction::run(
		static function () use ($parent): void {
			foreach (FlzEstAppointment::get_all_by(array('parent_id' => (int) $parent->id)) as $appointment) {
				if (!$appointment instanceof FlzEstAppointment) {
					continue;
				}
				$locked = FlzEstAppointment::get_by_id_for_update((int) $appointment->id);
				if (!$locked instanceof FlzEstAppointment) {
					throw new UnexpectedValueException('Ein zu löschender Elternsprechtagstermin wurde nicht gefunden.');
				}
				$locked->parent = null;
				$locked->isConfirmed = false;
				$locked->confirmationExpiration = 0;
				$locked->confirmationToken = null;
				$locked->save();
			}
			$parent->delete();
		},
		'Löschen eines Elternsprechtag-Elterndatensatzes und Freigeben seiner Termine'
	);
}

function flzest_anonymize_teacher(FlzEstTeacher $teacher): void
{
	if (empty($teacher->id)) {
		throw new UnexpectedValueException('Die zu anonymisierende Lehrkraft wurde nicht gefunden.');
	}
	$teacher->name = 'Entfernt';
	$teacher->firstName = 'Lehrkraft ' . (int) $teacher->id;
	$teacher->gender = 'd';
	$teacher->email = 'removed-teacher-' . (int) $teacher->id . '@example.invalid';
	$teacher->save();
}

function flzest_retention_months(): int
{
	return max(1, min(120, absint(get_option('flzest_retention_months', 24))));
}

function flzest_retention_cutoff(): int
{
	return current_datetime()->modify('-' . flzest_retention_months() . ' months')->getTimestamp();
}

function flzest_schedule_privacy_cleanup(): void
{
	if (wp_next_scheduled('flzest_daily_privacy_cleanup')) {
		return;
	}
	$scheduled = wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'flzest_daily_privacy_cleanup', array(), true);
	if (is_wp_error($scheduled) || false === $scheduled) {
		throw new RuntimeException('Der tägliche Elternsprechtag-Privacy-Job konnte nicht eingerichtet werden.');
	}
}

function flzest_unschedule_privacy_cleanup(): void
{
	$cleared = wp_clear_scheduled_hook('flzest_daily_privacy_cleanup', array(), true);
	if (is_wp_error($cleared)) {
		throw new RuntimeException('Der Elternsprechtag-Privacy-Job konnte nicht entfernt werden.');
	}
}

function flzest_run_privacy_cleanup(): int
{
	if (!(bool) get_option('flzest_retention_enabled', 0)) {
		return 0;
	}

	return flzest_delete_expired_bookings();
}

function flzest_delete_expired_bookings(): int
{
	global $wpdb;

	$table = $wpdb->prefix . 'flz_est_appointments';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellenname ist ein fester Pluginvertrag mit WordPress-Präfix.
	$parent_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT parent_id FROM $table WHERE parent_id IS NOT NULL AND start < %d", flzest_retention_cutoff()));
	if (!is_array($parent_ids)) {
		throw new RuntimeException('Abgelaufene Elternsprechtagsbuchungen konnten nicht gelesen werden.');
	}

	$deleted = 0;
	foreach ($parent_ids as $parent_id) {
		$parent = FlzEstParent::get_by_id(absint($parent_id));
		if ($parent instanceof FlzEstParent) {
			flzest_erase_parent($parent);
			++$deleted;
		}
	}

	return $deleted;
}

function flzest_handle_manual_privacy_cleanup(): void
{
	if (!current_user_can('flz_est')) {
		wp_die(esc_html__('Keine Berechtigung.', 'flz-elternsprechtag'));
	}
	check_admin_referer('flzest_delete_expired_bookings');
	if (!isset($_POST['confirm_delete_expired_bookings'])) {
		wp_die(esc_html__('Die ausdrückliche Löschbestätigung fehlt.', 'flz-elternsprechtag'));
	}

	try {
		$deleted = flzest_delete_expired_bookings();
		$url = add_query_arg(array('page' => 'flzest_settings', 'privacy_deleted' => $deleted), admin_url('admin.php'));
		wp_safe_redirect($url);
		exit;
	} catch (Throwable $error) {
		flzest_log_error($error, 'Manuelles Löschen abgelaufener Elternsprechtagsbuchungen');
		wp_die(esc_html__('Die abgelaufenen Buchungen konnten nicht vollständig gelöscht werden.', 'flz-elternsprechtag'));
	}
}


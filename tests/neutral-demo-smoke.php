<?php

// In-memory contract test; no WordPress database or mail transport is used.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
namespace flz_wpdb_objects {
	class FlzWpdbTransaction {
		public static function run(callable $callback, string $context): void { $callback(); }
	}
}

namespace {
	if (PHP_SAPI !== 'cli') { exit; }
	const DAY_IN_SECONDS = 86400;
	function sanitize_text_field($value) { return $value; }
	function current_time($type) { return strtotime('2030-01-01 12:00:00 UTC'); }
	function wp_date($format, $timestamp) { return date($format, $timestamp); }
	class DemoRecord {
		public static array $rows = array();
		public array $data;
		public function __construct(array $data) { $this->data = $data; }
		public function __get($key) { return $this->data[$key] ?? null; }
		public function __set($key, $value): void { $this->data[$key] = $value; }
		public function save(): void { $this->data['id'] = spl_object_id($this); static::$rows[spl_object_id($this)] = $this; }
		public static function get_by_email($email) {
			foreach (static::$rows as $row) { if ($row->email === $email) { return $row; } }
			return null;
		}
	}
	class FlzEstSetting extends DemoRecord {
		public static array $rows = array();
		public static function get_by_fields($fields) {
			foreach (self::$rows as $row) { if ($row->name === $fields['name']) { return $row; } }
			return null;
		}
	}
	class FlzEstTeacher extends DemoRecord { public static array $rows = array(); }
	class FlzEstParent extends DemoRecord { public static array $rows = array(); }
	class flzEstAppointment extends DemoRecord {
		public static array $rows = array();
		public static function get_by_fields($fields) {
			foreach (self::$rows as $row) {
				if ($row->teacher->id === $fields['teacher_id'] && $row->start === $fields['start']) { return $row; }
			}
			return null;
		}
		public static function get_by_teacher_and_start($teacher, $start) {
			foreach (self::$rows as $row) {
				if ($row->teacher === $teacher && date('H:i', $row->start) === $start) { return $row; }
			}
			return null;
		}
	}
	require dirname(__DIR__) . '/includes/time-slots.php';
	function getAppointmentsSlots() {
		$value = static fn($name) => FlzEstSetting::get_by_fields(array('name' => $name))->value;
		return flzest_build_appointment_slots($value('NextParentsDay'), $value('ParentsDayBegin'), $value('ParentsDayEnd'), (int) $value('SlotLength'));
	}
	require dirname(__DIR__) . '/backend/settings.php';
	function demo_assert($condition, $message): void {
		if (!$condition) { throw new \RuntimeException($message); }
	}
	$created = flzest_create_demo_records();
	demo_assert($created === array('teachers' => 3, 'appointments' => 2), 'Expected three teachers and two bookings.');
	demo_assert(count(FlzEstParent::$rows) === 2 && count(flzEstAppointment::$rows) === 18, 'Expected two parents/children and eighteen slots.');
	foreach (array_merge(FlzEstTeacher::$rows, FlzEstParent::$rows) as $person) {
		demo_assert(str_ends_with($person->email, '@example.test') && str_starts_with($person->name, 'Demo-'), 'Person must be explicitly synthetic.');
	}
	foreach (FlzEstParent::$rows as $parent) {
		demo_assert(str_contains($parent->studentName, 'Demo'), 'Child must be explicitly synthetic.');
	}
	demo_assert(FlzEstSetting::get_by_fields(array('name' => 'MailFrom'))?->value === 'demo.elternsprechtag@example.test', 'Demo sender must be synthetic.');
	demo_assert(FlzEstSetting::get_by_fields(array('name' => 'TestMode'))->value === '1', 'Demo must use test recipients.');
	demo_assert(count(FlzEstSetting::$rows) === 6, 'All six settings must be seeded.');
	foreach (flzEstAppointment::$rows as $appointment) {
		if ($appointment->parent instanceof FlzEstParent) {
			demo_assert($appointment->isConfirmed === true && $appointment->confirmationToken === null && $appointment->confirmationExpiration === 0, 'Seed bookings must not contain live confirmation tokens.');
		}
	}
	$teacher = reset(FlzEstTeacher::$rows);
	$appointment = flzEstAppointment::get_by_teacher_and_start($teacher, '16:00');
	$before = $appointment->data;
	demo_assert(flzest_book_first_free_demo_slot($teacher, 1) === false, 'Occupied slot must be rejected.');
	demo_assert($appointment->data === $before, 'Rejected booking must preserve the occupied slot.');
	foreach (array('flz_elternsprechtag.php', 'frontend/frontend.php', 'templates/frontend-step3.php') as $file) {
		demo_assert(stripos(file_get_contents(dirname(__DIR__) . '/' . $file), 'tagore') === false, 'User-facing source contains school branding: ' . $file);
	}
	echo "OK: neutral branding and synthetic demo seed contract\n";
}

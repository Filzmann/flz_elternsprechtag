<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Diagnosedaten; Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Additiver, idempotenter Upgradepfad für das Elternsprechtagsschema.
 */
final class FlzEstSchemaMigrator
{
	public static function maybe_upgrade(): void
	{
		$current_version = (string) get_option('flzest_db_version', '0');
		if (version_compare($current_version, \flz_est\FLZ_EST_DB_VERSION, '>=')) {
			return;
		}

		FlzEstSetting::create_table();
		FlzEstTeacher::create_table();
		FlzEstParent::create_table();
		FlzEstAppointment::create_table();

		if (version_compare($current_version, '2.0.0', '<')) {
			self::migrate_legacy_tables();
		}
		update_option('flzest_db_version', \flz_est\FLZ_EST_DB_VERSION, false);
	}

	private static function migrate_legacy_tables(): void
	{
		global $wpdb;

		$legacy = array(
			'teachers' => self::identifier($wpdb->prefix . 'flzestteachers'),
			'parents' => self::identifier($wpdb->prefix . 'flzestparents'),
			'appointments' => self::identifier($wpdb->prefix . 'flzestappointments'),
			'settings' => self::identifier($wpdb->prefix . 'flzestsettings'),
		);
		if (!self::table_exists($legacy['appointments'])) {
			return;
		}
		foreach ($legacy as $table) {
			if (!self::table_exists($table)) {
				throw new RuntimeException('Das Legacy-Schema des Elternsprechtags ist unvollständig; die Migration wurde abgebrochen.');
			}
		}

		$current = array(
			'teachers' => self::identifier($wpdb->prefix . 'flz_est_teachers'),
			'parents' => self::identifier($wpdb->prefix . 'flz_est_parents'),
			'appointments' => self::identifier($wpdb->prefix . 'flz_est_appointments'),
			'settings' => self::identifier($wpdb->prefix . 'flz_est_settings'),
		);

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($legacy, $current): void {
				self::migrate_settings($legacy['settings'], $current['settings']);
				$teacher_map = self::migrate_people(
					$legacy['teachers'],
					$current['teachers'],
					array('name', 'firstName', 'gender', 'email')
				);
				$parent_map = self::migrate_people(
					$legacy['parents'],
					$current['parents'],
					array('name', 'firstName', 'gender', 'email', 'studentName', 'studentClass', 'gdprChecked')
				);
				self::migrate_appointments(
					$legacy['appointments'],
					$current['appointments'],
					$teacher_map,
					$parent_map
				);
			},
			'Migrieren des Elternsprechtag-Legacyschemas'
		);
	}

	private static function migrate_settings(string $legacy_table, string $current_table): void
	{
		global $wpdb;

		foreach (self::rows($legacy_table) as $row) {
			if (!isset($row->name, $row->value)) {
				throw new RuntimeException('Eine Legacy-Einstellung besitzt ein ungültiges Format.');
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interner Tabellenname wurde validiert.
			$id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $current_table WHERE name = %s", (string) $row->name));
			$result = $id
				? $wpdb->update($current_table, array('value' => (string) $row->value), array('id' => (int) $id))
				: $wpdb->insert($current_table, array('name' => (string) $row->name, 'value' => (string) $row->value));
			if (false === $result) {
				throw new RuntimeException('Eine Legacy-Einstellung konnte nicht vollständig übernommen werden.');
			}
		}
	}

	/**
	 * @param array<int,string> $fields
	 * @return array<int,int> Legacy-ID zu aktueller ID.
	 */
	private static function migrate_people(string $legacy_table, string $current_table, array $fields): array
	{
		global $wpdb;

		$available = array();
		foreach (self::rows($current_table) as $row) {
			$key = self::identity($row, $fields);
			$available[$key][] = (int) $row->id;
		}

		$map = array();
		foreach (self::rows($legacy_table) as $row) {
			if (!isset($row->id)) {
				throw new RuntimeException('Ein Legacy-Personendatensatz besitzt keine ID.');
			}
			$key = self::identity($row, $fields);
			if (!empty($available[$key])) {
				$map[(int) $row->id] = (int) array_shift($available[$key]);
				continue;
			}

			$data = array();
			foreach ($fields as $field) {
				if (!property_exists($row, $field)) {
					throw new RuntimeException('Ein Legacy-Personendatensatz besitzt ein ungültiges Format.');
				}
				$data[$field] = (string) $row->{$field};
			}
			if (false === $wpdb->insert($current_table, $data) || (int) $wpdb->insert_id <= 0) {
				throw new RuntimeException('Ein Legacy-Personendatensatz konnte nicht übernommen werden.');
			}
			$map[(int) $row->id] = (int) $wpdb->insert_id;
		}

		return $map;
	}

	/** @param array<int,int> $teacher_map @param array<int,int> $parent_map */
	private static function migrate_appointments(
		string $legacy_table,
		string $current_table,
		array $teacher_map,
		array $parent_map
	): void {
		global $wpdb;

		$available = array();
		foreach (self::rows($current_table) as $row) {
			$key = self::appointment_identity((int) $row->teacher_id, (int) $row->start, (int) $row->end);
			$available[$key][] = $row;
		}

		foreach (self::rows($legacy_table) as $row) {
			$legacy_teacher_id = isset($row->teacher_id) ? (int) $row->teacher_id : 0;
			if (!isset($teacher_map[$legacy_teacher_id], $row->start, $row->end)) {
				throw new RuntimeException('Ein Legacy-Termin verweist auf keine auflösbare Lehrkraft.');
			}
			$teacher_id = $teacher_map[$legacy_teacher_id];
			$parent_id = null;
			if (isset($row->parent_id) && null !== $row->parent_id) {
				$legacy_parent_id = (int) $row->parent_id;
				if (!isset($parent_map[$legacy_parent_id])) {
					throw new RuntimeException('Ein Legacy-Termin verweist auf keinen auflösbaren Elterndatensatz.');
				}
				$parent_id = $parent_map[$legacy_parent_id];
			}

			$data = array(
				'start' => (int) $row->start,
				'end' => (int) $row->end,
				'isConfirmed' => (int) ($row->isConfirmed ?? 0),
				'confirmationExpiration' => (int) ($row->confirmationExpiration ?? 0),
				'confirmationToken' => isset($row->confirmationToken) ? (string) $row->confirmationToken : null,
				'teacher_id' => $teacher_id,
				'parent_id' => $parent_id,
			);
			$key = self::appointment_identity($teacher_id, $data['start'], $data['end']);
			$current = !empty($available[$key]) ? array_shift($available[$key]) : null;
			if (is_object($current)) {
				$current_parent = isset($current->parent_id) && null !== $current->parent_id ? (int) $current->parent_id : null;
				if (null !== $current_parent && null !== $parent_id && $current_parent !== $parent_id) {
					throw new RuntimeException('Legacy- und aktueller Termin enthalten widersprüchliche Buchungen.');
				}
				if (null === $current_parent && null !== $parent_id && false === $wpdb->update($current_table, $data, array('id' => (int) $current->id))) {
					throw new RuntimeException('Eine Legacy-Buchung konnte nicht in den vorhandenen Termin übernommen werden.');
				}
				continue;
			}

			if (false === $wpdb->insert($current_table, $data)) {
				throw new RuntimeException('Ein Legacy-Termin konnte nicht übernommen werden.');
			}
		}
	}

	/** @return array<int,object> */
	private static function rows(string $table): array
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interner Tabellenname wurde validiert.
		$rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id ASC");
		if (!is_array($rows)) {
			throw new RuntimeException('Eine Tabelle konnte für die Legacy-Migration nicht gelesen werden.');
		}

		return $rows;
	}

	/** @param array<int,string> $fields */
	private static function identity(object $row, array $fields): string
	{
		$values = array();
		foreach ($fields as $field) {
			if (!property_exists($row, $field)) {
				throw new RuntimeException('Ein Legacy-Personendatensatz besitzt ein ungültiges Format.');
			}
			$value = (string) $row->{$field};
			$values[] = 'email' === $field ? strtolower(trim($value)) : $value;
		}

		return hash('sha256', serialize($values));
	}

	private static function appointment_identity(int $teacher_id, int $start, int $end): string
	{
		return $teacher_id . '|' . $start . '|' . $end;
	}

	private static function table_exists(string $table): bool
	{
		global $wpdb;

		$pattern = method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($table) : $table;
		return $table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $pattern));
	}

	private static function identifier(string $identifier): string
	{
		if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
			throw new InvalidArgumentException('Ein interner Elternsprechtag-Tabellenname ist ungültig.');
		}

		return $identifier;
	}
}

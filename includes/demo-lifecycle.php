<?php

// Exceptions are internal diagnostics, rendered by the existing admin boundary.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

class FlzEstDemoConflict extends RuntimeException {}

/** Fixed internal table map; never accept table names from requests or manifests. */
function flzest_demo_tables(): array {
	global $wpdb;
	return array(
		'settings' => $wpdb->prefix . 'flz_est_settings',
		'teachers' => $wpdb->prefix . 'flz_est_teachers',
		'parents' => $wpdb->prefix . 'flz_est_parents',
		'appointments' => $wpdb->prefix . 'flz_est_appointments',
	);
}

/** Read and lock the small demo scope, including references, within the transaction. */
function flzest_demo_snapshot(): array {
	global $wpdb;
	$snapshot = array();
	foreach (flzest_demo_tables() as $kind => $table) {
		$rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id FOR UPDATE', $table), ARRAY_A);
		if (!is_array($rows) || $wpdb->last_error !== '') {
			throw new RuntimeException('Der Demo-Datenbestand konnte nicht gesperrt werden.');
		}
		$snapshot[$kind] = array_column($rows, null, 'id');
	}
	return $snapshot;
}

function flzest_demo_fingerprint(array $row): string {
	return hash('sha256', serialize($row));
}

/** The option stores ownership hashes and setting backups, never guessed ownership. */
function flzest_demo_manifest(array $before, array $after): array {
	$manifest = array('version' => 1, 'records' => array(), 'settings' => array());
	foreach (array('teachers', 'parents', 'appointments') as $kind) {
		$manifest['records'][$kind] = array_map('flzest_demo_fingerprint', array_diff_key($after[$kind], $before[$kind]));
	}
	foreach ($after['settings'] as $id => $row) {
		if (($before['settings'][$id] ?? null) !== $row) {
			$manifest['settings'][$id] = array('before' => $before['settings'][$id] ?? null, 'after' => flzest_demo_fingerprint($row));
		}
	}
	return $manifest;
}

function flzest_install_demo_content(): array {
	return flzest_demo_lifecycle(false);
}

function flzest_remove_demo_content(): array {
	return flzest_demo_lifecycle(true);
}

/** Both paths share a single transaction and lock order; failures leave no partial seed. */
function flzest_demo_lifecycle(bool $remove): array {
	global $wpdb;
	try {
		return flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($remove, $wpdb): array {
				$raw = $wpdb->get_var($wpdb->prepare('SELECT option_value FROM %i WHERE option_name = %s FOR UPDATE', $wpdb->options, 'flzest_demo_manifest'));
				if ($wpdb->last_error !== '') { throw new RuntimeException('Der Demo-Herkunftsnachweis konnte nicht gelesen werden.'); }
				$manifest = $raw === null ? null : maybe_unserialize($raw);
				if ($manifest !== null && (!is_array($manifest) || ($manifest['version'] ?? null) !== 1 || !is_array($manifest['records'] ?? null) || !is_array($manifest['settings'] ?? null))) {
					throw new RuntimeException('Der Demo-Herkunftsnachweis ist ungültig.');
				}
				if ($manifest !== null) {
					foreach (array('teachers', 'parents', 'appointments') as $kind) {
						if (!is_array($manifest['records'][$kind] ?? null)) { throw new RuntimeException('Der Demo-Herkunftsnachweis ist unvollständig.'); }
					}
				}
				if (!$remove && $manifest !== null) { return array('teachers' => 0, 'appointments' => 0); }
				if ($remove && $manifest === null) { return array('teachers' => 0, 'parents' => 0, 'appointments' => 0); }
				$before = flzest_demo_snapshot();
				if ($remove) {
					$result = flzest_demo_remove_owned($manifest, $before);
					if (!delete_option('flzest_demo_manifest')) { throw new RuntimeException('Der Demo-Herkunftsnachweis konnte nicht entfernt werden.'); }
					return $result;
				}
				$result = flzest_create_demo_records();
				if (!add_option('flzest_demo_manifest', flzest_demo_manifest($before, flzest_demo_snapshot()), '', false)) {
					throw new RuntimeException('Der Demo-Herkunftsnachweis konnte nicht gespeichert werden.');
				}
				return $result;
			},
			$remove ? 'Entfernen eigener Demo-Daten' : 'Anlegen eigener Demo-Daten'
		);
	} finally {
		// WordPress may have cached an option before a failed database COMMIT.
		wp_cache_delete('flzest_demo_manifest', 'options');
		wp_cache_delete('notoptions', 'options');
	}
}

/** Validate every owned row and every reference before deleting anything. */
function flzest_demo_remove_owned(array $manifest, array $current): array {
	global $wpdb;
	$tables = flzest_demo_tables();
	foreach (array('teachers', 'parents', 'appointments') as $kind) {
		foreach ($manifest['records'][$kind] as $id => $hash) {
			if (isset($current[$kind][$id]) && !hash_equals($hash, flzest_demo_fingerprint($current[$kind][$id]))) {
				throw new FlzEstDemoConflict(__('Demo-Daten wurden nachträglich verändert oder gebucht. Es wurde nichts entfernt. Bitte prüfen Sie die betroffenen Daten vor einem erneuten Versuch.', 'flz-elternsprechtag'));
			}
		}
	}
	foreach ($current['appointments'] as $id => $row) {
		if (!isset($manifest['records']['appointments'][$id]) &&
			(isset($manifest['records']['teachers'][$row['teacher_id']]) || isset($manifest['records']['parents'][$row['parent_id']]))) {
			throw new FlzEstDemoConflict(__('Zusätzliche Termine verwenden Demo-Personen. Es wurde nichts entfernt. Bitte prüfen Sie diese Termine vor einem erneuten Versuch.', 'flz-elternsprechtag'));
		}
	}
	$result = array('teachers' => 0, 'parents' => 0, 'appointments' => 0);
	foreach (array('appointments', 'parents', 'teachers') as $kind) {
		foreach ($manifest['records'][$kind] as $id => $hash) {
			$deleted = $wpdb->delete($tables[$kind], array('id' => (int) $id), array('%d'));
			if ($deleted === false) { throw new RuntimeException('Ein Demo-Datensatz konnte nicht entfernt werden.'); }
			$result[$kind] += $deleted;
		}
	}
	foreach ($manifest['settings'] as $id => $setting) {
		if (!isset($current['settings'][$id]) || !hash_equals($setting['after'], flzest_demo_fingerprint($current['settings'][$id]))) {
			continue; // A manual setting change always wins over the backup.
		}
		$restored = $setting['before'] === null
			? $wpdb->delete($tables['settings'], array('id' => (int) $id), array('%d'))
			: $wpdb->update($tables['settings'], array('value' => $setting['before']['value']), array('id' => (int) $id), array('%s'), array('%d'));
		if ($restored === false) { throw new RuntimeException('Eine Demo-Einstellung konnte nicht zurückgesetzt werden.'); }
	}
	return $result;
}

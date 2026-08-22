<?php

// Alle POST-Pfade laufen durch flzest_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing

function flzest_settings_page(): void
{
	try {
		flzest_settings_page_content();
	} catch ( Throwable $error ) {
		flzest_render_admin_error( $error, 'Anzeigen der Elternsprechtags-Einstellungen' );
	}
}

function flzest_settings_page_content(): void
{
	flzest_assert_admin_request();
	$settings_notice = '';
	if ( isset( $_POST['settings'] ) )
	{
			$posted_settings = map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' );
			flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $posted_settings ): void {
					foreach ( (array) $posted_settings as $id => $value ) {
						$setting = FlzEstSetting::get_by_id( absint( $id ) );
						if ( ! $setting instanceof FlzEstSetting ) {
							throw new UnexpectedValueException( 'Eine zu speichernde Einstellung wurde nicht gefunden.' );
						}
						$setting->value = flzest_validate_setting_value($setting->name, (string) $value);
						$setting->save();
					}
				},
				'Speichern der Elternsprechtags-Einstellungen'
			);
			$settings_notice = 'Einstellungen gespeichert.';
	}
	if (isset($_POST['flzest_privacy_settings'])) {
		$retention_enabled = isset($_POST['flzest_retention_enabled']) ? 1 : 0;
		$retention_months = isset($_POST['flzest_retention_months'])
			? max(1, min(120, absint(wp_unslash($_POST['flzest_retention_months']))))
			: 24;
		$retention_enabled ? flzest_schedule_privacy_cleanup() : flzest_unschedule_privacy_cleanup();
		update_option('flzest_retention_enabled', $retention_enabled, false);
		update_option('flzest_retention_months', $retention_months, false);
		$settings_notice = 'Datenschutz- und Aufbewahrungseinstellungen gespeichert.';
	}
	if ( isset( $_POST['flzest_install_demo'] ) ) {
		$created = flzest_install_demo_content();
		$settings_notice = sprintf(
			'Demo-Daten angelegt/aktualisiert: %d Lehrkräfte, %d Beispielbuchungen.',
			$created['teachers'],
			$created['appointments']
		);
	}
	$settings = FlzEstSetting::get_all_by();
	$retention_enabled = (bool) get_option('flzest_retention_enabled', 0);
	$retention_months = flzest_retention_months();
	$privacy_deleted = isset($_GET['privacy_deleted']) ? absint(wp_unslash($_GET['privacy_deleted'])) : null;
	include( plugin_dir_path( __FILE__ ) . '../templates/settings.php' );
}

function flzest_validate_setting_value(string $name, string $value): string
{
	$value = trim($value);
	if ('SlotLength' === $name) {
		if (!ctype_digit($value) || (int) $value < 5 || (int) $value > 120) {
			throw new UnexpectedValueException('Die Terminlänge muss zwischen 5 und 120 Minuten liegen.');
		}
		return (string) (int) $value;
	}
	if (in_array($name, array('ParentsDayBegin', 'ParentsDayEnd'), true)) {
		$date = DateTimeImmutable::createFromFormat('!H:i', $value);
		if (!$date || $date->format('H:i') !== $value) {
			throw new UnexpectedValueException('Beginn und Ende müssen im Format HH:MM angegeben werden.');
		}
		return $value;
	}
	if ('NextParentsDay' === $name) {
		foreach (array('!d.m.y', '!d.m.Y') as $format) {
			$date = DateTimeImmutable::createFromFormat($format, $value);
			if ($date && $date->format(substr($format, 1)) === $value) {
				return $date->format('d.m.y');
			}
		}
		throw new UnexpectedValueException('Der Elternsprechtag muss als TT.MM.JJ oder TT.MM.JJJJ angegeben werden.');
	}
	if ('TestMode' === $name) {
		if (!in_array($value, array('0', '1'), true)) {
			throw new UnexpectedValueException('Der Testmodus muss 0 oder 1 sein.');
		}
		return $value;
	}
	if ('MailFrom' === $name) {
		$email = sanitize_email($value);
		if (!is_email($email)) {
			throw new UnexpectedValueException('Die Absenderadresse ist ungültig.');
		}
		return $email;
	}

	return sanitize_text_field($value);
}

/**
 * Legt kleine, klar erkennbare Demo-Daten für lokale Tests an.
 *
 * Die Aktion ist idempotent: vorhandene Demo-Lehrkräfte und Demo-Eltern werden
 * anhand ihrer example.test-Adressen wiederverwendet statt dupliziert.
 *
 * @return array{teachers:int,appointments:int}
 */
function flzest_install_demo_content(): array
{
	$result = array(
		'teachers'     => 0,
		'appointments' => 0,
	);

	flz_wpdb_objects\FlzWpdbTransaction::run(
		static function () use ( &$result ): void {
			flzest_save_setting_value( 'NextParentsDay', wp_date( 'd.m.y', current_time( 'timestamp' ) + 30 * DAY_IN_SECONDS ) );
			flzest_save_setting_value( 'SlotLength', '20' );
			flzest_save_setting_value( 'ParentsDayBegin', '16:00' );
			flzest_save_setting_value( 'ParentsDayEnd', '18:00' );
			flzest_save_setting_value( 'TestMode', '1' );

			$demo_teachers = array(
				array( 'gender' => 'f', 'name' => 'Demo-Beispiel', 'firstName' => 'Anna', 'email' => 'demo.elternsprechtag.anna@example.test' ),
				array( 'gender' => 'm', 'name' => 'Demo-Muster', 'firstName' => 'Bernd', 'email' => 'demo.elternsprechtag.bernd@example.test' ),
				array( 'gender' => 'f', 'name' => 'Demo-Test', 'firstName' => 'Clara', 'email' => 'demo.elternsprechtag.clara@example.test' ),
			);

			foreach ( $demo_teachers as $index => $teacher_data ) {
				$teacher = flzest_ensure_demo_teacher( $teacher_data );
				if ( $teacher_data['created'] ?? false ) {
					$result['teachers']++;
				}
				flzest_ensure_teacher_slots( $teacher );
				if ( $index < 2 && flzest_book_first_free_demo_slot( $teacher, $index ) ) {
					$result['appointments']++;
				}
			}
		},
		'Anlegen von Elternsprechtag-Demo-Daten'
	);

	return $result;
}

function flzest_save_setting_value( string $name, string $value ): void
{
	$setting = FlzEstSetting::get_by_fields( array( 'name' => sanitize_text_field( $name ) ) );
	if ( ! $setting instanceof FlzEstSetting ) {
		$setting = new FlzEstSetting( array( 'name' => $name, 'value' => $value ) );
	} else {
		$setting->value = $value;
	}
	$setting->save();
}

/**
 * @param array{gender:string,name:string,firstName:string,email:string} $teacher_data
 */
function flzest_ensure_demo_teacher( array &$teacher_data ): FlzEstTeacher
{
	$teacher = FlzEstTeacher::get_by_email( $teacher_data['email'] );
	if ( $teacher instanceof FlzEstTeacher ) {
		$teacher_data['created'] = false;
		return $teacher;
	}

	$teacher_data['created'] = true;
	$teacher = new FlzEstTeacher( $teacher_data );
	$teacher->save();

	return $teacher;
}

function flzest_ensure_teacher_slots( FlzEstTeacher $teacher ): void
{
	foreach ( getAppointmentsSlots() as $slot ) {
		$start_label = date( 'H:i', (int) $slot['start'] );
		if ( flzEstAppointment::get_by_teacher_and_start( $teacher, $start_label ) instanceof flzEstAppointment ) {
			continue;
		}

		( new flzEstAppointment(
			array(
				'start'   => (int) $slot['start'],
				'end'     => (int) $slot['end'],
				'teacher' => $teacher,
				'parent'  => null,
			)
		) )->save();
	}
}

function flzest_book_first_free_demo_slot( FlzEstTeacher $teacher, int $index ): bool
{
	$slots = getAppointmentsSlots();
	if ( empty( $slots ) ) {
		return false;
	}

	$appointment = flzEstAppointment::get_by_teacher_and_start( $teacher, date( 'H:i', (int) $slots[0]['start'] ) );
	if ( ! $appointment instanceof flzEstAppointment || $appointment->parent instanceof FlzEstParent ) {
		return false;
	}

	$parent = flzest_ensure_demo_parent( $index );
	$appointment->parent = $parent;
	$appointment->isConfirmed = true;
	$appointment->confirmationExpiration = 0;
	$appointment->confirmationToken = null;
	$appointment->save();

	return true;
}

function flzest_ensure_demo_parent( int $index ): FlzEstParent
{
	$parents = array(
		array( 'gender' => 'f', 'name' => 'Demo-Elternteil', 'firstName' => 'Eva', 'email' => 'demo.elternsprechtag.eltern1@example.test', 'studentName' => 'Mia Demo', 'studentClass' => '7.1', 'gdprChecked' => 'yes' ),
		array( 'gender' => 'm', 'name' => 'Demo-Sorgeberechtigt', 'firstName' => 'Max', 'email' => 'demo.elternsprechtag.eltern2@example.test', 'studentName' => 'Noah Demo', 'studentClass' => '8.2', 'gdprChecked' => 'yes' ),
	);
	$data = $parents[ $index % count( $parents ) ];
	$parent = FlzEstParent::get_by_email( $data['email'] );
	if ( $parent instanceof FlzEstParent ) {
		return $parent;
	}

	$parent = new FlzEstParent( $data );
	$parent->save();

	return $parent;
}

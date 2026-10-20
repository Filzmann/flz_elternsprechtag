<?php

require_once __DIR__ . '/../includes/demo-lifecycle.php';
require_once __DIR__ . '/../includes/mail-templates.php';

// Alle POST-Pfade laufen durch flzest_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing
// Domain exception messages are escaped in flzest_settings_page().
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

function flzest_settings_page(): void
{
	try {
		flzest_settings_page_content();
	} catch ( Throwable $error ) {
		$cause = $error;
		do {
			if ($cause instanceof FlzEstDemoConflict) {
				echo '<div class="notice notice-error"><p>' . esc_html($cause->getMessage()) . '</p></div>';
				return;
			}
			$cause = $cause->getPrevious();
		} while ($cause instanceof Throwable);
		flzest_render_admin_error( $error, 'Anzeigen der Elternsprechtags-Einstellungen' );
	}
}

function flzest_settings_page_content(): void
{
	flzest_assert_admin_request();
	$settings_notice = '';
	if ((isset($_POST['flzest_install_demo']) || isset($_POST['flzest_remove_demo'])) &&
		(isset($_POST['settings']) || isset($_POST['flzest_privacy_settings']) || (isset($_POST['flzest_install_demo']) && isset($_POST['flzest_remove_demo'])))) {
		throw new FlzEstDemoConflict(__('Bitte führen Sie jeweils nur eine Demo-Aktion aus.', 'flz-elternsprechtag'));
	}
	if (isset($_POST['flzest_remove_demo'])) {
		if (!isset($_POST['flzest_confirm_remove_demo']) || sanitize_text_field(wp_unslash($_POST['flzest_confirm_remove_demo'])) !== '1') {
			throw new FlzEstDemoConflict(__('Bitte bestätigen Sie die Entfernung der Demo-Daten.', 'flz-elternsprechtag'));
		}
		$removed = flzest_remove_demo_content();
		$settings_notice = sprintf(
			__('Demo-Daten entfernt: %1$d Lehrkräfte, %2$d Elternteile, %3$d Termine.', 'flz-elternsprechtag'),
			$removed['teachers'], $removed['parents'], $removed['appointments']
		);
	}
	if ( isset( $_POST['settings'] ) )
	{
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Jeder Wert wird nach Zuordnung zur persistierten Einstellung einzeln validiert.
			$posted_settings = wp_unslash( $_POST['settings'] );
			if ( ! is_array( $posted_settings ) ) {
				throw new UnexpectedValueException( 'Die Einstellungen besitzen kein gültiges Array-Format.' );
			}
			flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $posted_settings ): void {
					foreach ( $posted_settings as $id => $value ) {
						$setting = FlzEstSetting::get_by_id( absint( $id ) );
						if ( ! $setting instanceof FlzEstSetting ) {
							throw new UnexpectedValueException( 'Eine zu speichernde Einstellung wurde nicht gefunden.' );
						}
						$setting->value = flzest_validate_setting_value($setting->name, is_scalar( $value ) ? (string) $value : '');
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
		foreach (array('!Y-m-d', '!d.m.y', '!d.m.Y') as $format) {
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
	if ( 'BookingConfirmationNotice' === $name ) {
		$value = wp_kses_post( $value );
		if ( '' === $value ) {
			throw new UnexpectedValueException( 'Der Hinweis zur Bestätigungsmail darf nicht leer sein.' );
		}

		return $value;
	}
	if ( flzest_is_mail_template_setting( $name ) ) {
		if ( str_ends_with( $name, 'Subject' ) ) {
			$value = sanitize_text_field( $value );
		} else {
			$value = wp_kses_post( $value );
		}
		if ( '' === $value ) {
			throw new UnexpectedValueException( 'Mailbetreff und Mailtext dürfen nicht leer sein.' );
		}
		if ( flzest_mail_template_has_unknown_placeholder( $value ) ) {
			throw new UnexpectedValueException( 'Die Mailvorlage enthält einen unbekannten Platzhalter.' );
		}

		return $value;
	}

	return sanitize_text_field($value);
}

/**
 * Converts the established stored date format into the ISO value required by
 * an HTML date input. Invalid legacy values are left empty so they cannot be
 * submitted as a different date without an explicit correction.
 */
function flzest_date_input_value(string $value): string
{
	foreach (array('!d.m.y', '!d.m.Y') as $format) {
		$date = DateTimeImmutable::createFromFormat($format, $value);
		if ($date && $date->format(substr($format, 1)) === $value) {
			return $date->format('Y-m-d');
		}
	}

	return '';
}

/**
 * Returns the UI Components field contract for a persisted plugin setting.
 *
 * @return array<string,mixed>
 */
function flzest_setting_input_args(string $name, string $value): array
{
	switch ($name) {
		case 'NextParentsDay':
			return array(
				'type'        => 'date',
				'value'       => flzest_date_input_value($value),
				'description' => 'Datum auswählen.',
			);
		case 'SlotLength':
			return array(
				'type'  => 'number',
				'value' => $value,
				'min'   => 5,
				'max'   => 120,
			);
		case 'ParentsDayBegin':
		case 'ParentsDayEnd':
			return array(
				'type'  => 'time',
				'value' => $value,
			);
		case 'TestMode':
			return array(
				'type'           => 'checkbox',
				'checkbox_value' => '1',
				'checked'        => '1' === $value,
			);
		case 'MailFrom':
			return array(
				'type'         => 'email',
				'value'        => $value,
				'autocomplete' => 'email',
			);
		case 'MailTeacherSubject':
		case 'MailParentSubject':
			return array(
				'type'  => 'text',
				'value' => $value,
			);
		case 'MailTeacherBody':
		case 'MailParentBody':
			return array(
				'type'        => 'textarea',
				'value'       => $value,
				'rows'        => 10,
				'input_class' => 'large-text code',
			);
		case 'BookingConfirmationNotice':
			return array(
				'type'        => 'textarea',
				'value'       => $value,
				'rows'        => 4,
				'input_class' => 'large-text',
			);
		default:
			return array(
				'type'  => 'text',
				'value' => $value,
			);
	}
}

function flzest_setting_label( string $name ): string
{
	$labels = array(
		'MailTeacherSubject' => 'Betreff: Mail an Lehrkraft',
		'MailTeacherBody'    => 'Text: Mail an Lehrkraft',
		'MailParentSubject'  => 'Betreff: Mail an Elternteil',
		'MailParentBody'     => 'Text: Mail an Elternteil',
		'BookingConfirmationNotice' => 'Hinweis zur Bestätigungsmail auf der Buchungsseite',
	);

	return $labels[ $name ] ?? $name;
}

/**
 * Legt kleine, klar erkennbare Demo-Daten für lokale Tests an.
 *
 * Interner Erzeuger; Transaktion, Wiederholbarkeit und Herkunftsnachweis
 * liegen ausschließlich in flzest_demo_lifecycle().
 *
 * @return array{teachers:int,appointments:int}
 */
function flzest_create_demo_records(): array
{
	$result = array(
		'teachers'     => 0,
		'appointments' => 0,
	);

	// Called only inside flzest_demo_lifecycle() and its transaction.
	flzest_save_setting_value( 'NextParentsDay', wp_date( 'd.m.y', time() + 30 * DAY_IN_SECONDS ) );
	flzest_save_setting_value( 'SlotLength', '20' );
	flzest_save_setting_value( 'ParentsDayBegin', '16:00' );
	flzest_save_setting_value( 'ParentsDayEnd', '18:00' );
	flzest_save_setting_value( 'TestMode', '1' );
	flzest_save_setting_value( 'MailFrom', 'demo.elternsprechtag@example.test' );

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
		throw new FlzEstDemoConflict(__('Eine Demo-Lehrkraftadresse ist bereits ohne Herkunftsnachweis vorhanden. Es wurden keine Demo-Daten angelegt. Verwenden Sie eine leere Demo-Installation.', 'flz-elternsprechtag'));
	}

	$teacher_data['created'] = true;
	$teacher = new FlzEstTeacher( $teacher_data );
	$teacher->save();

	return $teacher;
}

function flzest_ensure_teacher_slots( FlzEstTeacher $teacher ): void
{
	foreach ( getAppointmentsSlots() as $slot ) {
		if ( flzEstAppointment::get_by_fields( array( 'teacher_id' => (int) $teacher->id, 'start' => (int) $slot['start'] ) ) instanceof flzEstAppointment ) {
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

	$appointment = flzEstAppointment::get_by_fields( array( 'teacher_id' => (int) $teacher->id, 'start' => (int) $slots[0]['start'] ) );
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
		throw new FlzEstDemoConflict(__('Eine Demo-Elternadresse ist bereits ohne Herkunftsnachweis vorhanden. Es wurden keine Demo-Daten angelegt. Verwenden Sie eine leere Demo-Installation.', 'flz-elternsprechtag'));
	}

	$parent = new FlzEstParent( $data );
	$parent->save();

	return $parent;
}

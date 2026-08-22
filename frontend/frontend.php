<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

use JetBrains\PhpStorm\NoReturn;

/**
 * @throws ReflectionException
 */
function flzest_probeunterricht_form( $atts ): false|string {
	$frontend_notice = '';
	try {
		$nextEST = FlzEstSetting::get_value_by_name( 'NextParentsDay' );
		if ( isset( $_GET['appointment_id'], $_GET['token'] ) ) {
			$appointment = FlzEstAppointment::get_by_id( absint( wp_unslash( $_GET['appointment_id'] ) ) );
			if ( ! $appointment instanceof FlzEstAppointment ) {
				$frontend_notice = flz_ui()->notice( 'Der Bestätigungslink ist ungültig.', 'error' );
			} else {
				$frontend_notice = $appointment->activate(
					sanitize_text_field( wp_unslash( $_GET['token'] ) )
				);
			}
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: '';
		if ( 'post' === $request_method ) {
			if (
				! isset( $_POST['flzest_nonce'] )
				|| ! wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['flzest_nonce'] ) ),
					'flzest_book_appointment'
				)
			) {
				throw new RuntimeException( 'Die Nonce-Prüfung der Elternsprechtagsbuchung ist fehlgeschlagen.' );
			}
		}

		$selected_id = isset( $_POST['selected'] ) ? absint( wp_unslash( $_POST['selected'] ) ) : 0;
		$selected = $selected_id > 0
			? FlzEstAppointment::get_by_id( $selected_id )
			: new FlzEstAppointment( array() );
		if ( ! $selected instanceof FlzEstAppointment ) {
			throw new UnexpectedValueException( 'Der ausgewählte Elternsprechtagstermin wurde nicht gefunden.' );
		}

		if ( isset( $_POST['teacher'] ) ) {
			$teacher = FlzEstTeacher::get_by_id( absint( wp_unslash( $_POST['teacher'] ) ) );
			if ( ! $teacher instanceof FlzEstTeacher ) {
				throw new UnexpectedValueException( 'Die ausgewählte Lehrkraft wurde nicht gefunden.' );
			}
			$selected->teacher = $teacher;
		}

		if ( isset( $_POST['appointment'] ) ) {
			$selected = setAppointment( $selected, absint( wp_unslash( $_POST['appointment'] ) ) );
		}
		if ( isset( $_POST['parent'] ) ) {
			$parent_data = map_deep( wp_unslash( $_POST['parent'] ), 'sanitize_text_field' );
			if ( ! is_array( $parent_data ) ) {
				throw new UnexpectedValueException( 'Die Elternangaben besitzen kein gültiges Array-Format.' );
			}
			$selected = setParent( $selected, $parent_data );
		}

		if ( empty( $selected->errors() ) ) {
			$selected->confirmationToken = wp_generate_password( 48, false, false );
			$selected->confirmationExpiration = time() + 2 * DAY_IN_SECONDS;
			flzest_persist_booking( $selected );

			try {
				sendMails( $selected );
				redirectToThankYouPage( $atts );
			} catch ( Throwable $mail_error ) {
				flzest_log_error( $mail_error, 'Versenden der Buchungs-E-Mails nach gespeicherter Terminbuchung' );
				return '<p class="flz-est-error">Der Termin wurde gespeichert, aber mindestens eine E-Mail konnte nicht versendet werden. Bitte kontaktieren Sie die Schule.</p>';
			}
		}
	} catch ( Throwable $error ) {
		if ( flzest_has_appointment_unavailable_cause( $error ) ) {
			return '<p class="flz-est-error">Dieser Termin wurde zwischenzeitlich gebucht. Bitte wählen Sie einen anderen Termin.</p>';
		}
		flzest_log_error( $error, 'Verarbeiten der öffentlichen Elternsprechtagsseite' );
		return '<p class="flz-est-error">Die Anfrage konnte wegen eines technischen Fehlers nicht verarbeitet werden. Bitte später erneut versuchen.</p>';
	}

	ob_start();
	echo wp_kses_post( $frontend_notice );
	include plugin_dir_path( __FILE__ ) . '../templates/frontend-form.php';

	return (string) ob_get_clean();
}

/**
 * Speichert Elternangaben und reserviert den Termin in derselben Transaktion.
 */
function flzest_persist_booking( FlzEstAppointment $selected ): void {
	flz_wpdb_objects\FlzWpdbTransaction::run(
		static function () use ( $selected ): void {
			$selected->parent->save();
			$selected->claim_for_parent_if_available();
		},
		'Speichern einer Elternsprechtagsbuchung mit Elternangaben'
	);
}

/**
 * Erkennt den fachlichen Konflikt auch durch die Transaktions-Exceptionkette.
 */
function flzest_has_appointment_unavailable_cause( Throwable $error ): bool {
	$current = $error;
	do {
		if ( $current instanceof FlzEstAppointmentUnavailableException ) {
			return true;
		}
		$current = $current->getPrevious();
	} while ( $current instanceof Throwable );

	return false;
}

function sendMails( FlzEstAppointment $selected ): void {
	// Prepare email data
	$teacherEmail = $selected->teacher->email;
	$parentEmail = $selected->parent->email;

	if (intval(FlzEstSetting::get_value_by_name( "TestMode" )))
	{
		$teacherEmail = 'private-test@example.test';
		$parentEmail = 'private-test@example.test';

	}
	// Prepare email headers
	$headers[] = 'From: Tagore Gymnasium <post@tagore-gymnasium.de>';
	$headers[] = 'Content-Type: text/plain; charset=UTF-8';
	// Construct your email message
	$teachers_message = "Hallo ".$selected->teacher->get_gender_as_anrede()." ".$selected->teacher->firstName." ".$selected->teacher->name."\n";
	$teachers_message.= "Es gibt eine neue Buchung für den Elternsprechtag am ". flz_ui_format_date( $selected->start )."\n";
	$teachers_message.= "Beginn: ".date('H:i', $selected->start)."\n";
	$teachers_message.= "Ende: ".date('H:i', $selected->end)."\n";
	$teachers_message.= "Name: ".$selected->parent->get_gender_as_anrede()." ".$selected->parent->firstName." ".$selected->parent->name."\n";
	$teachers_message.= "Name Schüler:in: ".$selected->parent->studentName."\n";
	$teachers_message.= "Klasse Schüler:in: ".$selected->parent->studentClass."\n";

	//echo $teachers_message."<hr>";
	// Send the email
	$teacher_mail_sent = wp_mail($teacherEmail, 'Elternsprechtag', $teachers_message, $headers);


	// Baue den Aktivierungslink
	$link=get_permalink() . '?appointment_id=' . $selected->id . '&token=' . $selected->confirmationToken;


	$parents_message = "Hallo ".$selected->parent->get_gender_as_anrede()." ".$selected->parent->firstName." ".$selected->parent->name."\n";
	$parents_message.= "Sie haben zum Elternsprechtag des Tagore-Gymnasiums am ". flz_ui_format_date( $selected->start )."\n";
	$parents_message.= "einen Termin bei ".$selected->teacher->get_gender_as_anrede()." ".$selected->teacher->firstName." ".$selected->teacher->name."\n";
	$parents_message.= "Für ".$selected->parent->studentName." gebucht"."\n";
	$parents_message.= "Beginn: ".date('H:i', $selected->start)."\n";
	$parents_message.= "Ende: ".date('H:i', $selected->end)."\n";
	$parents_message.= "Achtung, sie müssen den Termin durch Klicken dieses Links bestätigen!"."\n"."\n";
	$parents_message.= "<a href='". esc_url($link) ."'>". $link ."</a>" . "\n" . "\n";
	$parents_message.= "Beste Grüße";
	//echo $parents_message."<hr>";
	// Send the email
	$parent_mail_sent = wp_mail($parentEmail, 'Elternsprechtag', $parents_message, $headers);
	if ( ! $teacher_mail_sent || ! $parent_mail_sent ) {
		$failed = array();
		if ( ! $teacher_mail_sent ) {
			$failed[] = 'Lehrkraft';
		}
		if ( ! $parent_mail_sent ) {
			$failed[] = 'Elternteil';
		}
		throw flzest_operation_error(
			new RuntimeException( 'wp_mail() meldete einen Fehler für: ' . implode( ', ', $failed ) ),
			'Senden der Elternsprechtags-E-Mails für Termin-ID ' . (string) $selected->id
		);
	}
}

/**
 * @throws ReflectionException
 */
function getUnbookedAppointmentsByTeacherId($teacher_id): array {
	return flzEstAppointment::get_all_by(
		[
			'teacher_id' => absint( $teacher_id ),
			'parent_id'  => null,
		]
	);
}
/**
 * @throws ReflectionException
 */
function setAppointment( $selected, int $selected_appointment_id ): flzEstAppointment {

	// if change of teacher or appointment after parent data is already given
	$parent=$selected->parent??null;
	$selected  = flzEstAppointment::get_by_id( $selected_appointment_id );
	if ( ! $selected instanceof FlzEstAppointment ) {
		throw new UnexpectedValueException( 'Der ausgewählte Elternsprechtagstermin wurde nicht gefunden.' );
	}
	$selected->parent=$parent;
	return $selected;
}


function setParent( $selected, array $parent_data ): flzEstAppointment {
	$parent=$selected->parent??new FlzEstParent();
	$parent->assignPostData(
		$parent_data,
		[ 'name', 'firstName', 'gender', 'email', 'studentName', 'studentClass', 'gdprChecked' ]
	);
	$selected->parent=$parent;
	return $selected;
}

#[NoReturn] function redirectToThankYouPage( $atts ): void {
	$thank_you_page_id  = intval( $atts['danke'] );
	$thank_you_page_url = get_permalink( $thank_you_page_id );
	if ( ! is_string( $thank_you_page_url ) || ! wp_safe_redirect( $thank_you_page_url ) ) {
		throw new RuntimeException( 'Die Weiterleitung zur Danke-Seite ist fehlgeschlagen.' );
	}
	exit;
}
/**
 * Rendert ein Elternsprechtags-Frontend-Template als String.
 *
 * @param array<string,mixed> $vars Template-Variablen.
 */
function flzest_get_frontend_template( string $template, array $vars = array() ): string {
	$template = trim( str_replace( array( '..', '\\' ), '', $template ), '/' );
	$file     = plugin_dir_path( __FILE__ ) . '../templates/' . $template . '.php';
	if ( ! is_readable( $file ) ) {
		throw new RuntimeException( 'Das Elternsprechtags-Template "' . $template . '" wurde nicht gefunden.' );
	}

	ob_start();
	extract( $vars, EXTR_SKIP );
	include $file;

	return (string) ob_get_clean();
}

/**
 * @param array<int,FlzEstTeacher> $teachers
 * @return array<string,string>
 */
function flzest_teacher_options( array $teachers ): array {
	$options = array();
	foreach ( $teachers as $teacher ) {
		if ( $teacher instanceof FlzEstTeacher && ! empty( $teacher->id ) ) {
			$options[ (string) $teacher->id ] = $teacher->get_gender_as_anrede() . ' ' . $teacher->name;
		}
	}

	return $options;
}

function flzest_render_step1( FlzEstAppointment $selected ): string {
	return flzest_get_frontend_template(
		'frontend-step1',
		array(
			'selected' => $selected,
			'teachers' => FlzEstTeacher::get_all_by(),
		)
	);
}

function flzest_render_step2( FlzEstAppointment $selected ): string {
	return flzest_get_frontend_template(
		'frontend-step2',
		array(
			'selected' => $selected,
			'appointments' => getUnbookedAppointmentsByTeacherId( $selected->teacher->id ),
		)
	);
}

function flzest_render_step3( FlzEstAppointment $selected ): string {
	return flzest_get_frontend_template(
		'frontend-step3',
		array(
			'selected' => $selected,
			'errors' => $selected->errors() ?? array(),
		)
	);
}
// Hinzufügen des Formulars im Frontend
add_shortcode( 'flzest', 'flzest_probeunterricht_form' );

/**
 * Registriert den Gutenberg-Block für das Elternsprechtagsformular.
 */
function flzest_register_blocks(): void {
	if ( ! function_exists( 'flz_ui_register_shortcode_block' ) ) {
		return;
	}

	flz_ui_register_shortcode_block( array(
		'name'        => 'flz/elternsprechtag',
		'shortcode'   => 'flzest',
		'title'       => 'FLZ Elternsprechtag',
		'description' => 'Buchungsformular für den Elternsprechtag.',
		'icon'        => 'calendar-alt',
		'keywords'    => array( 'elternsprechtag', 'termin', 'flz' ),
		'attributes'  => array(
			'danke' => array(
				'type'    => 'string',
				'default' => '',
			),
		),
		'fields'      => array(
			'danke' => array(
				'label'       => 'Danke-Seite-ID',
				'description' => 'Optional: WordPress-Seiten-ID für die Weiterleitung nach erfolgreicher Buchung.',
			),
		),
	) );
}

add_action( 'init', 'flzest_register_blocks' );

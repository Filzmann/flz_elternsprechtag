<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

use JetBrains\PhpStorm\NoReturn;
//require_once(plugin_dir_path(__FILE__) .'..\classes\FlzEstAppointment.php' );

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
		if ( 'POST' === $request_method ) {
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
			flz_wpdb_objects\FlzWpdbTransaction::run(
				static function () use ( $selected ): void {
					$selected->parent->save();
					$selected->save();
				},
				'Speichern einer Elternsprechtagsbuchung mit Elternangaben'
			);

			try {
				sendMails( $selected );
				redirectToThankYouPage( $atts );
			} catch ( Throwable $mail_error ) {
				flzest_log_error( $mail_error, 'Versenden der Buchungs-E-Mails nach gespeicherter Terminbuchung' );
				return '<p class="flz-est-error">Der Termin wurde gespeichert, aber mindestens eine E-Mail konnte nicht versendet werden. Bitte kontaktieren Sie die Schule.</p>';
			}
		}
	} catch ( Throwable $error ) {
		flzest_log_error( $error, 'Verarbeiten der öffentlichen Elternsprechtagsseite' );
		return '<p class="flz-est-error">Die Anfrage konnte wegen eines technischen Fehlers nicht verarbeitet werden. Bitte später erneut versuchen.</p>';
	}

	ob_start();
	echo wp_kses_post( $frontend_notice );
	include plugin_dir_path( __FILE__ ) . '../templates/frontend-form.php';

	return (string) ob_get_clean();
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
function select(array $options, string $name='', $submit=true, $selected=0): string {


	$select_options = array();
	foreach ( $options as $option )
	{
		$select_options[ (string) $option->id ] = $option->get_gender_as_anrede() . ' ' . $option->name;
	}
	return flz_ui()->field( array(
		'type'        => 'select',
		'name'        => $name,
		'id'          => $name,
		'label'       => 'Lehrkraft',
		'value'       => (string) $selected,
		'placeholder' => '--- bitte auswählen ---',
		'options'     => $select_options,
		'attrs'       => $submit ? array( 'onchange' => 'this.form.submit();' ) : array(),
	) );
}
function step1($selected): string {
	$ui = flz_ui();
	$out = $ui->form_start( array( 'method' => 'post', 'nonce' => 'flzest_book_appointment', 'nonce_name' => 'flzest_nonce' ) );
	$out.= select(
		options: FlzEstTeacher::get_all_by(),
		name: "teacher",
		selected: $selected->teacher?$selected->teacher->id:0
	);
	if ( $selected->teacher )
		$out.=step2($selected);
	else
		$out.="Wählen Sie eine Lehrkraft aus!";
	$selected4post = $selected->id ? (int) $selected->id : 0;
	$out .= $ui->hidden( 'selected', $selected4post, array( 'id' => 'selected' ) );
	$out .= $ui->form_end();
	return $out;
}

function step2($selected): string {
	$appointments=getUnbookedAppointmentsByTeacherId($selected->teacher->id);
	$out = "<div class='button-group'>
        <h3>Mögliche Termine
            bei ";
	$out .= esc_html( $selected->teacher->get_gender_as_anrede() . ' ' . $selected->teacher->name );
	$out .= '</h3>';
	if(empty($appointments)) $out.='Leider sind alle Termine schon ausgebucht!';
	else
	{
		foreach ( $appointments as $appointment ) {
			$out .= flz_ui()->button( array(
				'label'    => date( 'H:i', $appointment->start ),
				'type'     => 'submit',
				'variant'  => 'secondary',
				'icon'     => 'clock',
				'icon_alt' => 'Termin um ' . date( 'H:i', $appointment->start ) . ' auswählen',
				'attrs'    => array(
					'name'  => 'appointment',
					'id'    => 'appointment-' . (int) $appointment->id,
					'value' => (string) $appointment->id,
				),
			) );
		}
		$out.="</div>";
		if ( $selected->id )
		{
			$out.=step3($selected);
		}
	}
	return $out;
}
function step3($selected): string {
	$errors=$selected->errors()??[];
	$out = "<p>Für Ihren Termin am ";
	$out .= esc_html( flz_ui_format_date( $selected->start ) );
	$out.=" um ";
	$out .= esc_html( date( "H:i", $selected->start ) );

	$out .= ' bei ' . esc_html( $selected->teacher->get_gender_as_anrede() . ' ' . $selected->teacher->name );
	$out.=" benötigen wir noch folgende Daten von Ihnen:</p>";
	$out.= '
        <div class="form-wrap">';

	$options = array(
		"" => "keine Angabe/divers",
		"f" => "Frau",
		"m" => "Herr"
	);
	$selectedOption = $selected->parent?$selected->parent->gender:''; // Selected option which could be '', 'f', or 'm'
	$out.=createSelectFieldWithLabel(
		'parent[gender]',
		'parent[gender]',
		'Anrede',
		$options,
		$selectedOption
	);


	$out .= createInputFieldWithLabel(
		'text',
		'parent[name]',
		'parent[name]',
		'Name',
		$selected->parent->name??'',
		in_array('NO_NAME', $errors),
		'Bitte geben Sie Ihren Namen ein'
	);

	$out .= createInputFieldWithLabel(
		'text',
		'parent[firstName]',
		'parent[firstName]',
		'Vorname',
		$selected->parent->firstName??''
	);
	$out .= createInputFieldWithLabel(
		'email',
		'parent[email]',
		'parent[email]',
		'Email',
		$selected->parent->email??'',
		in_array('NO_EMAIL', $errors),
		'Bitte geben Sie Ihre E-Mail ein'
	);

	$out .= createInputFieldWithLabel(
		'text',
		'parent[studentName]',
		'parent[studentName]',
		'Name Schüler*in',
		$selected->parent->studentName??'',
		in_array('NO_STUDENTS_NAME', $errors),
		'Bitte geben Sie den Namen des Kindes ein'
	);


	$out .= createInputFieldWithLabel(
		'text',
		'parent[studentClass]',
		'parent[studentClass]',
		'Klasse Schüler*in',
		$selected->parent->studentClass??'',
		in_array('NO_STUDENTS_CLASS', $errors),
		'Bitte geben Sie die Klasse ihres Kindes ein'
	);

	if(in_array('NO_GDPR', $errors)) $out.= flz_ui()->notice( 'Bitte bestätigen Sie die Datenschutzerklärung.', 'error' );

	$out .= flz_ui()->field( array(
		'type'    => 'checkbox',
		'name'    => 'parent[gdprChecked]',
		'id'      => 'parent_gdprChecked',
		'label'   => 'Ich habe die Datenschutzerklärung (einschließlich der angegebenen Löschfristen) zur Kenntnis genommen.',
		'checked' => $selected->parent && $selected->parent->gdprChecked === 'on',
	) );
	$out.='
			<p>
			Ich stimme zu, dass meine Angaben und Daten zur Beantwortung meiner Anfrage elektronisch gespeichert werden.
			Die Einwilligung kann jederzeit per E-Mail an technik@tagore-gymnasium.de widerrufen werden.
			Von einer Übersendung sensibler Daten (zum Beispiel Gesundheitsdaten) bitten wir abzusehen
			</p>
        </div>';
	$out .= flz_ui()->button( array(
		'label'    => 'Termin verbindlich buchen',
		'type'     => 'submit',
		'variant'  => 'primary',
		'icon'     => 'check',
		'icon_alt' => 'Termin verbindlich buchen',
		'attrs'    => array(
			'name' => 'save',
			'id'   => 'save',
		),
	) );


	return $out;
}

function createInputFieldWithLabel(string $inputType, string $inputName, string $inputId, string $labelText, string $inputValue = '', bool $isRequired = false, string $errorMsg = ''): string {
	$out = $isRequired ? flz_ui()->notice( $errorMsg, 'error' ) : '';
	$out .= flz_ui()->input( $inputType, array(
		'name'  => $inputName,
		'id'    => str_replace( array( '[', ']' ), '_', $inputId ),
		'label' => $labelText,
		'value' => $inputValue,
	) );
	return $out;
}

function createSelectFieldWithLabel($fieldName, $fieldId, $labelText, $options, $selectedOption = null): string {
	return flz_ui()->field( array(
		'type'    => 'select',
		'name'    => $fieldName,
		'id'      => str_replace( array( '[', ']' ), '_', $fieldId ),
		'label'   => $labelText,
		'value'   => (string) $selectedOption,
		'options' => $options,
	) );
}
// Hinzufügen des Formulars im Frontend
add_shortcode( 'flzest', 'flzest_probeunterricht_form' );

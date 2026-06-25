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
				$frontend_notice = '<p class="flz-est-error">Der Bestätigungslink ist ungültig.</p>';
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
	$teachers_message.= "Es gibt eine neue Buchung für den Elternsprechtag am ". date("d.m.Y", $selected->start)."\n";
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
	$parents_message.= "Sie haben zum Elternsprechtag des Tagore-Gymnasiums am ". date("d.m.Y", $selected->start)."\n";
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


	$out = "<select name=\"$name\" id=\"$name\" ";
	if($submit)
		$out.="onchange=\"this.form.submit();\" ";
	$out.=">";
		$out.="<option>--- bitte auswählen ---</option>";
	foreach ( $options as $option )
	{
		$out.= "<option value='$option->id'";
		if ( $option->id == $selected ) {
			$out.= " selected ";
		}
		$out.= ">";
		$out.= $option->get_gender_as_anrede();
		$out.=" ";
		$out.= $option->name;
		$out.="</option>";
	}
	$out.="</select>";
	return $out;
}
function step1($selected): string {
	$out = '<form method="post">';
	$out .= wp_nonce_field( 'flzest_book_appointment', 'flzest_nonce', true, false );
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
	$out .= "<input type='hidden' name='selected' id='selected' value='" . esc_attr( $selected4post ) . "'>";
	$out.='</form>';
	return $out;
}

function step2($selected): string {
	$appointments=getUnbookedAppointmentsByTeacherId($selected->teacher->id);
	$out = "<div class='button-group'>
        <h3>Mögliche Termine
            bei ";
	$out.= $selected->teacher->get_gender_as_anrede() . " " . $selected->teacher->name;
	$out.="</h2>";
	if(empty($appointments)) $out.='Leider sind alle Termine schon ausgebucht!';
	else
	{
		foreach ( $appointments as $appointment ) {
			$out.= "<button name='appointment' id='appointment' value='$appointment->id' onclick='this.form.submit();'>" . date( "H:i", $appointment->start ) . " </button>";
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
	$out.= date( "d.m.Y", $selected->start);
	$out.=" um ";
	$out.= date( "H:i", $selected->start);

	$out.=" bei ".$selected->teacher->get_gender_as_anrede()." ";
	$out.=$selected->teacher->name;
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

	if(in_array('NO_GDPR', $errors)) $out.= "<span style='color:red;'>Bitte bestätigen Sie die Datenschutzerklärung</span>";

	$out.='<input type="checkbox" name="parent[gdprChecked]" id="parent[gdprChecked]" ';
	if($selected->parent)  $out.= $selected->parent->gdprChecked=='on'?" checked=checked ":'';
	$out.='>
            <label for="parent[gdprChecked]">
            Ich habe die Datenschutzerklärung (einschließlich der angegebenen Löschfristen) zur Kenntnis genommen. 
			</label>
			<p>
			Ich stimme zu, dass meine Angaben und Daten zur Beantwortung meiner Anfrage elektronisch gespeichert werden.
			Die Einwilligung kann jederzeit per E-Mail an technik@tagore-gymnasium.de widerrufen werden.
			Von einer Übersendung sensibler Daten (zum Beispiel Gesundheitsdaten) bitten wir abzusehen
			</p>
        	<input type="submit" name="save" id="save" value="Termin verbindlich buchen!" /><br />
        </div>';


	return $out;
}

function createInputFieldWithLabel(string $inputType, string $inputName, string $inputId, string $labelText, string $inputValue = '', bool $isRequired = false, string $errorMsg = ''): string {
	$isRequiredMsg = $isRequired? "<span style='color: red;'>$errorMsg</span>" : "";
	return "
    $isRequiredMsg
    <label for='$inputId'>$labelText</label>
    <input type='$inputType' name='$inputName' id='$inputId' value='$inputValue'><br/>";
}

function createSelectFieldWithLabel($fieldName, $fieldId, $labelText, $options, $selectedOption = null): string {
	$out = "<label for='$fieldId'>$labelText</label>";
	$out .= "<select name='$fieldName' id='$fieldId'>";

	foreach($options as $value => $label){
		$selected = '';
		if($selectedOption && $selectedOption == $value) {
			$selected = 'selected = selected';
		}
		$out .= "<option value='$value' $selected>$label</option>";
	}

	$out .= "</select><br />";

	return $out;
}
// Hinzufügen des Formulars im Frontend
add_shortcode( 'flzest', 'flzest_probeunterricht_form' );

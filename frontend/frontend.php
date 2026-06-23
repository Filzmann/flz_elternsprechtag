<?php

use JetBrains\PhpStorm\NoReturn;
//require_once(plugin_dir_path(__FILE__) .'..\classes\FlzEstAppointment.php' );

/**
 * @throws ReflectionException
 */
function flzest_probeunterricht_form( $atts ): false|string {

	$nextEST          = FlzEstSetting::get_value_by_name( "NextParentsDay" );
	// aktivierungslink geklickt
	if (isset($_GET['appointment_id']) && isset($_GET['token'])) {

		$appointment=FlzEstAppointment::get_by_id(intval($_GET['appointment_id']));
		echo $appointment->activate(sanitize_text_field($_GET['token']));
	}

	$selected = isset($_POST["selected"])?unserialize( base64_decode($_POST["selected"])) : new FlzEstAppointment([]);

	if ( isset( $_POST["teacher"] ) )
	{
		$teacher=FlzEstTeacher::get_by_id( intval( $_POST["teacher"] ));
		$selected->teacher=$teacher;
	}


	if ( isset( $_POST["appointment"] ) ) $selected = setAppointment( $selected );
	if ( isset( $_POST["parent"] ) ) $selected = setParent( $selected );

	if ( empty($selected->errors()) ) {

		// Generiere einen eindeutigen Token
		$selected->confirmationToken = md5(uniqid());
		// Speichere den Token und das Ablaufdatum in der Datenbank
		$selected->confirmationExpiration = strtotime('+48 hours');
		$selected->parent->save();
		$selected->save();
		sendMails($selected);
		redirectToThankYouPage( $atts );
	}


	ob_start();
	include( plugin_dir_path( __FILE__ ) . '../templates/frontend-form.php' );

	return ob_get_clean();
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
	wp_mail($teacherEmail, 'Elternsprechtag', $teachers_message, $headers);


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
	wp_mail($parentEmail, 'Elternsprechtag', $parents_message, $headers);
}

/**
 * @throws ReflectionException
 */
function getUnbookedAppointmentsByTeacherId($teacher_id): array {

	$where = "teacher_id = " . $teacher_id;
	$where.= " AND parent_id IS NULL";
	return flzEstAppointment::get_all( where: $where );
}
/**
 * @throws ReflectionException
 */
function setAppointment( $selected ): flzEstAppointment {

	$selected_appointment_id = intval( $_POST["appointment"] );
	// if change of teacher or appointment after parent data is already given
	$parent=$selected->parent??null;
	$selected  = flzEstAppointment::get_by_id( $selected_appointment_id );

	$selected->parent=$parent;
	return $selected;
}


function setParent( $selected ): flzEstAppointment {
	$parent=$selected->parent??new FlzEstParent();
	$parent->assignPostData( $_POST["parent"] );
	$selected->parent=$parent;
	return $selected;
}

#[NoReturn] function redirectToThankYouPage( $atts ): void {
	$thank_you_page_id  = intval( $atts['danke'] );
	$thank_you_page_url = get_permalink( $thank_you_page_id );
	wp_redirect( $thank_you_page_url );
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
	$out.= select(
		options: FlzEstTeacher::get_all(),
		name: "teacher",
		selected: $selected->teacher?$selected->teacher->id:0
	);
	if ( $selected->teacher )
		$out.=step2($selected);
	else
		$out.="Wählen Sie eine Lehrkraft aus!";
	$selected4post=base64_encode(serialize($selected));
	$out.="<input type='hidden' name='selected' id='elected' value='$selected4post'>";
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
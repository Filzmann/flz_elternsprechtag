<?php


// Funktion zur Anzeige der appointments-Seite im Backend
const SECONDS_IN_MINUTE = 60;

function resetAppointments(): void {
	flzEstAppointment::truncate_table();
	FlzEstParent::truncate_table( true );
	createAppointments();
}
function processAppointmentForm(): void
{
	$isFormSubmitted = isset($_POST['submit']);
	if (!$isFormSubmitted) {
		return;
	}
	$id=$_POST['id'];
	$appointment = FlzEstAppointment::get_by_id($id);
	$appointment->parent=$appointment->parent?:new FlzEstParent([]);

	if( countFilledFieldsInArray($_POST['parent']) > 0){
		$appointment->parent->assignPostData($_POST['parent']);
		$appointment->parent->save();
	}
	else
	{
		if(isset($appointment->parent->id))
			$appointment->parent->delete();
		$appointment->parent = null;
	}
	$appointment->save();
}

function countFilledFieldsInArray($array): int {
	$count=0;
	foreach ( $array as $value ) {
		//debug($value);
		if ( !empty(trim($value)) ) {
			$count++;
		}
	}
	return $count;
}
function getSelectedAppointment(): FlzEstAppointment
{
	if (isset( $_POST[ 'appointment_edit' ]))
	{
		$id = intval($_POST['appointment_id']);
		$appointment= FlzEstAppointment::get_by_id($id);
	}
	else $appointment= new FlzEstAppointment([]);

	$appointment->parent=$appointment->parent?:new FlzEstParent([]);
	//debug($appointment,"get selected");
	return $appointment;

}
function processAppointmentsCsvFile(): array|null {
	$unprocessedLines=array();

	try {
		$isFormSubmitted = isset($_POST['submit_csv']);
		if (!$isFormSubmitted) {
			return null;
		}
		if (isset($_FILES['appointments-csv']['tmp_name'])) {
			$fileOriginalName = $_FILES['appointments-csv']['name'];
			// sanitize the file input
			$filePath = $_FILES['appointments-csv']['tmp_name'];
			//verify if the file is a CSV file
			if (pathinfo($fileOriginalName, PATHINFO_EXTENSION) != 'csv') {
				throw new Exception('Das ist keine csv-Datei');
			}
			//open the file
			$fileHandle = fopen($filePath, 'r');
			if (!$fileHandle) {
				throw new Exception("Konnte die Datei nicht öffnen");
			}
			fgetcsv($fileHandle);
			//read each line and make a new teacher object
			while(($line = fgetcsv($fileHandle, separator: ';')) !== false) {
				// Assuming FlzEstTeachers accepts an array to create a new teacher

				$teacher= FlzEstTeacher::get_by_email($line[0]);

				if(!$teacher)
				{
					$line['error']='Kein Lehrer mit dieser Email vorhanden';
					$unprocessedLines[]=$line;
					continue;
				}

				$appointment=FlzEstAppointment::get_by_teacher_and_start($teacher, $line[1]);

				if(!$appointment)
				{
					$line['error']='Appointment not found';
					$unprocessedLines[]=$line;
					continue;
				}
				if($appointment->parent)
				{
					$line['error']='Dieser Slot ist bereits belegt, bitte manuell prüfen';
					$unprocessedLines[]=$line;
					continue;
				}
				else{
					$data=array(
						'studentName'=>$line[2],
						'studentClass'=>$line[3],
						'gdprChecked'=>true,
						'name'=>'Vorbelegung Schule',
						'firstName'=>'',
						'email'=>'info@tagore-gymnasium.de'
					);
					$parent=new FlzEstParent(
						$data
					);
					$parent->save();

				}
				$appointment->parent=$parent;
				//debug($appointment);
				$appointment->isConfirmed=true;
				$appointment->save();

			}

			fclose($fileHandle);

		} else {
			throw new Exception("Keine Datei hochgeladen");
		}
	} catch (Exception $e) {
		// Echoing the script tag with alert to show the error message
		echo '<script>alert("'.$e->getMessage().'")</script>';
	}
	return $unprocessedLines;
}


function createAppointments(): void {
	$slots = getAppointmentsSlots();
	$teachers = FlzEstTeacher::get_all();
	foreach ( $teachers as $teacher ) {
		$teacher->createTeachersAppointments($slots);
	}
}



function flzest_appointments_page(): void {
	

	if ( isset( $_POST['newEST'] ) ) {
		resetAppointments();
	}
	if ( isset( $_POST['appointment_empty'] ) ) {
		empty_appointment(intval($_POST['appointment_empty']['id']));

	}
	$unprocessed=processAppointmentsCsvFile();
	if($unprocessed)
	{
		echo "Folgende Datensätze konnten nicht verarbeitet werden:<br>";
		echo"<textarea cols='100' rows='5'>";
		foreach ($unprocessed as $line)
			echo implode(";",$line)."\n";
		echo"</textarea>";
	}
	$selected=getSelectedAppointment();


	processAppointmentForm();
	// show appointment table
	$appointments = flzEstAppointment::get_all();

	
	$csvFile = createCsv($appointments, 'appointments.csv', "Name Lehrer; Vorname Lehrer; Email Lehrer; Beginn; Ende; Name Eltern; Vorname Eltern; Email Eltern; Name Schüler:in; Klasse Schüler:in; bestätigt\n");
	include( plugin_dir_path( __FILE__ ) . '../templates/appointments.php' );
}

function empty_appointment( int $appointment_id ) {
	$appointment = FlzEstAppointment::get_by_id( $appointment_id );
	$appointment->parent = null;
	$appointment->isConfirmed = false;
	$appointment->confirmationToken=null;
	$appointment->confirmationExpiration=0;
	$appointment->save();
	echo "<script>alert('Termin erfolgreich geleert!');</script>";
}


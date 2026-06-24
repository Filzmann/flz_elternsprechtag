<?php
// Funktion zur Anzeige der Lehrer-Seite im Backend


function flzest_teachers_page(): void
{
	$selected=getSelectedTeacher();
	processTeacherForm();
	processTeacherCsvFile();
	handleTeacherDeletion();

	$teachers = FlzEstTeacher::get_all_by( order_by: 'name' );
	$csvFile = flz_wpdb_objects_create_csv($teachers, 'teachers.csv', "Geschlecht(m/f); Name; Vorname; Email\n");

	include( plugin_dir_path( __FILE__ ) . '../templates/teachers.php' );
}
function getSelectedTeacher(): FlzEstTeacher
{
	if (!isset( $_POST[ 'teacher_edit' ]))
		return new FlzEstTeacher([]);

	$id = intval($_POST['teacher_id']);
	return FlzEstTeacher::get_by_id($id);
}
/**
 * Processes the submitted teacher form.
 * The function creates and saves a FlzEstTeacher object
 * with the form details.
 */
function processTeacherForm(): void
{
	$isFormSubmitted = isset($_POST['submit']);
	if (!$isFormSubmitted) {
		return;
	}

	$teacher = new FlzEstTeacher();
	$teacher->assignPostData( $_POST, [ 'name', 'firstName', 'gender', 'email' ] );
	$teacher->save();
}

function processTeacherCsvFile(): void
{

	try {
		$isFormSubmitted = isset($_POST['submit_csv']);
		if (!$isFormSubmitted) {
			return;
		}
		if (isset($_FILES['teacher-csv']['tmp_name'])) {
			$fileOriginalName = $_FILES['teacher-csv']['name'];
			// sanitize the file input
			$filePath = $_FILES['teacher-csv']['tmp_name'];
			//verify if the file is a CSV file
			if (pathinfo($fileOriginalName, PATHINFO_EXTENSION) != 'csv') {
				throw new Exception('Das ist keine csv-Datei');
			}
			//open the file
			$fileHandle = fopen($filePath, 'r');
			if (!$fileHandle) {
				throw new Exception("Konnte die Datei nicht öffnen");
			}
			FlzEstTeacher::truncate_table(true);
			//throw away the first line - usually the header
			fgetcsv($fileHandle);
			//read each line and make a new teacher object
			while(($line = fgetcsv($fileHandle, separator: ';')) !== false) {
				// Assuming FlzEstTeachers accepts an array to create a new teacher
				$teacher = new FlzEstTeacher([
					"gender" => $line[0],
					"name" => $line[1],
					"firstName" => $line[2],
					"email" => $line[3],
				]);
				$teacher->save();
			}
			fclose($fileHandle);
		} else {
			throw new Exception("Keine Datei hochgeladen");
		}
	} catch (Exception $e) {
		// Echoing the script tag with alert to show the error message
		echo '<script>alert("'.$e->getMessage().'")</script>';
	}
}


function handleTeacherDeletion(): void
{
	if ( ! isset( $_POST[ 'teacher_delete' ] ) ) return;

	$id = intval($_POST['teacher_delete']);
	$teacher = FlzEstTeacher::get_by_id($id);
	$teacher->delete();
}

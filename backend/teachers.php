<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Alle POST-Pfade laufen durch flzest_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing
// Funktion zur Anzeige der Lehrer-Seite im Backend


function flzest_teachers_page(): void
{
	try {
		flzest_teachers_page_content();
	} catch ( Throwable $error ) {
		flzest_render_admin_error( $error, 'Anzeigen und Verarbeiten der Lehrkräfte' );
	}
}

function flzest_teachers_page_content(): void
{
	flzest_assert_admin_request();
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

	$id = isset( $_POST['teacher_id'] ) ? absint( wp_unslash( $_POST['teacher_id'] ) ) : 0;
	$teacher = FlzEstTeacher::get_by_id($id);
	if ( ! $teacher instanceof FlzEstTeacher ) {
		throw new UnexpectedValueException( 'Die ausgewählte Lehrkraft wurde nicht gefunden.' );
	}
	return $teacher;
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

	$teacher_data = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
	$teacher = new FlzEstTeacher();
	$teacher->assignPostData( $teacher_data, [ 'name', 'firstName', 'gender', 'email' ] );
	flz_wpdb_objects\FlzWpdbTransaction::run(
		static fn() => $teacher->save(),
		'Anlegen einer Lehrkraft mit ihren Elternsprechtagsterminen'
	);
}

function processTeacherCsvFile(): void
{
	$fileHandle = null;
	try {
		$isFormSubmitted = isset($_POST['submit_csv']);
		if (!$isFormSubmitted) {
			return;
		}
			if (isset($_FILES['teacher-csv']['tmp_name'], $_FILES['teacher-csv']['name'])) {
				$fileOriginalName = sanitize_file_name( wp_unslash( $_FILES['teacher-csv']['name'] ) );
				// sanitize the file input
				$filePath = sanitize_text_field( wp_unslash( $_FILES['teacher-csv']['tmp_name'] ) );
			//verify if the file is a CSV file
			if (pathinfo($fileOriginalName, PATHINFO_EXTENSION) != 'csv') {
				throw new Exception('Das ist keine csv-Datei');
			}
			//open the file
			$fileHandle = fopen($filePath, 'r');
			if (!$fileHandle) {
				throw new Exception("Konnte die Datei nicht öffnen");
			}
				//throw away the first line - usually the header
				fgetcsv($fileHandle);
				$teacher_rows = array();
				while(($line = fgetcsv($fileHandle, separator: ';')) !== false) {
					if ( count( $line ) < 4 ) {
						throw new UnexpectedValueException( 'Eine Lehrkräfte-CSV-Zeile enthält weniger als vier Spalten.' );
					}
					$teacher_rows[] = array(
						'gender' => sanitize_text_field( $line[0] ),
						'name' => sanitize_text_field( $line[1] ),
						'firstName' => sanitize_text_field( $line[2] ),
						'email' => sanitize_email( $line[3] ),
					);
				}
				fclose($fileHandle);
				$fileHandle = null;
				flz_wpdb_objects\FlzWpdbTransaction::run(
					static function () use ( $teacher_rows ): void {
						foreach ( FlzEstTeacher::get_all_by() as $existing_teacher ) {
							$existing_teacher->delete();
						}
						foreach ( $teacher_rows as $teacher_data ) {
							( new FlzEstTeacher( $teacher_data ) )->save();
						}
					},
					'Ersetzen aller Lehrkräfte durch einen CSV-Import'
				);
		} else {
			throw new Exception("Keine Datei hochgeladen");
		}
		} catch (Throwable $error) {
			if ( is_resource( $fileHandle ) ) {
				fclose( $fileHandle );
			}
			throw flzest_operation_error( $error, 'Importieren der Lehrkräfte-CSV-Datei' );
	}
}


function handleTeacherDeletion(): void
{
	if ( ! isset( $_POST[ 'teacher_delete' ] ) ) return;

	$id = absint( wp_unslash( $_POST['teacher_delete'] ) );
	$teacher = FlzEstTeacher::get_by_id($id);
	if ( ! $teacher instanceof FlzEstTeacher ) {
		throw new UnexpectedValueException( 'Die zu löschende Lehrkraft wurde nicht gefunden.' );
	}
	$teacher->delete();
}

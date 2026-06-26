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
	processTeacherForm();
	processTeacherCsvFile();
	handleTeacherDeletion();

	$teachers = FlzEstTeacher::get_all_by( order_by: 'name' );
	$csvFile = flz_wpdb_objects_create_csv($teachers, 'teachers.csv', "Geschlecht(m/f); Name; Vorname; Email\n");

	include( plugin_dir_path( __FILE__ ) . '../templates/teachers.php' );
}
/**
 * Speichert das Lehrkräfteformular.
 *
 * Die ID kommt nicht über assignPostData(), sondern wird vorher explizit
 * ausgewertet. So bleibt Mass Assignment für Datenbank-IDs weiterhin gesperrt.
 */
function processTeacherForm(): void
{
	$isFormSubmitted = isset($_POST['submit']);
	if (!$isFormSubmitted) {
		return;
	}

	$teacher_data = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
	$teacher_id   = isset( $teacher_data['id'] ) && '' !== $teacher_data['id'] ? absint( $teacher_data['id'] ) : null;
	$teacher      = $teacher_id ? FlzEstTeacher::get_by_id( $teacher_id ) : new FlzEstTeacher( [] );
	if ( ! $teacher instanceof FlzEstTeacher ) {
		throw new UnexpectedValueException( 'Die zu speichernde Lehrkraft wurde nicht gefunden.' );
	}
	$teacher->assignPostData( $teacher_data, [ 'name', 'firstName', 'gender', 'email' ] );
	flz_wpdb_objects\FlzWpdbTransaction::run(
		static fn() => $teacher->save(),
		'Speichern einer Lehrkraft mit ihren Elternsprechtagsterminen'
	);
}

function processTeacherCsvFile(): void
{
	try {
		$isFormSubmitted = isset($_POST['submit_csv']);
		if (!$isFormSubmitted) {
			return;
		}
		$teacher_rows = array();
		foreach ( flz_wpdb_objects_read_uploaded_csv( 'teacher-csv', 'Importieren der Lehrkräfte-CSV-Datei' ) as $line ) {
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
	} catch (Throwable $error) {
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

<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Alle POST-Pfade laufen durch flzest_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing

// Funktion zur Anzeige der appointments-Seite im Backend
const SECONDS_IN_MINUTE = 60;

function resetAppointments(): void {
	flz_wpdb_objects\FlzWpdbTransaction::run(
		static function (): void {
			foreach ( flzEstAppointment::get_all_by() as $appointment ) {
				$appointment->delete();
			}
			foreach ( FlzEstParent::get_all_by() as $parent ) {
				$parent->delete();
			}
			createAppointments();
		},
		'Zurücksetzen aller Elternsprechtagstermine'
	);
}
function processAppointmentForm(): void
{
	$isFormSubmitted = isset($_POST['submit']);
	if (!$isFormSubmitted) {
		return;
	}
	$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
	$appointment = FlzEstAppointment::get_by_id($id);
	if ( ! $appointment instanceof FlzEstAppointment ) {
		throw new UnexpectedValueException( 'Der zu bearbeitende Termin wurde nicht gefunden.' );
	}
	$appointment->parent=$appointment->parent?:new FlzEstParent([]);

	$parent_data = isset( $_POST['parent'] )
		? map_deep( wp_unslash( $_POST['parent'] ), 'sanitize_text_field' )
		: array();
	flz_wpdb_objects\FlzWpdbTransaction::run(
		static function () use ( $appointment, $parent_data ): void {
			if ( countFilledFieldsInArray( $parent_data ) > 0 ) {
				$appointment->parent->assignPostData(
					$parent_data,
					array( 'name', 'firstName', 'gender', 'email', 'studentName', 'studentClass', 'gdprChecked' )
				);
				$appointment->parent->save();
			} else {
				if ( isset( $appointment->parent->id ) ) {
					$appointment->parent->delete();
				}
				$appointment->parent = null;
			}
			$appointment->save();
		},
		'Speichern eines Elternsprechtagstermins mit Elternangaben'
	);
}

function countFilledFieldsInArray($array): int {
	$count=0;
	foreach ( $array as $value ) {
		if ( !empty(trim($value)) ) {
			$count++;
		}
	}
	return $count;
}
function processAppointmentsCsvFile(): array|null {
	$unprocessedLines=array();

	try {
		$isFormSubmitted = isset($_POST['submit_csv']);
		if (!$isFormSubmitted) {
			return null;
		}
			$imports = array();
			$reserved_appointment_ids = array();
			foreach ( flz_wpdb_objects_read_uploaded_csv( 'appointments-csv', 'Importieren der Elternsprechtagstermine aus CSV' ) as $line ) {
					if ( count( $line ) < 4 ) {
						$line['error'] = 'Die CSV-Zeile enthält weniger als vier Spalten.';
						$unprocessedLines[] = $line;
						continue;
					}
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
				if ( in_array( (int) $appointment->id, $reserved_appointment_ids, true ) ) {
					$line['error'] = 'Dieser Slot kommt mehrfach in der CSV-Datei vor.';
					$unprocessedLines[] = $line;
					continue;
				}
				$reserved_appointment_ids[] = (int) $appointment->id;
				$parent = new FlzEstParent(
					array(
						'studentName'=>$line[2],
						'studentClass'=>$line[3],
						'gdprChecked'=>true,
						'name'=>'Vorbelegung Schule',
						'firstName'=>'',
						'email'=>'info@tagore-gymnasium.de'
					)
				);
				$imports[] = array( 'parent' => $parent, 'appointment' => $appointment );
			}

			flz_wpdb_objects\FlzWpdbTransaction::run(
				static function () use ( $imports ): void {
					foreach ( $imports as $import ) {
						$parent = $import['parent'];
						$appointment = $import['appointment'];
						$parent->save();
						$appointment->parent = $parent;
						$appointment->isConfirmed = true;
						$appointment->save();
					}
				},
				'Importieren aller vorab belegten Elternsprechtagstermine'
			);

	} catch (Throwable $error) {
		throw flzest_operation_error( $error, 'Importieren der Elternsprechtagstermine aus CSV' );
	}
	return $unprocessedLines;
}


function createAppointments(): void {
	$slots = getAppointmentsSlots();
	$teachers = FlzEstTeacher::get_all_by();
	foreach ( $teachers as $teacher ) {
		$teacher->createTeachersAppointments($slots);
	}
}



function flzest_appointments_page(): void {
	try {
		flzest_appointments_page_content();
	} catch ( Throwable $error ) {
		flzest_render_admin_error( $error, 'Anzeigen und Verarbeiten der Elternsprechtagstermine' );
	}
}

function flzest_appointments_page_content(): void {
	flzest_assert_admin_request();
	if ( isset( $_POST['newEST'] ) ) {
		resetAppointments();
	}
	if ( isset( $_POST['appointment_empty'] ) ) {
		$empty_data = map_deep( wp_unslash( $_POST['appointment_empty'] ), 'sanitize_text_field' );
		$appointment_id = is_array( $empty_data ) && isset( $empty_data['id'] ) ? absint( $empty_data['id'] ) : 0;
		empty_appointment( $appointment_id );

	}
	$unprocessed=processAppointmentsCsvFile();
	if($unprocessed)
	{
		echo flz_ui()->csv_unprocessed_notice( $unprocessed, array( 'name' => 'flzest_unprocessed_appointments' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente.
	}
	processAppointmentForm();
	// show appointment table
	$appointments = flzEstAppointment::get_all_by();
	$appointment_teacher_filter = flz_ui_admin_filter_text( 'teacher_filter' );
	$appointment_booking_filter = isset( $_GET['booking_filter'] ) ? sanitize_key( wp_unslash( $_GET['booking_filter'] ) ) : 'all';
	if ( ! in_array( $appointment_booking_filter, array( 'all', 'booked', 'free' ), true ) ) {
		$appointment_booking_filter = 'all';
	}
	$appointment_orderby = flz_ui_admin_orderby( array( 'start', 'end', 'teacher', 'parent', 'status' ), 'teacher' );
	$appointment_order   = flz_ui_admin_order();
	$appointment_base_args = array(
		'page' => 'flzest_appointments',
	);
	if ( '' !== $appointment_teacher_filter ) {
		$appointment_base_args['teacher_filter'] = $appointment_teacher_filter;
	}
	if ( 'all' !== $appointment_booking_filter ) {
		$appointment_base_args['booking_filter'] = $appointment_booking_filter;
	}

	$appointments = array_values( array_filter(
		$appointments,
		static function ( $appointment ) use ( $appointment_teacher_filter, $appointment_booking_filter ): bool {
			$teacher_label = $appointment->teacher
				? (string) $appointment->teacher->name
				: '';
			$is_booked = ! empty( $appointment->parent );

			if ( '' !== $appointment_teacher_filter && false === stripos( $teacher_label, $appointment_teacher_filter ) ) {
				return false;
			}

			if ( 'booked' === $appointment_booking_filter && ! $is_booked ) {
				return false;
			}

			if ( 'free' === $appointment_booking_filter && $is_booked ) {
				return false;
			}

			return true;
		}
	) );

	usort(
		$appointments,
		static function ( $left, $right ) use ( $appointment_orderby, $appointment_order ): int {
			$left_teacher = $left->teacher ? (string) $left->teacher->name : '';
			$right_teacher = $right->teacher ? (string) $right->teacher->name : '';
			$left_teacher_first_name = $left->teacher ? (string) $left->teacher->firstName : '';
			$right_teacher_first_name = $right->teacher ? (string) $right->teacher->firstName : '';
			$left_parent = $left->parent ? trim( (string) $left->parent->name . ', ' . (string) $left->parent->firstName ) : '';
			$right_parent = $right->parent ? trim( (string) $right->parent->name . ', ' . (string) $right->parent->firstName ) : '';

			$values = array(
				'start'   => array( $left->start, $right->start ),
				'end'     => array( $left->end, $right->end ),
				'teacher' => array( $left_teacher, $right_teacher ),
				'parent'  => array( $left_parent, $right_parent ),
				'status'  => array( empty( $left->parent ) ? 'frei' : 'belegt', empty( $right->parent ) ? 'frei' : 'belegt' ),
			);

			$result = flz_ui_admin_compare( $values[ $appointment_orderby ][0], $values[ $appointment_orderby ][1], $appointment_order );
			if ( 0 === $result && 'teacher' === $appointment_orderby ) {
				$result = flz_ui_admin_compare( $left_teacher_first_name, $right_teacher_first_name, $appointment_order );
			}
			if ( 0 === $result ) {
				return flz_ui_admin_compare( $left->start, $right->start, 'asc' );
			}

			return $result;
		}
	);

	$csvFile = flz_wpdb_objects_create_csv_file(
		array(
			'Name Lehrer',
			'Vorname Lehrer',
			'Email Lehrer',
			'Beginn',
			'Ende',
			'Name Eltern',
			'Vorname Eltern',
			'Email Eltern',
			'Name Schüler:in',
			'Klasse Schüler:in',
			'bestätigt',
		),
		array_map(
			static fn( FlzEstAppointment $appointment ): array => array(
				$appointment->teacher?->name,
				$appointment->teacher?->firstName,
				$appointment->teacher?->email,
				$appointment->start ? date( 'H:i', $appointment->start ) : '',
				$appointment->end ? date( 'H:i', $appointment->end ) : '',
				$appointment->parent ? $appointment->parent->name : 'kein Eintrag',
				$appointment->parent?->firstName,
				$appointment->parent?->email,
				$appointment->parent?->studentName,
				$appointment->parent?->studentClass,
				$appointment->isConfirmed ? 'ja' : 'nein',
			),
			$appointments
		),
		'appointments.csv'
	);
	include( plugin_dir_path( __FILE__ ) . '../templates/appointments.php' );
}

function empty_appointment( int $appointment_id ) {
	$appointment = FlzEstAppointment::get_by_id( $appointment_id );
	if ( ! $appointment instanceof FlzEstAppointment ) {
		throw new UnexpectedValueException( 'Der zu leerende Termin wurde nicht gefunden.' );
	}
	$appointment->parent = null;
	$appointment->isConfirmed = false;
	$appointment->confirmationToken=null;
	$appointment->confirmationExpiration=0;
	$appointment->save();
	echo "<script>alert('Termin erfolgreich geleert!');</script>";
}

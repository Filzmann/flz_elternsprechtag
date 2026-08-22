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
/**
 * @return array{type:string,message:string}|null
 */
function processAppointmentsCsvFile(): array|null {
	try {
		$is_dry_run = isset( $_POST['submit_csv_dry_run'] );
		$is_import = isset( $_POST['submit_csv'] );
		if ( ! $is_dry_run && ! $is_import ) {
			return null;
		}

		$appointment_rows = flzest_parse_appointment_csv(
			flz_wpdb_objects_read_uploaded_csv( 'appointments-csv', 'Importieren der Elternsprechtagstermine aus CSV', false )
		);
		$plan = flzest_plan_appointment_csv_import( $appointment_rows );
		$proof_hash = flzest_appointment_csv_proof_hash( $appointment_rows );

		if ( $is_dry_run ) {
			set_transient( flzest_appointment_csv_proof_key(), $proof_hash, 15 * MINUTE_IN_SECONDS );

			return array(
				'type' => 'success',
				'message' => sprintf(
					'Dry-Run erfolgreich: vollständiger Snapshot mit %d belegten und %d freien Terminen.',
					$plan['booked'],
					$plan['free']
				),
			);
		}

		$stored_proof = get_transient( flzest_appointment_csv_proof_key() );
		if ( ! is_string( $stored_proof ) || ! hash_equals( $stored_proof, $proof_hash ) ) {
			throw new UnexpectedValueException( 'Diese Datei muss vor dem Import erneut erfolgreich als Dry-Run geprüft werden.' );
		}

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $plan, $proof_hash, $appointment_rows ): void {
				$appointments = array_column( $plan['appointments'], 'appointment' );
				usort(
					$appointments,
					static fn( FlzEstAppointment $left, FlzEstAppointment $right ): int => $left->id <=> $right->id
				);
				foreach ( $appointments as $appointment ) {
					$appointment->lock_for_update();
				}
				if ( ! hash_equals( $proof_hash, flzest_appointment_csv_proof_hash( $appointment_rows ) ) ) {
					throw new UnexpectedValueException( 'Der Terminbestand hat sich seit dem Dry-Run verändert.' );
				}
				flzest_apply_appointment_csv_import_plan( $plan );
			},
			'Wiederherstellen aller Elternsprechtagstermine aus CSV'
		);
		delete_transient( flzest_appointment_csv_proof_key() );

		return array(
			'type' => 'success',
			'message' => sprintf(
				'Import abgeschlossen: %d belegte und %d freie Termine wiederhergestellt.',
				$plan['booked'],
				$plan['free']
			),
		);
	} catch ( Throwable $error ) {
		flzest_log_error( $error, 'Prüfen oder Importieren der Elternsprechtagstermine aus CSV' );

		return array(
			'type' => 'error',
			'message' => $error instanceof UnexpectedValueException
				? $error->getMessage()
				: 'Die Termin-CSV konnte nicht sicher verarbeitet werden.',
		);
	}
}

/**
 * @param array{appointments:array<int,array{appointment:FlzEstAppointment,parent:FlzEstParent|null,confirmed:bool}>,booked:int,free:int} $plan
 */
function flzest_apply_appointment_csv_import_plan( array $plan ): void {
	$old_parents = array();
	foreach ( $plan['appointments'] as $item ) {
		$appointment = $item['appointment'];
		$old_parent = $appointment->parent;
		if ( $old_parent instanceof FlzEstParent && ! empty( $old_parent->id ) ) {
			$old_parents[ (int) $old_parent->id ] = $old_parent;
		}
		if ( $item['parent'] instanceof FlzEstParent ) {
			$item['parent']->save();
		}
		$appointment->parent = $item['parent'];
		$appointment->isConfirmed = $item['confirmed'];
		$appointment->confirmationToken = null;
		$appointment->confirmationExpiration = 0;
		$appointment->save();
	}
	foreach ( $old_parents as $old_parent ) {
		if ( null === FlzEstAppointment::get_by_fields( array( 'parent_id' => (int) $old_parent->id ) ) ) {
			$old_parent->delete();
		}
	}
}

/**
 * @param array<int,array<string,mixed>> $appointment_rows
 * @return array{appointments:array<int,array{appointment:FlzEstAppointment,parent:FlzEstParent|null,confirmed:bool}>,booked:int,free:int}
 */
function flzest_plan_appointment_csv_import( array $appointment_rows ): array {
	$existing = array();
	foreach ( FlzEstAppointment::get_all_by() as $appointment ) {
		if ( ! $appointment->teacher instanceof FlzEstTeacher ) {
			throw new UnexpectedValueException( 'Ein vorhandener Termin besitzt keine gültige Lehrkraftreferenz.' );
		}
		$key = strtolower( (string) $appointment->teacher->email ) . '|' . (string) $appointment->start;
		if ( isset( $existing[ $key ] ) ) {
			throw new UnexpectedValueException( 'Die vorhandenen Termine enthalten einen doppelten Lehrkraft-/Beginn-Schlüssel.' );
		}
		$existing[ $key ] = $appointment;
	}

	$items = array();
	$booked = 0;
	$free = 0;
	foreach ( $appointment_rows as $row ) {
		$key = $row['teacher_email'] . '|' . (string) $row['start'];
		if ( ! isset( $existing[ $key ] ) ) {
			throw new UnexpectedValueException( 'Die CSV referenziert einen unbekannten Termin oder eine unbekannte Lehrkraft.' );
		}
		$appointment = $existing[ $key ];
		if ( (int) $appointment->end !== $row['end'] ) {
			throw new UnexpectedValueException( 'Die CSV-Endzeit stimmt nicht mit dem vorhandenen Termin überein.' );
		}
		unset( $existing[ $key ] );

		$parent = $row['booked'] ? new FlzEstParent( $row['parent'] ) : null;
		$items[] = array(
			'appointment' => $appointment,
			'parent' => $parent,
			'confirmed' => $row['confirmed'],
		);
		$row['booked'] ? ++$booked : ++$free;
	}

	if ( ! empty( $existing ) ) {
		throw new UnexpectedValueException( 'Die CSV ist kein vollständiger Snapshot: Mindestens ein vorhandener Termin fehlt.' );
	}

	return array(
		'appointments' => $items,
		'booked' => $booked,
		'free' => $free,
	);
}

/**
 * @param array<int,array<string,mixed>> $appointment_rows
 */
function flzest_appointment_csv_proof_hash( array $appointment_rows ): string {
	$current_state = array_map(
		static fn( FlzEstAppointment $appointment ): string => implode(
			'|',
			array(
				(string) $appointment->id,
				(string) $appointment->teacher?->id,
				(string) $appointment->start,
				(string) $appointment->end,
				(string) $appointment->parent?->id,
				(string) $appointment->parent?->gender,
				(string) $appointment->parent?->name,
				(string) $appointment->parent?->firstName,
				(string) $appointment->parent?->email,
				(string) $appointment->parent?->studentName,
				(string) $appointment->parent?->studentClass,
				(string) $appointment->parent?->gdprChecked,
				$appointment->isConfirmed ? '1' : '0',
				(string) $appointment->confirmationToken,
				(string) $appointment->confirmationExpiration,
			)
		),
		FlzEstAppointment::get_all_by()
	);
	sort( $current_state );

	return hash( 'sha256', serialize( array( $appointment_rows, $current_state ) ) );
}

function flzest_appointment_csv_proof_key(): string {
	return 'flzest_appointment_csv_proof_' . get_current_user_id();
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
	$appointment_csv_notice = processAppointmentsCsvFile();
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

	$appointment_csv_export_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=flzest_export_appointments_csv' ),
		'flzest_export_appointments_csv'
	);
	include( plugin_dir_path( __FILE__ ) . '../templates/appointments.php' );
}

/**
 * Sendet alle Buchungen und Termine als geschützten CSV-Direktdownload.
 */
function flzest_export_appointments_csv(): void
{
	flzest_assert_csv_export_request( 'flzest_export_appointments_csv' );

	try {
		$appointments = flzEstAppointment::get_all_by();
		flz_wpdb_objects_send_csv_download(
			flzest_appointment_csv_header(),
			array_map(
				static fn( FlzEstAppointment $appointment ): array => array(
					FLZEST_CSV_VERSION,
					'appointment',
					$appointment->teacher?->email,
					$appointment->start,
					$appointment->end,
					$appointment->parent?->gender,
					$appointment->parent?->name,
					$appointment->parent?->firstName,
					$appointment->parent?->email,
					$appointment->parent?->studentName,
					$appointment->parent?->studentClass,
					$appointment->parent ? 'yes' : '',
					$appointment->isConfirmed ? '1' : '0',
				),
				$appointments
			),
			'appointments.csv'
		);
	} catch ( Throwable $error ) {
		flzest_log_error( $error, 'Exportieren der Buchungen und Termine als CSV' );
		wp_die( esc_html__( 'Die Buchungs-CSV konnte nicht erstellt werden.', 'flz-elternsprechtag' ) );
	}
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

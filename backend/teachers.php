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
	$teacher_csv_notice = processTeacherCsvFile();
	$teacher_csv_stage_ready = FlzCsvImportStage::exists( 'flzest:teachers', get_current_user_id() );
	handleTeacherDeletion();

	$teachers = FlzEstTeacher::get_all_by( order_by: 'name' );
	$teacher_search = flz_ui_admin_filter_text( 'table_search' );
	$teacher_orderby = flz_ui_admin_orderby( array( 'gender', 'name', 'firstName', 'email' ), 'name' );
	$teacher_order = flz_ui_admin_order();
	$teacher_base_args = array( 'page' => 'flzest_teachers' );
	if ( '' !== $teacher_search ) {
		$teacher_base_args['table_search'] = $teacher_search;
	}

	if ( '' !== $teacher_search ) {
		$teachers = array_values( array_filter(
			$teachers,
			static function ( FlzEstTeacher $teacher ) use ( $teacher_search ): bool {
				return flzest_admin_table_matches_search(
					$teacher_search,
					array(
						$teacher->get_gender_as_anrede(),
						(string) $teacher->name,
						(string) $teacher->firstName,
						(string) $teacher->email,
					)
				);
			}
		) );
	}

	usort(
		$teachers,
		static function ( FlzEstTeacher $left, FlzEstTeacher $right ) use ( $teacher_orderby, $teacher_order ): int {
			$values = array(
				'gender'    => array( $left->get_gender_as_anrede(), $right->get_gender_as_anrede() ),
				'name'      => array( (string) $left->name, (string) $right->name ),
				'firstName' => array( (string) $left->firstName, (string) $right->firstName ),
				'email'     => array( (string) $left->email, (string) $right->email ),
			);

			$result = flz_ui_admin_compare( $values[ $teacher_orderby ][0], $values[ $teacher_orderby ][1], $teacher_order );
			if ( 0 === $result ) {
				return flz_ui_admin_compare( (string) $left->name, (string) $right->name, 'asc' );
			}

			return $result;
		}
	);
	$teacher_csv_export_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=flzest_export_teachers_csv' ),
		'flzest_export_teachers_csv'
	);

	include( plugin_dir_path( __FILE__ ) . '../templates/teachers.php' );
}

/**
 * Sendet alle Lehrkräfte als geschützten CSV-Direktdownload.
 */
function flzest_export_teachers_csv(): void
{
	flzest_assert_csv_export_request( 'flzest_export_teachers_csv' );

	try {
		$teachers = FlzEstTeacher::get_all_by( order_by: 'name' );
		flz_wpdb_objects_send_csv_download(
			flzest_teacher_csv_header(),
			array_map(
				static fn( FlzEstTeacher $teacher ): array => array(
					(string) ( $teacher->record_uuid ?? '' ),
					$teacher->gender,
					$teacher->name,
					$teacher->firstName,
					$teacher->email,
				),
				$teachers
			),
			'teachers.csv'
		);
	} catch ( Throwable $error ) {
		flzest_log_error( $error, 'Exportieren der Lehrkräfte als CSV' );
		wp_die( esc_html__( 'Die Lehrkräfte-CSV konnte nicht erstellt werden.', 'flz-elternsprechtag' ) );
	}
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

	$registration_note = isset( $_POST['registration_note'] ) ? wp_kses_post( wp_unslash( $_POST['registration_note'] ) ) : null;
	$teacher_data = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
	$teacher_id   = isset( $teacher_data['id'] ) && '' !== $teacher_data['id'] ? absint( $teacher_data['id'] ) : null;
	$teacher      = $teacher_id ? FlzEstTeacher::get_by_id( $teacher_id ) : new FlzEstTeacher( [] );
	if ( ! $teacher instanceof FlzEstTeacher ) {
		throw new UnexpectedValueException( 'Die zu speichernde Lehrkraft wurde nicht gefunden.' );
	}
	$teacher->assignPostData( $teacher_data, [ 'name', 'firstName', 'gender', 'email' ] );
	if ( null !== $registration_note ) {
		$teacher->registration_note = $registration_note;
	}
	flz_wpdb_objects\FlzWpdbTransaction::run(
		static fn() => $teacher->save(),
		'Speichern einer Lehrkraft mit ihren Elternsprechtagsterminen'
	);
}

/**
 * @return array{type:string,message:string}|null
 */
function processTeacherCsvFile(): array|null
{
	try {
		$is_submission = isset( $_POST['submit_csv'] );
		$is_confirmation = isset( $_POST['confirm_teacher_csv_import'] );
		if ( ! $is_submission && ! $is_confirmation ) {
			return null;
		}
		if ( $is_submission ) {
			$teacher_rows = flzest_parse_teacher_csv( flz_wpdb_objects_read_uploaded_csv( 'teacher-csv', 'Importieren der Lehrkräfte-CSV-Datei', false ) );
			$plan = flzest_plan_teacher_csv_import( $teacher_rows );
			$proof_hash = flzest_teacher_csv_proof_hash( $teacher_rows );
			FlzCsvImportStage::store( 'flzest:teachers', get_current_user_id(), $teacher_rows, $proof_hash, 15 * MINUTE_IN_SECONDS );

			return array(
				'type' => 'success',
				'message' => sprintf(
					'Dry-Run erfolgreich: %d Lehrkräfte werden aktualisiert, %d neu angelegt, %d Lehrkräfte bleiben unverändert. %d nicht in der CSV enthaltene Lehrkräfte sind markiert; davon können %d nach ausdrücklicher Bestätigung gelöscht werden, %d bleiben wegen künftiger oder weniger als 30 Tage zurückliegender Termine geschützt.',
					$plan['updated'],
					$plan['created'],
					$plan['unchanged'],
					$plan['preserved'],
					count( $plan['removable_missing'] ),
					count( $plan['protected_missing'] )
				),
				'deletable_missing' => count( $plan['removable_missing'] ),
				'protected_missing' => count( $plan['protected_missing'] ),
				'removable_missing_teacher_ids' => array_map(
					static fn( FlzEstTeacher $teacher ): int => (int) $teacher->id,
					$plan['removable_missing']
				),
				'protected_missing_teacher_ids' => array_map(
					static fn( FlzEstTeacher $teacher ): int => (int) $teacher->id,
					$plan['protected_missing']
				),
				'protected_missing_teachers' => array_map(
					static fn( FlzEstTeacher $teacher ): array => array(
						'id'    => (int) $teacher->id,
						'label' => trim( (string) $teacher->firstName . ' ' . (string) $teacher->name ) . ' (' . (string) $teacher->email . ')',
					),
					$plan['protected_missing']
				),
			);
		}

		$stage = FlzCsvImportStage::consume( 'flzest:teachers', get_current_user_id() );
		if ( ! is_array( $stage ) ) { throw new UnexpectedValueException( 'Der geprüfte CSV-Stand ist abgelaufen. Bitte laden Sie die Datei erneut hoch und führen Sie den Dry-Run aus.' ); }
		$teacher_rows = $stage['rows']; $proof_hash = $stage['proof']; $plan = flzest_plan_teacher_csv_import( $teacher_rows );
		$delete_missing_teachers = isset( $_POST['delete_missing_teachers'] );
		$delete_protected_missing_teacher_ids = flzest_requested_protected_teacher_csv_deletions( $plan );

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $plan, $proof_hash, $teacher_rows, $delete_missing_teachers, $delete_protected_missing_teacher_ids ): void {
				if ( ! hash_equals( $proof_hash, flzest_teacher_csv_proof_hash( $teacher_rows ) ) ) {
					throw new UnexpectedValueException( 'Der Lehrkräftebestand hat sich seit dem Dry-Run verändert.' );
				}
				flzest_apply_teacher_csv_import_plan( $plan, $delete_missing_teachers, $delete_protected_missing_teacher_ids );
			},
			'Aktualisieren und Ergänzen der Lehrkräfte durch einen CSV-Import'
		);
		FlzCsvImportStage::discard( 'flzest:teachers', get_current_user_id() );

		return array(
			'type' => 'success',
			'message' => sprintf(
				'Import abgeschlossen: %d Lehrkräfte aktualisiert, %d neu angelegt%s.',
				$plan['updated'],
				$plan['created'],
				( $delete_missing_teachers ? sprintf( ', %d nicht mehr enthaltene Lehrkräfte mit ausschließlich mehr als 30 Tage vergangenen Terminen gelöscht', count( $plan['removable_missing'] ) ) : '' )
				. ( ! empty( $delete_protected_missing_teacher_ids ) ? sprintf( ', %d ausgewählte geschützte Lehrkräfte einschließlich aller ihrer Termine endgültig gelöscht', count( $delete_protected_missing_teacher_ids ) ) : '' )
			),
		);
	} catch (Throwable $error) {
		flzest_log_error( $error, 'Prüfen oder Importieren der Lehrkräfte-CSV-Datei' );

		return array(
			'type' => 'error',
			'message' => flzest_csv_import_error_message( $error, 'Die Lehrkräfte-CSV' ),
		);
	}
}

/**
 * @param array{teachers:array<int,FlzEstTeacher>,created:int,updated:int,unchanged:int,preserved:int,removable_missing:array<int,FlzEstTeacher>,protected_missing:array<int,FlzEstTeacher>} $plan
 */
function flzest_apply_teacher_csv_import_plan( array $plan, bool $delete_missing_teachers = false, array $delete_protected_missing_teacher_ids = array() ): void {
	foreach ( $plan['teachers'] as $teacher ) {
		$teacher->save();
	}
	if ( $delete_missing_teachers ) {
		flzest_delete_teacher_csv_candidates( $plan['removable_missing'] );
	}
	if ( ! empty( $delete_protected_missing_teacher_ids ) ) {
		$protected_to_delete = array_filter(
			$plan['protected_missing'],
			static fn( FlzEstTeacher $teacher ): bool => in_array( (int) $teacher->id, $delete_protected_missing_teacher_ids, true )
		);
		flzest_delete_teacher_csv_candidates( $protected_to_delete );
	}
}

/**
 * Beschränkt die Auswahl auf geschützte Kandidaten des aktuellen, serverseitig
 * erneut berechneten CSV-Plans. So kann kein manipuliertes Formular andere
 * Lehrkräfte löschen.
 *
 * @param array{protected_missing:array<int,FlzEstTeacher>} $plan
 * @return array<int,int>
 */
function flzest_requested_protected_teacher_csv_deletions( array $plan ): array {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Jede eingereichte ID wird unmittelbar darunter als positive Ganzzahl und gegen den serverseitigen Plan validiert.
	$requested = isset( $_POST['delete_protected_missing_teachers'] ) ? wp_unslash( $_POST['delete_protected_missing_teachers'] ) : array();
	if ( ! is_array( $requested ) ) {
		throw new UnexpectedValueException( 'Die Auswahl geschützter Lehrkräfte ist ungültig.' );
	}

	$requested_ids = array();
	foreach ( $requested as $teacher_id ) {
		if ( ! is_scalar( $teacher_id ) || ! ctype_digit( (string) $teacher_id ) || 0 === (int) $teacher_id ) {
			throw new UnexpectedValueException( 'Die Auswahl geschützter Lehrkräfte ist ungültig.' );
		}
		$requested_ids[] = (int) $teacher_id;
	}
	$requested_ids = array_values( array_unique( $requested_ids ) );
	$allowed_ids = array_map( static fn( FlzEstTeacher $teacher ): int => (int) $teacher->id, $plan['protected_missing'] );
	if ( array_diff( $requested_ids, $allowed_ids ) ) {
		throw new UnexpectedValueException( 'Die Auswahl enthält keine geschützte Lehrkraft aus dem geprüften CSV-Stand.' );
	}

	return $requested_ids;
}

/** @param array<int,FlzEstTeacher> $teachers */
function flzest_delete_teacher_csv_candidates( array $teachers ): void {
	foreach ( $teachers as $teacher ) {
		foreach ( FlzEstAppointment::get_all_by( array( 'teacher_id' => (int) $teacher->id ) ) as $appointment ) {
			if ( $appointment instanceof FlzEstAppointment ) {
				$appointment->delete();
			}
		}
		$teacher->delete();
	}
}

/**
 * @param array<int,array{gender:string,name:string,firstName:string,email:string}> $teacher_rows
 * @return array{teachers:array<int,FlzEstTeacher>,created:int,updated:int,unchanged:int,preserved:int,removable_missing:array<int,FlzEstTeacher>,protected_missing:array<int,FlzEstTeacher>}
 */
function flzest_plan_teacher_csv_import( array $teacher_rows ): array {
	$existing_by_email = array();
	$existing_by_uuid = array();
	foreach ( FlzEstTeacher::get_all_by() as $teacher ) {
		if ( ! empty( $teacher->record_uuid ) ) { $existing_by_uuid[$teacher->record_uuid] = $teacher; }
		$email = strtolower( (string) $teacher->email );
		if ( isset( $existing_by_email[ $email ] ) ) {
			throw new UnexpectedValueException(
				sprintf( 'Die vorhandenen Lehrkräftedaten enthalten die E-Mail-Adresse "%s" mehrfach.', $email )
			);
		}
		$existing_by_email[ $email ] = $teacher;
	}

	$teachers = array();
	$created = 0;
	$updated = 0;
	$unchanged = 0;
	foreach ( $teacher_rows as $teacher_data ) {
		$email = $teacher_data['email'];
		$uuid = $teacher_data['record_uuid'] ?? '';
		$teacher = '' !== $uuid ? ( $existing_by_uuid[$uuid] ?? null ) : ( $existing_by_email[$email] ?? null );
		if ( '' !== $uuid && isset( $existing_by_email[$email] ) && $existing_by_email[$email] !== $teacher ) {
			throw new UnexpectedValueException( 'Die Datensatz-ID widerspricht einer vorhandenen Lehrkraft mit derselben E-Mail-Adresse.' );
		}
		if ( $teacher instanceof FlzEstTeacher ) {
			unset( $existing_by_email[ strtolower( (string) $teacher->email ) ] );
			unset( $existing_by_uuid[ (string) $teacher->record_uuid ] );
			if ( flzest_teacher_csv_row_is_unchanged( $teacher, $teacher_data ) ) {
				++$unchanged;
				continue;
			}
			$teacher->assignPostData( $teacher_data, array( 'name', 'firstName', 'gender', 'email' ) );
			++$updated;
		} else {
			$teacher = new FlzEstTeacher( $teacher_data );
			++$created;
		}
		$teachers[] = $teacher;
	}

	$missing = array_values( $existing_by_email );
	$missing_deletions = flzest_plan_missing_teacher_deletions( $missing );

	return array(
		'teachers' => $teachers,
		'created' => $created,
		'updated' => $updated,
		'unchanged' => $unchanged,
		'preserved' => count( $missing ),
		'removable_missing' => $missing_deletions['removable'],
		'protected_missing' => $missing_deletions['protected'],
	);
}

/** @param array{gender:string,name:string,firstName:string,email:string} $teacher_data */
function flzest_teacher_csv_row_is_unchanged( FlzEstTeacher $teacher, array $teacher_data ): bool {
	return (string) $teacher->gender === (string) $teacher_data['gender']
		&& (string) $teacher->name === (string) $teacher_data['name']
		&& (string) $teacher->firstName === (string) $teacher_data['firstName']
		&& strtolower( (string) $teacher->email ) === strtolower( (string) $teacher_data['email'] );
}

/**
 * Fehlende Lehrkräfte dürfen nur entfernt werden, wenn sämtliche Termine
 * mindestens 30 Tage vergangen sind. Künftige und aktuelle Buchungen bleiben
 * damit unabhängig vom CSV-Abgleich geschützt.
 *
 * @param array<int,FlzEstTeacher> $teachers
 * @return array{removable:array<int,FlzEstTeacher>,protected:array<int,FlzEstTeacher>}
 */
function flzest_plan_missing_teacher_deletions( array $teachers ): array {
	$cutoff = current_time( 'timestamp' ) - ( 30 * DAY_IN_SECONDS );
	$removable = array();
	$protected = array();
	foreach ( $teachers as $teacher ) {
		$can_delete = true;
		foreach ( FlzEstAppointment::get_all_by( array( 'teacher_id' => (int) $teacher->id ) ) as $appointment ) {
			if ( $appointment instanceof FlzEstAppointment && (int) $appointment->end >= $cutoff ) {
				$can_delete = false;
				break;
			}
		}
		if ( $can_delete ) {
			$removable[] = $teacher;
		} else {
			$protected[] = $teacher;
		}
	}
	return array( 'removable' => $removable, 'protected' => $protected );
}

/**
 * Bindet die Prüfung an Dateiinhalt, Benutzer und aktuellen Lehrkräftebestand.
 *
 * @param array<int,array{gender:string,name:string,firstName:string,email:string}> $teacher_rows
 */
function flzest_teacher_csv_proof_hash( array $teacher_rows ): string {
	$current_ids = array_map(
		static fn( FlzEstTeacher $teacher ): array => array(
			'id' => (int) $teacher->id,
			'record_uuid' => (string) $teacher->record_uuid,
			'gender' => (string) $teacher->gender,
			'name' => (string) $teacher->name,
			'firstName' => (string) $teacher->firstName,
			'email' => strtolower( (string) $teacher->email ),
		),
		FlzEstTeacher::get_all_by()
	);
	usort( $current_ids, static fn( array $left, array $right ): int => $left['id'] <=> $right['id'] );
	$current_appointments = array_map(
		static fn( FlzEstAppointment $appointment ): array => array(
			'id'                      => (int) $appointment->id,
			'teacher_id'              => (int) $appointment->getTeacher()?->id,
			'parent_id'               => (int) $appointment->getParent()?->id,
			'start'                   => (int) $appointment->start,
			'end'                     => (int) $appointment->end,
			'is_confirmed'            => (bool) $appointment->isConfirmed,
			'confirmation_expiration' => (int) $appointment->confirmationExpiration,
		),
		FlzEstAppointment::get_all_by()
	);
	usort( $current_appointments, static fn( array $left, array $right ): int => $left['id'] <=> $right['id'] );

	return hash( 'sha256', serialize( array( $teacher_rows, $current_ids, $current_appointments ) ) );
}

function flzest_teacher_csv_proof_key(): string {
	return 'flzest_teacher_csv_proof_' . get_current_user_id();
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

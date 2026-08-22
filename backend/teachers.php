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
	handleTeacherDeletion();

	$teachers = FlzEstTeacher::get_all_by( order_by: 'name' );
	$teacher_search = flz_ui_admin_filter_text( 'teacher_search' );
	$teacher_orderby = flz_ui_admin_orderby( array( 'gender', 'name', 'firstName', 'email' ), 'name' );
	$teacher_order = flz_ui_admin_order();
	$teacher_base_args = array( 'page' => 'flzest_teachers' );
	if ( '' !== $teacher_search ) {
		$teacher_base_args['teacher_search'] = $teacher_search;
	}

	if ( '' !== $teacher_search ) {
		$teachers = array_values( array_filter(
			$teachers,
			static function ( FlzEstTeacher $teacher ) use ( $teacher_search ): bool {
				return false !== stripos( (string) $teacher->name, $teacher_search );
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
					FLZEST_CSV_VERSION,
					'teacher',
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

/**
 * @return array{type:string,message:string}|null
 */
function processTeacherCsvFile(): array|null
{
	try {
		$is_dry_run = isset( $_POST['submit_csv_dry_run'] );
		$is_import = isset( $_POST['submit_csv'] );
		if ( ! $is_dry_run && ! $is_import ) {
			return null;
		}

		$teacher_rows = flzest_parse_teacher_csv(
			flz_wpdb_objects_read_uploaded_csv( 'teacher-csv', 'Importieren der Lehrkräfte-CSV-Datei', false )
		);
		$plan = flzest_plan_teacher_csv_import( $teacher_rows );
		$proof_hash = flzest_teacher_csv_proof_hash( $teacher_rows );

		if ( $is_dry_run ) {
			set_transient( flzest_teacher_csv_proof_key(), $proof_hash, 15 * MINUTE_IN_SECONDS );

			return array(
				'type' => 'success',
				'message' => sprintf(
					'Dry-Run erfolgreich: %d Lehrkräfte werden aktualisiert, %d neu angelegt, %d bestehende bleiben zusätzlich erhalten.',
					$plan['updated'],
					$plan['created'],
					$plan['preserved']
				),
			);
		}

		$stored_proof = get_transient( flzest_teacher_csv_proof_key() );
		if ( ! is_string( $stored_proof ) || ! hash_equals( $stored_proof, $proof_hash ) ) {
			throw new UnexpectedValueException( 'Diese Datei muss vor dem Import erneut erfolgreich als Dry-Run geprüft werden.' );
		}

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $plan, $proof_hash, $teacher_rows ): void {
				if ( ! hash_equals( $proof_hash, flzest_teacher_csv_proof_hash( $teacher_rows ) ) ) {
					throw new UnexpectedValueException( 'Der Lehrkräftebestand hat sich seit dem Dry-Run verändert.' );
				}
				flzest_apply_teacher_csv_import_plan( $plan );
			},
			'Aktualisieren und Ergänzen der Lehrkräfte durch einen CSV-Import'
		);
		delete_transient( flzest_teacher_csv_proof_key() );

		return array(
			'type' => 'success',
			'message' => sprintf(
				'Import abgeschlossen: %d Lehrkräfte aktualisiert, %d neu angelegt.',
				$plan['updated'],
				$plan['created']
			),
		);
	} catch (Throwable $error) {
		flzest_log_error( $error, 'Prüfen oder Importieren der Lehrkräfte-CSV-Datei' );

		return array(
			'type' => 'error',
			'message' => $error instanceof UnexpectedValueException
				? $error->getMessage()
				: 'Die Lehrkräfte-CSV konnte nicht sicher verarbeitet werden.',
		);
	}
}

/**
 * @param array{teachers:array<int,FlzEstTeacher>,created:int,updated:int,preserved:int} $plan
 */
function flzest_apply_teacher_csv_import_plan( array $plan ): void {
	foreach ( $plan['teachers'] as $teacher ) {
		$teacher->save();
	}
}

/**
 * @param array<int,array{gender:string,name:string,firstName:string,email:string}> $teacher_rows
 * @return array{teachers:array<int,FlzEstTeacher>,created:int,updated:int,preserved:int}
 */
function flzest_plan_teacher_csv_import( array $teacher_rows ): array {
	$existing_by_email = array();
	foreach ( FlzEstTeacher::get_all_by() as $teacher ) {
		$email = strtolower( (string) $teacher->email );
		if ( isset( $existing_by_email[ $email ] ) ) {
			throw new UnexpectedValueException( 'Die vorhandenen Lehrkräftedaten enthalten eine doppelte E-Mail-Adresse.' );
		}
		$existing_by_email[ $email ] = $teacher;
	}

	$teachers = array();
	$created = 0;
	$updated = 0;
	foreach ( $teacher_rows as $teacher_data ) {
		$email = $teacher_data['email'];
		if ( isset( $existing_by_email[ $email ] ) ) {
			$teacher = $existing_by_email[ $email ];
			$teacher->assignPostData( $teacher_data, array( 'name', 'firstName', 'gender', 'email' ) );
			unset( $existing_by_email[ $email ] );
			++$updated;
		} else {
			$teacher = new FlzEstTeacher( $teacher_data );
			++$created;
		}
		$teachers[] = $teacher;
	}

	return array(
		'teachers' => $teachers,
		'created' => $created,
		'updated' => $updated,
		'preserved' => count( $existing_by_email ),
	);
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
			'gender' => (string) $teacher->gender,
			'name' => (string) $teacher->name,
			'firstName' => (string) $teacher->firstName,
			'email' => strtolower( (string) $teacher->email ),
		),
		FlzEstTeacher::get_all_by()
	);
	usort( $current_ids, static fn( array $left, array $right ): int => $left['id'] <=> $right['id'] );

	return hash( 'sha256', serialize( array( $teacher_rows, $current_ids ) ) );
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

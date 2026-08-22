<?php

declare(strict_types=1);

namespace flz_wpdb_objects {
	class FlzWpdbObject {
		public int|null $id;
		public int $save_calls = 0;
		public int $delete_calls = 0;

		public function __construct( int|null $id = null ) {
			$this->id = $id;
		}

		protected static function table_name(): string {
			return 'wp_flz_est_appointments';
		}

		public function save(): int {
			++$this->save_calls;
			$this->id ??= 900 + $this->save_calls;

			return 1;
		}

		public function delete(): int {
			++$this->delete_calls;

			return 1;
		}

		public static function get_by_fields( array $fields ): null|object {
			return null;
		}
	}

	class FlzWpdbObjectsException extends \RuntimeException {
		public static function invalid_model_state( string $model, string $details ): self {
			return new self( $model . ': ' . $details );
		}

		public static function database( string $operation, string $table, string $details = '' ): self {
			return new self( $operation . ': ' . $table . ': ' . $details );
		}
	}
}

namespace {
	// Exception-Texte sind ausschließlich lokale CLI-Testdiagnostik.
	// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

	class FlzPerson extends flz_wpdb_objects\FlzWpdbObject {
		public function __construct( array $data = array() ) {
			parent::__construct( $data['id'] ?? null );
		}
	}

	class FlzEstSetting {
		public static function get_value_by_name( string $name ): string {
			return '';
		}
	}

	final class FlzEstBookingFakeWpdb {
		public int|false $result = 1;
		public string $last_error = '';
		public array $data = array();
		public array $where = array();
		public int|null $locked_id = 1;
		public string $prepared_query = '';

		public function update( string $table, array $data, array $where ): int|false {
			if ( 'wp_flz_est_appointments' !== $table ) {
				throw new RuntimeException( 'Unerwartete Tabelle: ' . $table );
			}
			$this->data = $data;
			$this->where = $where;

			return $this->result;
		}

		public function prepare( string $query, int $id ): string {
			$this->prepared_query = str_replace( '%d', (string) $id, $query );

			return $this->prepared_query;
		}

		public function get_var( string $query ): int|null {
			$this->prepared_query = $query;

			return $this->locked_id;
		}
	}

	require dirname( __DIR__ ) . '/classes/FlzEstAppointment.php';

	$teacher = new FlzEstTeacher( array( 'id' => 2 ) );
	$parent = new FlzEstParent( array( 'id' => 3 ) );
	$appointment = new FlzEstAppointment(
		array(
			'id' => 1,
			'start' => 100,
			'end' => 200,
			'teacher' => $teacher,
			'parent' => $parent,
			'confirmationToken' => 'synthetic-token',
			'confirmationExpiration' => 300,
		)
	);

	$wpdb = new FlzEstBookingFakeWpdb();
	$GLOBALS['wpdb'] = $wpdb;
	$appointment->lock_for_update();
	if ( ! str_contains( $wpdb->prepared_query, 'FOR UPDATE' ) ) {
		throw new RuntimeException( 'Der Terminimport sperrt den Datensatz nicht für konkurrierende Schreibvorgänge.' );
	}
	$appointment->claim_for_parent_if_available();
	if ( array( 'id' => 1, 'parent_id' => null ) !== $wpdb->where ) {
		throw new RuntimeException( 'Die Reservierung ist nicht auf einen weiterhin freien Termin begrenzt.' );
	}
	if ( 3 !== $wpdb->data['parent_id'] ) {
		throw new RuntimeException( 'Die Reservierung speichert nicht den vorgesehenen Elterndatensatz.' );
	}

	$wpdb->result = 0;
	try {
		$appointment->claim_for_parent_if_available();
		throw new RuntimeException( 'Eine konkurrierende Buchung wurde nicht abgewiesen.' );
	} catch ( FlzEstAppointmentUnavailableException $error ) {
		// Erwarteter Deny-Fall.
	}

	$frontend_source = file_get_contents( dirname( __DIR__ ) . '/frontend/frontend.php' );
	if ( false === $frontend_source ) {
		throw new RuntimeException( 'Der Buchungscontroller konnte nicht gelesen werden.' );
	}
	$transaction_position = strpos( $frontend_source, 'FlzWpdbTransaction::run' );
	$parent_save_position = strpos( $frontend_source, '$selected->parent->save()', $transaction_position ?: 0 );
	$claim_position = strpos( $frontend_source, '$selected->claim_for_parent_if_available()', $parent_save_position ?: 0 );
	if (
		false === $transaction_position
		|| false === $parent_save_position
		|| false === $claim_position
		|| $transaction_position > $parent_save_position
		|| $parent_save_position > $claim_position
	) {
		throw new RuntimeException( 'Elternspeicherung und atomarer Claim liegen nicht in derselben Transaktion.' );
	}

	require dirname( __DIR__ ) . '/backend/appointments.php';
	$old_parent = new FlzEstParent( array( 'id' => 10 ) );
	$new_parent = new FlzEstParent();
	$imported_appointment = new FlzEstAppointment(
		array(
			'id' => 11,
			'start' => 400,
			'end' => 500,
			'teacher' => $teacher,
			'parent' => $old_parent,
			'isConfirmed' => false,
			'confirmationToken' => 'must-not-survive',
			'confirmationExpiration' => 999,
		)
	);
	flzest_apply_appointment_csv_import_plan(
		array(
			'appointments' => array(
				array(
					'appointment' => $imported_appointment,
					'parent' => $new_parent,
					'confirmed' => true,
				),
			),
			'booked' => 1,
			'free' => 0,
		)
	);
	if ( 1 !== $new_parent->save_calls || 1 !== $imported_appointment->save_calls ) {
		throw new RuntimeException( 'Der Terminimport speichert Elternteil und Termin nicht vollständig.' );
	}
	if ( 1 !== $old_parent->delete_calls ) {
		throw new RuntimeException( 'Der Terminimport räumt den nicht mehr referenzierten Altdatensatz nicht auf.' );
	}
	if ( null !== $imported_appointment->confirmationToken || 0 !== $imported_appointment->confirmationExpiration ) {
		throw new RuntimeException( 'Der Terminimport übernimmt oder erhält ein Bestätigungstoken.' );
	}

	echo "OK: flz_elternsprechtag booking concurrency smoke test\n";
}

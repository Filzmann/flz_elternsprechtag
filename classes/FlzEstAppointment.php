<?php
// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

use flz_wpdb_objects\FlzWpdbObject;
use flz_wpdb_objects\FlzWpdbObjectsException;

require_once( "FlzEstTeacher.php" );
require_once( "FlzEstEstParent.php" );

class FlzEstAppointmentUnavailableException extends RuntimeException
{
}

class flzEstAppointment extends FlzWpdbObject
{
	public int|null $start;
	public int|null $end;
	public FlzEstTeacher|null $teacher;
	public FlzEstParent|null $parent = null;
	public bool $isConfirmed=false;
	public int|null $confirmationExpiration = 0;
	public string|null $confirmationToken=null   ;

	public function __construct(array $data=[])
	{
		parent::__construct(id:$data['id']??null);
		$this->start = $data['start']??null;
		$this->end = $data['end']??null;
		$this->teacher = $data['teacher'] ?? null;
		$this->parent = $data['parent'] ?? null;
		$this->isConfirmed=$data['isConfirmed'] ?? false;
		$this->confirmationExpiration=$data['confirmationExpiration'] ?? 0;
		$this->confirmationToken=$data['confirmationToken'] ?? null;
	}

	public function getTeacher(): ?FlzEstTeacher {
		return $this->teacher;
	}

	public function setTeacher(FlzEstTeacher $teacher): void {
		$this->teacher=$teacher;
	}

	public function setParent(FlzEstParent|null $parent): void {
		$this->parent=$parent;
	}

	public function getParent(): ?FlzEstParent {
		return $this->parent;
	}

	protected static function get_table_schema(): string {
		return "(
            id INT(11) NOT NULL AUTO_INCREMENT,
            start INT(11) NOT NULL,
            end INT(11) NOT NULL,
            isConfirmed BOOL NOT NULL,
            confirmationExpiration INT(11) NOT NULL,
            confirmationToken VARCHAR(255) NULL,
            teacher_id INT(11) NOT NULL,
            parent_id INT(11) NULL,
            PRIMARY KEY (id)
        )";
	}

	/**
	 * Retrieves an appointment object by teacher and start time.
	 *
	 * @param FlzEstTeacher $teacher The teacher object.
	 * @param string $start the start time as string in format "H:i".
	 *
	 * @return object|null The appointment object if found, null otherwise.
	 */
	public static function get_by_teacher_and_start( object|null $teacher,  string $start): object|null {
		if(!$teacher) return null;

		$date=FlzEstSetting::get_value_by_name("NextParentsDay");
		$timestamp  = strtotime( $date." ".$start);
		if ( $timestamp === false ) {
			throw FlzWpdbObjectsException::invalid_model_state(
				static::class,
				'Datum und Startzeit konnten nicht in einen Zeitstempel umgewandelt werden.'
			);
		}

		return static::get_by_fields(
			[
				'start'      => (int) $timestamp,
				'teacher_id' => (int) $teacher->id,
			]
		);
	}

	/**
	 * Prepares the data for saving.
	 *
	 * This method returns an array with the data that needs to be stored or persisted.
	 *
	 * @return array The prepared data for saving.
	 */
	protected function prepareDataForSaving(): array {
		if ( $this->teacher === null || $this->teacher->id === null || $this->start === null || $this->end === null ) {
			throw FlzWpdbObjectsException::invalid_model_state(
				static::class,
				'Lehrkraft, Beginn und Ende müssen vor dem Speichern gesetzt sein.'
			);
		}
		return [
			'start' => $this->start,
			'end' => $this->end,
			'teacher_id' => $this->teacher->id,
			'parent_id' => $this->parent?->id,
			'isConfirmed' => $this->isConfirmed,
			'confirmationExpiration' => $this->confirmationExpiration,
			'confirmationToken' => $this->confirmationToken

		];
	}

	/**
	 * Belegt den Termin atomar, sofern er in der Datenbank weiterhin frei ist.
	 */
	public function claim_for_parent_if_available(): void {
		global $wpdb;

		if ( empty( $this->id ) || empty( $this->parent?->id ) ) {
			throw FlzWpdbObjectsException::invalid_model_state(
				static::class,
				'Termin und Elterndatensatz müssen vor der Reservierung gespeichert sein.'
			);
		}

		$table = static::table_name();
		try {
			$result = $wpdb->update(
				$table,
				$this->prepareDataForSaving(),
				array(
					'id'        => $this->id,
					'parent_id' => null,
				)
			);
		} catch ( Throwable $error ) {
			throw FlzWpdbObjectsException::operation(
				'Atomares Reservieren eines Elternsprechtagstermins',
				$table,
				$error
			);
		}

		if ( false === $result ) {
			throw FlzWpdbObjectsException::database(
				'Atomares Reservieren eines Elternsprechtagstermins',
				$table,
				(string) $wpdb->last_error
			);
		}
		if ( 0 === $result ) {
			throw new FlzEstAppointmentUnavailableException( 'Der Termin ist nicht mehr frei.' );
		}
		if ( 1 !== $result ) {
			throw FlzWpdbObjectsException::invalid_model_state(
				static::class,
				'Die Reservierung hat unerwartet mehr als einen Termin verändert.'
			);
		}
	}

	/**
	 * Sperrt diesen Termin bis zum Ende der laufenden Datenbanktransaktion.
	 */
	public function lock_for_update(): void {
		global $wpdb;

		if ( empty( $this->id ) ) {
			throw FlzWpdbObjectsException::invalid_model_state(
				static::class,
				'Ein ungespeicherter Termin kann nicht gesperrt werden.'
			);
		}

		$table = static::table_name();
		try {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellenname stammt ausschließlich aus dem intern validierten Modellvertrag.
			$query = $wpdb->prepare( "SELECT id FROM $table WHERE id = %d FOR UPDATE", $this->id );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query wurde unmittelbar zuvor mit fester interner Tabelle vorbereitet.
			$locked_id = $wpdb->get_var( $query );
		} catch ( Throwable $error ) {
			throw FlzWpdbObjectsException::operation(
				'Sperren eines Elternsprechtagstermins für den CSV-Import',
				$table,
				$error
			);
		}

		if ( (int) $locked_id !== $this->id ) {
			throw FlzWpdbObjectsException::invalid_model_state(
				static::class,
				'Der zu importierende Termin existiert nicht mehr.'
			);
		}
	}

	public static function get_by_id_for_update(int $id): ?self {
		$sql = 'SELECT * FROM ' . static::table_name() . ' WHERE id = %d FOR UPDATE';
		$models = static::query_models($sql, array($id), 'Sperren eines Elternsprechtagstermins');

		return $models[0] ?? null;
	}

	public function errors(): array {
		$errors=[];
		if(empty($this->start)) $errors[] = 'NO_START';
		if(empty($this->end)) $errors[] = 'NO_END';
		if(empty($this->teacher)) $errors[]='NO_TEACHER';
		else $errors = array_merge($errors, $this->getTeacher()->errors());
		if(!$this->parent or empty($this->parent)) $errors[]='NO_PARENT';
		else $errors = array_merge($errors, $this->getParent()->errors());
		return $errors;
	}

	public function activate($token): string
	{
		$token_valid = is_string( $token )
			&& $this->confirmationToken !== null
			&& hash_equals( $this->confirmationToken, $token );
		if ( $token_valid && $this->confirmationExpiration !== null && $this->confirmationExpiration >= time() )
		{
			$this->isConfirmed=true;
			$this->confirmationToken = null;
			$this->confirmationExpiration = 0;
			$this->save();
			return flz_ui()->notice( 'Der Termin wurde bestätigt. Sie können das Fenster jetzt schließen!', 'success' );
		}
		else return flz_ui()->notice( 'Fehler bei der Bestätigung. Bitte Link nochmal testen oder Termin erneut registrieren.', 'error' );
	}

}

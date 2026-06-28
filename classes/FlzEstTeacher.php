<?php

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

class FlzEstTeacher extends FlzPerson
{
	public function __construct(array $data = []) {
		parent::__construct($data);
	}

	protected static function get_table_schema(): string {
		return "(
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            firstName VARCHAR(255) NOT NULL,
            gender CHAR NOT NULL,
            email VARCHAR(255) NOT NULL,
            PRIMARY KEY (id))";
	}

	public function errors(): array {
		return [];
	}

	protected function prepareDataForSaving(): array {
		return [
			'name' => sanitize_text_field($this->name),
			'firstName' => sanitize_text_field($this->firstName),
			'gender' => sanitize_text_field($this->gender),
			'email' => sanitize_email($this->email),
		];
	}
	public function createTeachersAppointments($slots=array()): void {
		$slots=empty($slots)?
			(
				function_exists( "getAppointmentsSlots" ) ?
					getAppointmentsSlots()
					: $slots
			):$slots;
		foreach ( $slots as $slot ) {
			$data = [
				'start' => $slot['start'],
				'end' => $slot['end'],
				'teacher' => $this,
				'parent' => null
			];
			$appointment = new flzEstAppointment( $data );
			$appointment->save();
		}
	}
	protected static function afterInsert(): void {
		$teachersId = static::last_insert_id();
		$teacher=FlzEstTeacher::get_by_id($teachersId);
		if ( ! $teacher instanceof FlzEstTeacher ) {
			throw flz_wpdb_objects\FlzWpdbObjectsException::invalid_model_state(
				static::class,
				'Die neu eingefügte Lehrkraft mit ID ' . $teachersId . ' konnte nicht erneut geladen werden.'
			);
		}
		$teacher->createTeachersAppointments();
	}
}

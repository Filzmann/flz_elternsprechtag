<?php

class FlzEstParent extends FlzPerson
{
	public String|null $studentName;
	public String|null $studentClass;
	public String|null $gdprChecked;
	public function __construct(array $data = []) {
		parent::__construct($data);
		$this->studentName      = (isset($data['studentName']) && $data['studentName']!='') ?$data['studentName']: null;
		$this->studentClass     = (isset($data['studentClass']) && $data['studentClass']!='') ?$data['studentClass']: null;
		$this->gdprChecked      = (isset($data['gdprChecked']) && $data['gdprChecked']!='') ?$data['gdprChecked']: null;
	}
	protected static function get_table_schema(): string {
		return "(
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            firstName VARCHAR(255) NOT NULL,
            gender CHAR NOT NULL,
            email VARCHAR(255) NOT NULL,
            studentName VARCHAR(255) NOT NULL,
            studentClass VARCHAR(255) NOT NULL,
            gdprChecked VARCHAR(3) NOT NULL,
            PRIMARY KEY (id)
        )";
	}

	public function errors(): array {
		$errors=[];

		if (!$this->valid_text($this->name, 255)) {
			$errors[] = 'NO_NAME';
		}
		if (!$this->valid_text($this->firstName, 255)) {
			$errors[] = 'NO_FIRST_NAME';
		}
		if (!in_array($this->gender, array(null, '', 'd', 'f', 'm'), true)) {
			$errors[] = 'NO_GENDER';
		}
		if (!in_array($this->gdprChecked, array('on', 'yes'), true)) {
			$errors[] = 'NO_GDPR';
		}
		if (!$this->valid_text($this->studentClass, 32) || !preg_match('/^[\p{L}\p{N} ._-]+$/u', (string) $this->studentClass)) {
			$errors[] = 'NO_STUDENTS_CLASS';
		}
		if (!$this->valid_text($this->studentName, 255)) {
			$errors[] = 'NO_STUDENTS_NAME';
		}
		if (!$this->valid_text($this->email, 255) || !is_email((string) $this->email)) {
			$errors[] = 'NO_EMAIL';
		}
		return $errors;
	}

	private function valid_text(?string $value, int $maximum): bool {
		$value = trim((string) $value);
		return '' !== $value && strlen($value) <= $maximum;
	}

	protected function prepareDataForSaving(): array {
		return [
			'name' => sanitize_text_field($this->name),
			'firstName' => sanitize_text_field($this->firstName),
			'gender' => sanitize_text_field($this->gender),
			'email' => sanitize_email($this->email),
			'studentName' => sanitize_text_field($this->studentName),
			'studentClass' => sanitize_text_field($this->studentClass),
			'gdprChecked' => sanitize_text_field($this->gdprChecked)
		];
	}



}

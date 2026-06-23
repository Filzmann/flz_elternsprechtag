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

		if(!$this->name or $this->name=='') $errors[]='NO_NAME';
		if( !$this->gdprChecked or $this->gdprChecked == "no") $errors[] ='NO_GDPR';#
		if( !$this->studentClass or $this->studentClass == "") $errors[] ='NO_STUDENTS_CLASS';
		if( !$this->studentName or $this->studentName == "") $errors[] ='NO_STUDENTS_NAME';
		if(!$this->email or $this->email=="") $errors[]='NO_EMAIL';
		return $errors;
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
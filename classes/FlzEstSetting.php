<?php

use flz_wpdb_objects\FlzWpdbObject;

class FlzEstSetting extends FlzWpdbObject {

	public string $name;
	public string $value;

	public function __construct(array $data ) {
		parent::__construct($data['id']??null);
		$this->name  = $data['name'];
		$this->value = $data['value'];
	}

	protected static function get_table_schema(): string {
		return "(id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            value VARCHAR(255) NOT NULL,
            PRIMARY KEY (id))";
	}

	protected static function afterCreate(): void {
		//insert defaults
		$setting=new FlzEstSetting(array('name'=>"SlotLength", 'value'=>"20"));
		$setting->save();
		$setting=new FlzEstSetting(array('name'=>"NextParentsDay", 'value'=>"17.11.2023"));
		$setting->save();
		$setting=new FlzEstSetting(array('name'=>"ParentsDayBegin", 'value'=>"16:00"));
		$setting->save();
		$setting=new FlzEstSetting(array('name'=>"ParentsDayEnd", 'value'=>"20:00"));
		$setting->save();
		$setting=new FlzEstSetting(array('name'=>"TestMode", 'value'=>"1"));
		$setting->save();
	}
	public static function get_value_by_name( string $name ): string {
		$setting = static::get_by_fields( [ 'name' => sanitize_text_field( $name ) ] );

		return $setting === null ? '' : (string) $setting->value;
	}

	protected function prepareDataForSaving(): array {
		return [
			'name' => $this->name,
			'value' => $this->value,
		];
	}
}

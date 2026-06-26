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
		$defaults = array(
			'SlotLength' => '20',
			'NextParentsDay' => '17.11.23',
			'ParentsDayBegin' => '16:00',
			'ParentsDayEnd' => '20:00',
			'TestMode' => '1',
		);
		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ( $defaults ): void {
				foreach ( $defaults as $name => $value ) {
					if ( static::get_by_fields( array( 'name' => $name ) ) === null ) {
						( new FlzEstSetting( array( 'name' => $name, 'value' => $value ) ) )->save();
					}
				}
			},
			'Anlegen fehlender Elternsprechtags-Standardeinstellungen'
		);
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

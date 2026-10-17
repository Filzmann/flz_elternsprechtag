<?php

// Isolierter Vertragstest: Das HTML-Datumsfeld sendet ISO-Daten, der Bestand
// verwendet weiterhin das etablierte Anzeigeformat TT.MM.JJ.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI-Testfehler.

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

require_once __DIR__ . '/../backend/settings.php';

function flzest_settings_date_assert( bool $condition ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Settings date contract failed.' );
	}
}

flzest_settings_date_assert(
	'2026-11-17' === flzest_date_input_value( '17.11.26' ),
);
flzest_settings_date_assert(
	'17.11.26' === flzest_validate_setting_value( 'NextParentsDay', '2026-11-17' ),
);
flzest_settings_date_assert(
	'17.11.26' === flzest_validate_setting_value( 'NextParentsDay', '17.11.2026' ),
);

$rejected = false;
try {
	flzest_validate_setting_value( 'NextParentsDay', '2026-99-17' );
} catch ( UnexpectedValueException $exception ) {
	$rejected = true;
}
flzest_settings_date_assert( $rejected );

$template = file_get_contents( __DIR__ . '/../templates/settings.php' );
flzest_settings_date_assert(
	false !== strpos( $template, 'flzest_setting_input_args' )
		&& false !== strpos( $template, '$flzest_ui->field( $flzest_setting_args )' ),
);
flzest_settings_date_assert( false !== strpos( $template, 'flz-ui-card-grid flzest-settings-grid' ) );

$field_contracts = array(
	'NextParentsDay'  => array( 'type' => 'date', 'value' => '2026-11-17' ),
	'SlotLength'      => array( 'type' => 'number', 'min' => 5, 'max' => 120 ),
	'ParentsDayBegin' => array( 'type' => 'time' ),
	'ParentsDayEnd'   => array( 'type' => 'time' ),
	'TestMode'        => array( 'type' => 'checkbox', 'checkbox_value' => '1', 'checked' => true ),
	'MailFrom'        => array( 'type' => 'email', 'autocomplete' => 'email' ),
);
foreach ( $field_contracts as $name => $expected ) {
	$field = flzest_setting_input_args( $name, 'TestMode' === $name ? '1' : '17.11.26' );
	foreach ( $expected as $key => $value ) {
		flzest_settings_date_assert( isset( $field[ $key ] ) && $field[ $key ] === $value );
	}
}
flzest_settings_date_assert( 'text' === flzest_setting_input_args( 'UnknownSetting', '' )['type'] );
flzest_settings_date_assert( false !== strpos( $template, '->hidden( \'settings[\' . $setting->id . \']\', \'0\'' ) );

foreach ( array( 'teachers.php', 'appointments.php' ) as $csv_template_name ) {
	$csv_template = file_get_contents( __DIR__ . '/../templates/' . $csv_template_name );
	$csv_grid_position  = strpos( $csv_template, 'flz-ui-card-grid flzest-csv-grid' );
	$table_position     = 'appointments.php' === $csv_template_name ? strpos( $csv_template, 'id="appointmentTable"' ) : strpos( $csv_template, '<table class="widefat striped flz-ui-admin-table">' );
	flzest_settings_date_assert( false !== $csv_grid_position && false !== $table_position && $table_position < $csv_grid_position );
}

echo "OK: settings date input and storage contract\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI-Testausgabe.

<?php

declare( strict_types=1 );

// Quellvertrag: Der Token-Link bestätigt separat und gibt niemals anschließend
// wieder das Buchungsformular aus.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$root = dirname( __DIR__ );
$frontend = file_get_contents( $root . '/frontend/frontend.php' );
$settings = file_get_contents( $root . '/classes/FlzEstSetting.php' );
$template = file_get_contents( $root . '/templates/frontend-confirmation.php' );

if ( false === $frontend || false === $settings || false === $template ) {
	throw new RuntimeException( 'Aktivierungsquellen oder -template fehlen.' );
}

foreach (
	array(
		'flzest_render_activation_confirmation',
		'activate_if_valid',
		'flzest_mail_multipart_alternative_body',
		'ActivationConfirmationMessage',
		'ActivationRedirectTarget',
	) as $needle
) {
	if ( ! str_contains( $frontend . $settings . $template, $needle ) ) {
		throw new RuntimeException( 'Aktivierungsvertrag fehlt: ' . $needle );
	}
}

if ( ! str_contains( $frontend, '$activation_success = $appointment->activate_if_valid( $token );' ) ) {
	throw new RuntimeException( 'Die Aktivierungsseite muss das Ergebnis der Aktivierung auswerten.' );
}

$confirmation_position = strpos( $frontend, 'flzest_render_activation_confirmation' );
$form_position = strpos( $frontend, "include plugin_dir_path( __FILE__ ) . '../templates/frontend-form.php'" );
if ( false === $confirmation_position || false === $form_position || $confirmation_position > $form_position ) {
	throw new RuntimeException( 'Der Aktivierungslink darf nicht weiter zum Buchungsformular durchfallen.' );
}

echo "OK: activation confirmation and multipart mail contract\n";

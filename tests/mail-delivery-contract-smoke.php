<?php

declare( strict_types=1 );

// Quellvertrag: Fluent SMTP/Brevo erhält HTML- und Textteil getrennt, nie
// einen selbst serialisierten MIME-Block im HTML-Feld.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$frontend = file_get_contents( dirname( __DIR__ ) . '/frontend/frontend.php' );

if ( false === $frontend ) {
	throw new RuntimeException( 'Der Frontend-Versandpfad fehlt.' );
}

foreach (
	array(
		'function flzest_send_html_mail_with_plaintext_fallback',
		"add_action( 'phpmailer_init'",
		"remove_action( 'phpmailer_init'",
		'flzest_mail_plaintext_fallback( $html )',
		'$phpmailer->ContentType = \'multipart/alternative\';',
		"'Content-Type: text/html; charset=UTF-8'",
	) as $needle
) {
	if ( ! str_contains( $frontend, $needle ) ) {
		throw new RuntimeException( 'Der Fluent-SMTP-Mailvertrag fehlt: ' . $needle );
	}
}

if ( str_contains( $frontend, 'flzest_mail_multipart_alternative_body(' ) ) {
	throw new RuntimeException( 'Der Versand darf keinen selbst serialisierten MIME-Block an Fluent SMTP übergeben.' );
}

echo "OK: Fluent SMTP multipart delivery contract\n";

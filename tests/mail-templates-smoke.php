<?php

declare( strict_types=1 );

// Exception-Texte sind ausschließlich lokale CLI-Testdiagnostik.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

require dirname( __DIR__ ) . '/includes/mail-templates.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$defaults = flzest_mail_template_defaults();
$assert( isset( $defaults['MailParentBody'], $defaults['MailTeacherSubject'] ), 'Die Standard-Mailvorlagen fehlen.' );
$assert( ! flzest_mail_template_has_unknown_placeholder( $defaults['MailParentBody'] ), 'Gültige Platzhalter wurden abgewiesen.' );
$assert( str_contains( $defaults['MailParentBody'], '{confirmation_link}' ), 'Der Standard enthält keinen klickbaren Bestätigungslink.' );
$assert( str_contains( $defaults['MailParentBody'], '{confirmation_button}' ), 'Der Standard enthält keinen Bestätigungsbutton.' );
$assert( str_contains( $defaults['MailParentBody'], '{confirmation_url_copy}' ), 'Der Standard enthält kein kopierbares Bestätigungslink-Feld.' );
$assert( flzest_mail_template_has_unknown_placeholder( 'Hallo {unbekannt}' ), 'Unbekannte Platzhalter wurden akzeptiert.' );

$confirmation_parts = flzest_confirmation_mail_placeholder_context( 'https://example.test/confirm?token=abc' );
$assert( str_contains( $confirmation_parts['confirmation_link'], '<a href="https://example.test/confirm?token=abc"' ), 'Der Link-Platzhalter enthält keine klickbare Zieladresse.' );
$assert( str_contains( $confirmation_parts['confirmation_button'], '>Termin jetzt bestätigen</a>' ), 'Der Button-Platzhalter enthält keine verständliche Aktion.' );
$assert( str_contains( $confirmation_parts['confirmation_url_copy'], 'https://example.test/confirm?token=abc' ), 'Das Kopierfeld enthält die Bestätigungsadresse nicht.' );

$rendered = flzest_render_mail_template(
	'{parent_name} / {teacher_name} / {confirmation_url} / {confirmation_link} / {confirmation_button} / {confirmation_url_copy}',
	array(
		'parent_name' => 'Beispiel',
		'teacher_name' => 'Lehrkraft',
		'confirmation_url' => $confirmation_parts['confirmation_url'],
		'confirmation_link' => $confirmation_parts['confirmation_link'],
		'confirmation_button' => $confirmation_parts['confirmation_button'],
		'confirmation_url_copy' => $confirmation_parts['confirmation_url_copy'],
	)
);
$assert( str_contains( $rendered, 'Beispiel / Lehrkraft / https://example.test/confirm?token=abc' ), 'Mailplatzhalter wurden nicht vollständig ersetzt.' );
$assert( str_contains( $rendered, 'Termin jetzt bestätigen' ), 'Die neuen Bestätigungsplatzhalter wurden nicht gerendert.' );
$assert( flzest_is_mail_template_setting( 'MailParentBody' ), 'Eine bekannte Mail-Einstellung wurde nicht erkannt.' );
$assert( ! flzest_is_mail_template_setting( 'UnknownSetting' ), 'Eine unbekannte Einstellung wurde als Mailvorlage erkannt.' );
$assert( str_contains( flzest_booking_confirmation_notice_default(), 'Bestätigungslink' ), 'Der Bestätigungshinweis fehlt.' );

$multipart = flzest_mail_multipart_alternative_body( '<p>Bestätigung: https://example.test/confirm</p>', 'flzest-test-boundary' );
$assert( str_contains( $multipart, 'Content-Type: text/plain; charset=UTF-8' ), 'Der Plaintext-Fallback fehlt.' );
$assert( str_contains( $multipart, 'Content-Type: text/html; charset=UTF-8' ), 'Der HTML-Mailteil fehlt.' );
$assert( str_contains( $multipart, 'Bestätigung: https://example.test/confirm' ), 'Der Plaintext-Fallback verliert den Mailinhalt.' );

echo "OK: flz_elternsprechtag mail templates smoke test\n";

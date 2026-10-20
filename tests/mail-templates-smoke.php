<?php

declare( strict_types=1 );

// Exception-Texte sind ausschließlich lokale CLI-Testdiagnostik.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/mail-templates.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$defaults = flzest_mail_template_defaults();
$assert( isset( $defaults['MailParentBody'], $defaults['MailTeacherSubject'] ), 'Die Standard-Mailvorlagen fehlen.' );
$assert( ! flzest_mail_template_has_unknown_placeholder( $defaults['MailParentBody'] ), 'Gültige Platzhalter wurden abgewiesen.' );
$assert( flzest_mail_template_has_unknown_placeholder( 'Hallo {unbekannt}' ), 'Unbekannte Platzhalter wurden akzeptiert.' );

$rendered = flzest_render_mail_template(
	'{parent_name} / {teacher_name} / {confirmation_url}',
	array(
		'parent_name' => 'Beispiel',
		'teacher_name' => 'Lehrkraft',
	)
);
$assert( 'Beispiel / Lehrkraft / ' === $rendered, 'Mailplatzhalter wurden nicht vollständig oder sicher ersetzt.' );
$assert( flzest_is_mail_template_setting( 'MailParentBody' ), 'Eine bekannte Mail-Einstellung wurde nicht erkannt.' );
$assert( ! flzest_is_mail_template_setting( 'UnknownSetting' ), 'Eine unbekannte Einstellung wurde als Mailvorlage erkannt.' );
$assert( str_contains( flzest_booking_confirmation_notice_default(), 'Bestätigungslink' ), 'Der Bestätigungshinweis fehlt.' );

echo "OK: flz_elternsprechtag mail templates smoke test\n";

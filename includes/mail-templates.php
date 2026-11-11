<?php

defined( 'ABSPATH' ) || exit;

/**
 * @return array<string,string>
 */
function flzest_mail_template_defaults(): array
{
	return array(
		'MailTeacherSubject' => 'Neue Buchung am {appointment_date}',
		'MailTeacherBody' => "Hallo {teacher_salutation} {teacher_name},\n\nes gibt eine neue Buchung für den Elternsprechtag am {appointment_date}.\nBeginn: {start_time}\nEnde: {end_time}\n\nElternteil: {parent_salutation} {parent_name}\nSchüler*in: {student_name}\nKlasse: {student_class}",
		'MailParentSubject' => 'Bitte Termin am {appointment_date} bestätigen',
		'MailParentBody' => "Hallo {parent_salutation} {parent_name},\n\nSie haben am {appointment_date} einen Termin bei {teacher_salutation} {teacher_name} für {student_name} gebucht.\nBeginn: {start_time}\nEnde: {end_time}\n\nBitte bestätigen Sie den Termin. Falls eine Darstellung nicht funktioniert, nutzen Sie eine der weiteren Möglichkeiten:\n\n{confirmation_link}\n\n{confirmation_button}\n\n{confirmation_url_copy}\n\nBeste Grüße\n{site_name}",
		'MailUnconfirmedBookingWarningSubject' => 'Bitte Termin am {appointment_date} bestätigen',
		'MailUnconfirmedBookingWarningBody' => "Hallo {parent_salutation} {parent_name},\n\nIhr Termin bei {teacher_salutation} {teacher_name} am {appointment_date} um {start_time} wurde noch nicht bestätigt. Bitte verwenden Sie den Bestätigungslink aus Ihrer Buchungsmail, damit der Termin erhalten bleibt.\n\nBeste Grüße\n{site_name}",
		'MailUnconfirmedBookingReleasedSubject' => 'Ihr unbestätigter Termin wurde freigegeben',
		'MailUnconfirmedBookingReleasedBody' => "Hallo {parent_salutation} {parent_name},\n\nIhr unbestätigter Termin bei {teacher_salutation} {teacher_name} am {appointment_date} um {start_time} wurde freigegeben. Wenn Sie weiterhin einen Termin benötigen, buchen Sie bitte einen freien Zeitpunkt erneut.\n\nBeste Grüße\n{site_name}",
	);
}

/**
 * @return array<int,string>
 */
function flzest_mail_template_placeholders(): array
{
	return array(
		'teacher_salutation',
		'teacher_name',
		'parent_salutation',
		'parent_name',
		'student_name',
		'student_class',
		'appointment_date',
		'start_time',
		'end_time',
		'confirmation_url',
		'confirmation_link',
		'confirmation_button',
		'confirmation_url_copy',
		'site_name',
	);
}

/**
 * Builds redundant HTML representations of the same confirmation URL.
 *
 * @return array<string,string>
 */
function flzest_confirmation_mail_placeholder_context( string $confirmation_url ): array
{
	$safe_url = esc_url( $confirmation_url );

	return array(
		'confirmation_url'      => $safe_url,
		'confirmation_link'     => '<a href="' . $safe_url . '">Bestätigungslink öffnen</a>',
		'confirmation_button'   => '<a href="' . $safe_url . '" style="display:inline-block;padding:12px 18px;background:#135e96;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:bold;">Termin jetzt bestätigen</a>',
		'confirmation_url_copy' => '<p><strong>Falls Sie nicht klicken können: Link kopieren und in die Browserzeile einfügen</strong><br><span style="display:inline-block;padding:10px;background:#f1f1f1;border:1px solid #cccccc;word-break:break-all;font-family:monospace;">' . $safe_url . '</span><br><small>Smartphone/Tablet: Link gedrückt halten und „Kopieren“ wählen. Desktop: Link markieren, mit Strg+C (Mac: Cmd+C) kopieren und in die Browserzeile einfügen.</small></p>',
	);
}

function flzest_mail_template_has_unknown_placeholder( string $template ): bool
{
	preg_match_all( '/\{([a-z_]+)\}/', $template, $matches );
	foreach ( $matches[1] ?? array() as $placeholder ) {
		if ( ! in_array( $placeholder, flzest_mail_template_placeholders(), true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * @param array<string,string> $context
 */
function flzest_render_mail_template( string $template, array $context ): string
{
	$replacements = array();
	foreach ( flzest_mail_template_placeholders() as $placeholder ) {
		$replacements[ '{' . $placeholder . '}' ] = $context[ $placeholder ] ?? '';
	}

	return strtr( $template, $replacements );
}

function flzest_is_mail_template_setting( string $name ): bool
{
	return array_key_exists( $name, flzest_mail_template_defaults() );
}

function flzest_booking_confirmation_notice_default(): string
{
	return 'Nach der Buchung erhalten Sie eine E-Mail mit einem Bestätigungslink. Erst nach dem Anklicken dieses Links ist Ihr Termin verbindlich bestätigt.';
}

function flzest_activation_confirmation_message_default(): string
{
	return 'Ihr Termin wurde erfolgreich bestätigt.';
}

function flzest_mail_plaintext_fallback( string $html ): string
{
	$text = preg_replace( '/<\s*br\s*\/?>/i', "\n", $html );
	$text = preg_replace( '/<\/p\s*>/i', "\n\n", (string) $text );
	$text = html_entity_decode( strip_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( "/\r\n?|[ \t]+\n/", "\n", $text );

	return trim( (string) $text );
}

function flzest_mail_multipart_alternative_body( string $html, string $boundary ): string
{
	$plaintext = flzest_mail_plaintext_fallback( $html );

	return '--' . $boundary . "\r\n"
		. "Content-Type: text/plain; charset=UTF-8\r\n"
		. "Content-Transfer-Encoding: 8bit\r\n\r\n"
		. $plaintext . "\r\n\r\n"
		. '--' . $boundary . "\r\n"
		. "Content-Type: text/html; charset=UTF-8\r\n"
		. "Content-Transfer-Encoding: 8bit\r\n\r\n"
		. $html . "\r\n\r\n"
		. '--' . $boundary . "--\r\n";
}

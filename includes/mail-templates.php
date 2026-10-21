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
		'MailParentBody' => "Hallo {parent_salutation} {parent_name},\n\nSie haben am {appointment_date} einen Termin bei {teacher_salutation} {teacher_name} für {student_name} gebucht.\nBeginn: {start_time}\nEnde: {end_time}\n\nBitte bestätigen Sie den Termin über diesen Link:\n{confirmation_url}\n\nBeste Grüße\n{site_name}",
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
		'site_name',
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

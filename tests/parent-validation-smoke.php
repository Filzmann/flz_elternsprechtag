<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

function sanitize_email($value): string
{
	return (string) filter_var((string) $value, FILTER_SANITIZE_EMAIL);
}

function sanitize_text_field($value): string
{
	return trim(strip_tags((string) $value));
}

function is_email($value): bool
{
	return false !== filter_var((string) $value, FILTER_VALIDATE_EMAIL);
}

require_once dirname(__DIR__, 2) . '/flz_wpdb_objects/FlzWpdbObject.php';
require_once dirname(__DIR__, 2) . '/flz_wpdb_objects/FlzPerson.php';
require_once dirname(__DIR__) . '/classes/FlzEstEstParent.php';

$valid = new FlzEstParent(array(
	'name' => 'Beispiel',
	'firstName' => 'Mia',
	'gender' => 'f',
	'email' => 'mia@example.test',
	'studentName' => 'Kind Beispiel',
	'studentClass' => '7.1',
	'gdprChecked' => 'on',
));
if (array() !== $valid->errors()) {
	throw new RuntimeException('Gültige Eltern-/Kinddaten wurden abgelehnt.');
}

$invalid_email = clone $valid;
$invalid_email->email = 'keine-mail';
if (!in_array('NO_EMAIL', $invalid_email->errors(), true)) {
	throw new RuntimeException('Eine ungültige Eltern-E-Mail wurde akzeptiert.');
}

$invalid_class = clone $valid;
$invalid_class->studentClass = str_repeat('x', 40);
if (!in_array('NO_STUDENTS_CLASS', $invalid_class->errors(), true)) {
	throw new RuntimeException('Eine fachlich ungültige Klassenangabe wurde akzeptiert.');
}

$invalid_consent = clone $valid;
$invalid_consent->gdprChecked = 'beliebig';
if (!in_array('NO_GDPR', $invalid_consent->errors(), true)) {
	throw new RuntimeException('Ein manipulierter Einwilligungswert wurde akzeptiert.');
}

echo "OK: flz_elternsprechtag parent validation smoke test\n";


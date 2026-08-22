<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
$parent = $selected->parent ?? null;
$gender_options = array(
	''  => 'keine Angabe/divers',
	'f' => 'Frau',
	'm' => 'Herr',
);
?>
<p>
	Für Ihren Termin am <?php echo esc_html(flz_ui_format_date($selected->start)); ?>
	um <?php echo esc_html(date('H:i', $selected->start)); ?>
	bei <?php echo esc_html($selected->teacher->get_gender_as_anrede() . ' ' . $selected->teacher->name); ?>
	benötigen wir noch folgende Daten von Ihnen:
</p>

<div class="form-wrap">
	<?php
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui renderer escaped das Select inklusive Attribute und Optionen.
	echo $ui->field(array(
		'type'    => 'select',
		'name'    => 'parent[gender]',
		'id'      => 'parent_gender',
		'label'   => 'Anrede',
		'value'   => (string) ($parent->gender ?? ''),
		'options' => $gender_options,
	));
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>

	<?php if (in_array('NO_NAME', $errors, true)) : ?>
		<?php echo $ui->notice('Bitte geben Sie Ihren Namen ein', 'error'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
	<?php echo $ui->input('text', array('name' => 'parent[name]', 'id' => 'parent_name', 'label' => 'Name', 'value' => (string) ($parent->name ?? ''))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>

	<?php if (in_array('NO_FIRST_NAME', $errors, true)) : ?>
		<?php echo $ui->notice('Bitte geben Sie Ihren Vornamen ein', 'error'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
	<?php echo $ui->input('text', array('name' => 'parent[firstName]', 'id' => 'parent_firstName', 'label' => 'Vorname', 'value' => (string) ($parent->firstName ?? ''))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>

	<?php if (in_array('NO_EMAIL', $errors, true)) : ?>
		<?php echo $ui->notice('Bitte geben Sie Ihre E-Mail ein', 'error'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
	<?php echo $ui->input('email', array('name' => 'parent[email]', 'id' => 'parent_email', 'label' => 'Email', 'value' => (string) ($parent->email ?? ''))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>

	<?php if (in_array('NO_STUDENTS_NAME', $errors, true)) : ?>
		<?php echo $ui->notice('Bitte geben Sie den Namen des Kindes ein', 'error'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
	<?php echo $ui->input('text', array('name' => 'parent[studentName]', 'id' => 'parent_studentName', 'label' => 'Name Schüler*in', 'value' => (string) ($parent->studentName ?? ''))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>

	<?php if (in_array('NO_STUDENTS_CLASS', $errors, true)) : ?>
		<?php echo $ui->notice('Bitte geben Sie die Klasse ihres Kindes ein', 'error'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
	<?php echo $ui->input('text', array('name' => 'parent[studentClass]', 'id' => 'parent_studentClass', 'label' => 'Klasse Schüler*in', 'value' => (string) ($parent->studentClass ?? ''))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>

	<?php if (in_array('NO_GDPR', $errors, true)) : ?>
		<?php echo $ui->notice('Bitte bestätigen Sie die Datenschutzerklärung.', 'error'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
	<?php
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui renderer escaped die Checkbox inklusive Label und Attribute.
	echo $ui->field(array(
		'type'    => 'checkbox',
		'name'    => 'parent[gdprChecked]',
		'id'      => 'parent_gdprChecked',
		'label'   => 'Ich habe die Datenschutzerklärung (einschließlich der angegebenen Löschfristen) zur Kenntnis genommen.',
		'checked' => $parent && $parent->gdprChecked === 'on',
	));
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>

	<p>
		Ich stimme zu, dass meine Angaben und Daten zur Beantwortung meiner Anfrage elektronisch gespeichert werden.
		Die Einwilligung kann jederzeit per E-Mail an technik@tagore-gymnasium.de widerrufen werden.
		Von einer Übersendung sensibler Daten (zum Beispiel Gesundheitsdaten) bitten wir abzusehen.
	</p>
</div>

<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui renderer escaped den Button inklusive Label, Icon-Text und Attribute.
echo $ui->button(array(
	'label'    => 'Termin verbindlich buchen',
	'type'     => 'submit',
	'variant'  => 'primary',
	'icon'     => 'check',
	'icon_alt' => 'Termin verbindlich buchen',
	'attrs'    => array(
		'name' => 'save',
		'id'   => 'save',
	),
));
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
?>

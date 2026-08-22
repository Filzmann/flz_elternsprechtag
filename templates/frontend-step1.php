<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
$selected_id = $selected->id ? (int) $selected->id : 0;
?>
<?php echo $ui->form_start(array('method' => 'post', 'nonce' => 'flzest_book_appointment', 'nonce_name' => 'flzest_nonce')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
	<?php
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui renderer escaped das Select inklusive Attribute und Optionen.
	echo $ui->field(array(
		'type'        => 'select',
		'name'        => 'teacher',
		'id'          => 'teacher',
		'label'       => 'Lehrkraft',
		'value'       => (string) ($selected->teacher ? $selected->teacher->id : 0),
		'placeholder' => '--- bitte auswählen ---',
		'options'     => flzest_teacher_options($teachers),
		'attrs'       => array('data-flz-ui-submit-on-change' => '1'),
	));
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>

	<?php if ($selected->teacher) : ?>
		<?php echo flzest_render_step2($selected); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escaped dynamische Texte. ?>
	<?php else : ?>
		<p>Wählen Sie eine Lehrkraft aus!</p>
	<?php endif; ?>

	<?php echo $ui->hidden('selected', $selected_id, array('id' => 'selected')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Hidden Field. ?>
<?php echo $ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>

<?php

defined('ABSPATH') || exit;

$ui = flz_ui();
?>
<div class="button-group">
	<h3>
		Mögliche Termine bei
		<?php echo esc_html($selected->teacher->get_gender_as_anrede() . ' ' . $selected->teacher->name); ?>
	</h3>

	<?php if (empty($appointments)) : ?>
		<p>Leider sind alle Termine schon ausgebucht!</p>
	<?php else : ?>
		<?php foreach ($appointments as $appointment) : ?>
			<?php
			$time = date('H:i', $appointment->start);
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- flz_ui renderer escaped den Button inklusive Label, Icon-Text und Attribute.
			echo $ui->button(array(
				'label'    => $time,
				'type'     => 'submit',
				'variant'  => 'secondary',
				'icon'     => 'clock',
				'icon_alt' => 'Termin um ' . $time . ' auswählen',
				'attrs'    => array(
					'name'  => 'appointment',
					'id'    => 'appointment-' . (int) $appointment->id,
					'value' => (string) $appointment->id,
				),
			));
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<?php if (!empty($selected->id)) : ?>
	<?php echo flzest_render_step3($selected); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escaped dynamische Texte. ?>
<?php endif; ?>

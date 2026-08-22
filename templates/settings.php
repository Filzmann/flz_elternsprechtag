<?php $flzest_ui = flz_ui(); ?>
<div class="wrap">
    <h1>Elternsprechtag – Einstellungen</h1>
	<?php if ( ! empty( $settings_notice ) ) : ?>
		<?php echo $flzest_ui->notice( $settings_notice, 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
	<?php endif; ?>
    <div class="flz-ui-panel">
        <h2>Grundeinstellungen</h2>
        <p class="description">Diese Werte steuern den nächsten Elternsprechtag und die Länge der buchbaren Zeitfenster.</p>
		<?php echo $flzest_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzest_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
        <table class="widefat striped flz-ui-admin-table">
            <tr>
                <th>Name</th>
                <th>Wert</th>
            </tr>
            <?php foreach ( $settings as $setting ) : ?>
				<?php
				$flzest_setting_is_date = in_array( $setting->name, array( 'NextParentsDay' ), true );
				$flzest_setting_value   = $flzest_setting_is_date ? flz_ui_format_date( $setting->value, $setting->value ) : $setting->value;
				$flzest_description     = $flzest_setting_is_date ? 'Datumsformat: TT.MM.JJ' : '';
				?>
                <tr>
	                    <td><?php echo esc_html( $setting->name ); ?></td>
                    <td>
						<?php echo $flzest_ui->input( 'text', array( 'name' => 'settings[' . $setting->id . ']', 'label' => $setting->name, 'value' => $flzest_setting_value, 'required' => true, 'description' => $flzest_description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
                    </td>

                </tr>
            <?php endforeach; ?>
        </table>
			<?php echo $flzest_ui->button_save( array( 'label' => 'Einstellungen speichern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzest_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
    </div>

	<div class="flz-ui-panel">
		<h2>Datenschutz und Aufbewahrung</h2>
		<p>Automatische Löschung ist standardmäßig ausgeschaltet. Empfohlen sind 24 Monate nach dem Termin; der Zeitraum ist zwischen 1 und 120 Monaten einstellbar.</p>
		<p>Der WordPress-Datenexport liefert Eltern-/Kindbuchungen und Lehrkräftedaten. Der Eraser gibt gebuchte Termine frei, löscht Eltern-/Kinddaten und anonymisiert Lehrkräfte bei erhaltenem Terminplan.</p>
		<?php echo $flzest_ui->form_start(array('method' => 'post', 'nonce' => 'flzest_admin_action')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
			<?php echo $flzest_ui->field(array('type' => 'checkbox', 'name' => 'flzest_retention_enabled', 'label' => 'Automatische tägliche Löschung aktivieren', 'checked' => $retention_enabled)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
			<?php echo $flzest_ui->input('number', array('name' => 'flzest_retention_months', 'label' => 'Aufbewahrung in Monaten', 'value' => (string) $retention_months, 'min' => 1, 'max' => 120, 'required' => true)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
			<?php echo $flzest_ui->button_save(array('label' => 'Datenschutzeinstellungen speichern', 'attrs' => array('name' => 'flzest_privacy_settings', 'value' => '1'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzest_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>

		<?php if (null !== $privacy_deleted) : ?>
			<?php echo $flzest_ui->notice(sprintf('%d abgelaufene Elterndatensätze wurden gelöscht und ihre Termine freigegeben.', $privacy_deleted), 'success'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Notice. ?>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="flzest_delete_expired_bookings">
			<?php wp_nonce_field('flzest_delete_expired_bookings'); ?>
			<label><input type="checkbox" name="confirm_delete_expired_bookings" value="1" required> Ich bestätige die Löschung aller Eltern-/Kinddaten zu Terminen außerhalb der Aufbewahrungsfrist.</label>
			<?php echo $flzest_ui->button_delete(array('label' => 'Abgelaufene Buchungen jetzt löschen', 'type' => 'submit')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		</form>
	</div>

    <div class="flz-ui-panel">
        <h2>Demo-Daten</h2>
        <p>Legt ein kleines, klar als Demo erkennbares Beispiel an: drei Demo-Lehrkräfte, Zeitfenster für einen zukünftigen Elternsprechtag und zwei Beispielbuchungen.</p>
        <p class="description">Vorhandene Demo-Einträge werden wiederverwendet. Echte Daten werden nicht gelöscht.</p>
		<?php echo $flzest_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzest_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
			<?php echo $flzest_ui->button_new( array( 'label' => 'Demo-Daten anlegen oder auffrischen', 'type' => 'submit', 'attrs' => array( 'name' => 'flzest_install_demo', 'value' => '1' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzest_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
    </div>
</div>

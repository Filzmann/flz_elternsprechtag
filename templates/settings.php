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
				$flzest_setting_args          = flzest_setting_input_args( (string) $setting->name, (string) $setting->value );
				$flzest_setting_args['name']  = 'settings[' . $setting->id . ']';
				$flzest_setting_args['label'] = (string) $setting->name;
				?>
                <tr>
	                    <td><?php echo esc_html( $setting->name ); ?></td>
                    <td>
						<?php if ( 'checkbox' === $flzest_setting_args['type'] ) : ?>
							<?php echo $flzest_ui->hidden( 'settings[' . $setting->id . ']', '0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Hidden Field. ?>
							<?php echo $flzest_ui->field( $flzest_setting_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
						<?php else : ?>
							<?php $flzest_setting_args['required'] = true; ?>
							<?php echo $flzest_ui->input( (string) $flzest_setting_args['type'], $flzest_setting_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
						<?php endif; ?>
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
		<?php echo $flzest_ui->form_start( array( 'method' => 'post', 'action' => admin_url( 'admin-post.php' ), 'nonce' => 'flzest_delete_expired_bookings', 'hidden' => array( 'action' => 'flzest_delete_expired_bookings' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
			<?php echo $flzest_ui->field( array( 'type' => 'checkbox', 'name' => 'confirm_delete_expired_bookings', 'checkbox_value' => '1', 'label' => 'Ich bestätige die Löschung aller Eltern-/Kinddaten zu Terminen außerhalb der Aufbewahrungsfrist.', 'required' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
			<?php echo $flzest_ui->button_delete(array('label' => 'Abgelaufene Buchungen jetzt löschen', 'type' => 'submit')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzest_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
	</div>

    <div class="flz-ui-panel">
        <h2>Demo-Daten</h2>
        <p>Legt ein kleines, klar als Demo erkennbares Beispiel an: drei Demo-Lehrkräfte, Zeitfenster für einen zukünftigen Elternsprechtag und zwei Beispielbuchungen.</p>
        <p class="description">Alle erzeugten Personen- und Kinddaten sind synthetisch; E-Mail-Adressen verwenden example.test. Vorhandene Demo-Einträge werden wiederverwendet. Terminrahmen, Testmodus und Absender werden auf Demo-Werte gesetzt. Bestehende Daten bleiben erhalten. Für einen reinen Demobestand eine leere Demo-Installation verwenden.</p>
		<?php echo $flzest_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzest_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
			<?php echo $flzest_ui->button_new( array( 'label' => __('Demo-Daten anlegen', 'flz-elternsprechtag'), 'hide_label' => false, 'type' => 'submit', 'attrs' => array( 'name' => 'flzest_install_demo', 'value' => '1' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzest_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
		<p><?php echo esc_html__('Wiederholtes Anlegen lässt vorhandene Demo-Daten unverändert. Entfernen löscht nur nachweislich durch diesen Seed angelegte, unveränderte Datensätze. Nachträgliche Buchungen oder Bearbeitungen verhindern die Entfernung. Frühere Einstellungen werden wiederhergestellt, manuelle Änderungen bleiben erhalten. Ältere Demo-Daten ohne Herkunftsnachweis werden nicht automatisch übernommen oder gelöscht.', 'flz-elternsprechtag'); ?></p>
		<?php echo $flzest_ui->form_start(array('method' => 'post', 'nonce' => 'flzest_admin_action')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes the form. ?>
		<?php echo $flzest_ui->field(array('type' => 'checkbox', 'name' => 'flzest_confirm_remove_demo', 'checkbox_value' => '1', 'checked' => false, 'label' => __('Ich bestätige das Entfernen der unveränderten Demo-Daten.', 'flz-elternsprechtag'), 'required' => true)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes the field. ?>
		<?php echo $flzest_ui->button_delete(array('label' => __('Demo-Daten entfernen', 'flz-elternsprechtag'), 'hide_label' => false, 'type' => 'submit', 'attrs' => array('name' => 'flzest_remove_demo', 'value' => '1'))); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes the button. ?>
		<?php echo $flzest_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes the form. ?>
    </div>
</div>

<?php $flzest_ui = flz_ui(); ?>
<div class="wrap">
    <h1>Settings bearbeiten</h1>
    <div style="display: inline-block; width: 50%; overflow: auto; height: 20em;">
		<?php echo $flzest_ui->form_start( array( 'method' => 'post', 'nonce' => 'flzest_admin_action' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formular. ?>
        <table>
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
			<?php echo $flzest_ui->button_save( array( 'label' => 'Settings speichern' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped die Komponente. ?>
		<?php echo $flzest_ui->form_end(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escaped das Formularende. ?>
    </div>

</div>

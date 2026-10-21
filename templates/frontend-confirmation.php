<?php

defined( 'ABSPATH' ) || exit;

$ui = flz_ui();
?>
<div class="flzest-activation-confirmation">
	<?php if ( $activation_success ) : ?>
		<h2><?php esc_html_e( 'Aktivierung erledigt', 'flz-elternsprechtag' ); ?></h2>
		<?php echo wp_kses_post( wpautop( $activation_message ) ); ?>
		<?php echo $ui->button( array( 'href' => $redirect_url, 'label' => $redirect_label, 'variant' => 'primary' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI-Komponente escaped URL und Label. ?>
	<?php else : ?>
		<?php echo $ui->notice( __( 'Der Bestätigungslink ist ungültig oder abgelaufen. Bitte registrieren Sie den Termin erneut.', 'flz-elternsprechtag' ), 'error' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI-Komponente escaped die Meldung. ?>
	<?php endif; ?>
</div>

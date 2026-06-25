<?php

// Alle POST-Pfade laufen durch flzest_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing

function flzest_settings_page(): void
{
	try {
		flzest_settings_page_content();
	} catch ( Throwable $error ) {
		flzest_render_admin_error( $error, 'Anzeigen der Elternsprechtags-Einstellungen' );
	}
}

function flzest_settings_page_content(): void
{
	flzest_assert_admin_request();
	if ( isset( $_POST['settings'] ) )
	{
			$posted_settings = map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' );
			flz_wpdb_objects\FlzWpdbTransaction::run(
				static function () use ( $posted_settings ): void {
					foreach ( (array) $posted_settings as $id => $value ) {
						$setting = FlzEstSetting::get_by_id( absint( $id ) );
						if ( ! $setting instanceof FlzEstSetting ) {
							throw new UnexpectedValueException( 'Eine zu speichernde Einstellung wurde nicht gefunden.' );
						}
						$setting->value = $value;
						$setting->save();
					}
				},
				'Speichern der Elternsprechtags-Einstellungen'
			);
	}
	$settings = FlzEstSetting::get_all_by();
	include( plugin_dir_path( __FILE__ ) . '../templates/settings.php' );
}

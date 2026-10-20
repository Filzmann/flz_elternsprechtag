<?php

defined('ABSPATH') || exit;

/**
 * Protokolliert die vollständige Exception-Kette des Elternsprechtags.
 */
function flzest_log_error(Throwable $error, string $context): void
{
	flz_wpdb_objects\FlzWpdbObjectsException::log_error($error, 'flz_elternsprechtag', $context);
}

/**
 * Returns a user-safe, actionable message for a CSV import failure.
 *
 * Known validation and upload failures are controlled by this plugin or its
 * shared CSV helper. Unexpected infrastructure errors remain in the log so
 * database paths and other implementation details are never exposed.
 */
function flzest_csv_import_error_message( Throwable $error, string $csv_label ): string
{
	$current = $error;
	do {
		if ( $current instanceof UnexpectedValueException || $current instanceof InvalidArgumentException ) {
			return $current->getMessage();
		}

		if ( $current instanceof RuntimeException ) {
			$message = $current->getMessage();
			if ( in_array( $message, array( 'Keine CSV-Datei hochgeladen.', 'Die CSV-Datei stammt nicht aus einem gültigen HTTP-Upload.' ), true ) ) {
				return $message;
			}
			if ( preg_match( '/^Der CSV-Upload wurde nicht vollständig übertragen \(Fehlercode ([0-9]+)\)\.$/', $message, $matches ) ) {
				$upload_messages = array(
					1 => 'Der CSV-Upload überschreitet das serverseitige Dateigrößenlimit.',
					2 => 'Der CSV-Upload überschreitet das im Formular erlaubte Dateigrößenlimit.',
					3 => 'Der CSV-Upload wurde nur teilweise übertragen. Bitte laden Sie die Datei erneut hoch.',
					4 => 'Keine CSV-Datei hochgeladen.',
					6 => 'Der Server kann die CSV-Datei nicht zwischenspeichern. Bitte versuchen Sie es später erneut.',
					7 => 'Der Server kann die CSV-Datei nicht speichern. Bitte versuchen Sie es später erneut.',
					8 => 'Der CSV-Upload wurde durch eine Servererweiterung abgebrochen. Bitte wenden Sie sich an die Administration.',
				);
				$code = (int) $matches[1];
				if ( isset( $upload_messages[ $code ] ) ) {
					return $upload_messages[ $code ];
				}
			}
		}
		$current = $current->getPrevious();
	} while ( $current instanceof Throwable );

	return sprintf(
		'%s konnte nicht verarbeitet werden. Bitte laden Sie die unveränderte CSV-Datei erneut hoch, führen Sie zuerst einen Dry-Run aus und prüfen Sie Dateiformat sowie Uploadgröße. Wenn das Problem bleibt, wenden Sie sich mit dem Zeitpunkt an die Administration; die technische Ursache wurde protokolliert.',
		$csv_label
	);
}

/**
 * Sichere Fehlergrenze für Backend-Seiten.
 */
function flzest_render_admin_error(Throwable $error, string $context): void
{
	flzest_log_error($error, $context);
	echo '<div class="notice notice-error"><p>'
		. esc_html__('Die Daten konnten nicht verarbeitet werden. Details stehen im Serverprotokoll.', 'flz-elternsprechtag')
		. '</p></div>';
}

/**
 * Ergänzt eine technische Ursache um den fachlichen Elternsprechtags-Kontext.
 */
function flzest_operation_error(Throwable $error, string $operation): flz_wpdb_objects\FlzWpdbObjectsException
{
	return flz_wpdb_objects\FlzWpdbObjectsException::operation(
		$operation,
		'Plugin flz_elternsprechtag',
		$error
	);
}

/**
 * Prüft Berechtigung und Nonce einer Elternsprechtags-Backend-Seite.
 */
function flzest_assert_admin_request(): void
{
	if ( ! current_user_can( 'flz_est' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'flz-elternsprechtag' ) );
	}

	$request_method = isset( $_SERVER['REQUEST_METHOD'] )
		? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
		: '';
	if ( 'post' === $request_method ) {
		check_admin_referer( 'flzest_admin_action' );
	}
}

/**
 * Prüft Berechtigung und Nonce eines CSV-Direktdownloads.
 */
function flzest_assert_csv_export_request( string $nonce_action ): void
{
	if ( ! current_user_can( 'flz_est' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'flz-elternsprechtag' ) );
	}

	check_admin_referer( $nonce_action );
}

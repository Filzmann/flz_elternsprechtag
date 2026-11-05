<?php

declare( strict_types=1 );

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

function absint( mixed $value ): int {
	return abs( (int) $value );
}

function add_action( string $hook, string $callback ): void {}

class FlzEstSetting {
	/** @var array<string,string> */
	public static array $values = array(
		'UnconfirmedBookingReleaseEnabled' => '1',
		'UnconfirmedBookingReleaseHours'   => '48',
		'UnconfirmedBookingWarningHours'   => '24',
	);

	public static function get_value_by_name( string $name ): string {
		return static::$values[ $name ] ?? '';
	}
}

class FlzEstParent {}

class FlzEstAppointment {
	public FlzEstParent|null $parent = null;
	public bool $isConfirmed = false;
	public int $confirmationExpiration = 0;
	public int $confirmationWarningSentAt = 0;
}

require dirname( __DIR__ ) . '/includes/unconfirmed-booking-release.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-Testdiagnostik.
		throw new RuntimeException( $message );
	}
};

$appointment = new FlzEstAppointment();
$appointment->parent = new FlzEstParent();
$appointment->confirmationExpiration = 10_000;

$assert( flzest_unconfirmed_booking_release_enabled(), 'Die aktivierte Freigabeautomatik wurde nicht erkannt.' );
$assert( 48 === flzest_unconfirmed_booking_release_hours(), 'Der einstellbare Freigabezeitraum wurde nicht gelesen.' );
$assert( 24 === flzest_unconfirmed_booking_warning_hours(), 'Der einstellbare Warnabstand wurde nicht gelesen.' );
$assert( flzest_unconfirmed_booking_should_warn( $appointment, 10_000 - 24 * 3600 ), 'Die Warnung muss innerhalb des Warnfensters fällig werden.' );
$assert( ! flzest_unconfirmed_booking_should_warn( $appointment, 10_000 - 24 * 3600 - 1 ), 'Eine Warnung darf nicht vor dem Warnfenster versendet werden.' );
$assert( flzest_unconfirmed_booking_should_release( $appointment, 10_000 ), 'Eine abgelaufene unbestätigte Buchung muss freigegeben werden.' );

$appointment->confirmationWarningSentAt = 1;
$assert( ! flzest_unconfirmed_booking_should_warn( $appointment, 10_000 - 10 ), 'Eine bereits gewarnte Buchung darf nicht erneut gewarnt werden.' );
$appointment->isConfirmed = true;
$assert( ! flzest_unconfirmed_booking_should_release( $appointment, 10_001 ), 'Eine bestätigte Buchung darf nicht automatisch freigegeben werden.' );

$release_source = file_get_contents( dirname( __DIR__ ) . '/includes/unconfirmed-booking-release.php' );
$frontend_source = file_get_contents( dirname( __DIR__ ) . '/frontend/frontend.php' );
$assert( is_string( $release_source ) && str_contains( $release_source, "add_action( 'flzest_hourly_unconfirmed_booking_release'" ), 'Der stündliche Freigabejob wurde nicht registriert.' );
$assert( is_string( $release_source ) && str_contains( $release_source, 'flzest_release_expired_unconfirmed_booking' ), 'Die transaktionale Freigabe fehlt.' );
$assert( is_string( $frontend_source ) && str_contains( $frontend_source, 'flzest_send_unconfirmed_booking_lifecycle_mail' ), 'Die Elternbenachrichtigung zur Freigabe fehlt.' );

echo "OK: flz_elternsprechtag unconfirmed booking release smoke test\n";

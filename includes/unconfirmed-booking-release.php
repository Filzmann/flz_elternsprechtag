<?php

defined( 'ABSPATH' ) || exit;

add_action( 'flzest_hourly_unconfirmed_booking_release', 'flzest_run_unconfirmed_booking_release' );

function flzest_unconfirmed_booking_release_enabled(): bool
{
	return '1' === FlzEstSetting::get_value_by_name( 'UnconfirmedBookingReleaseEnabled' );
}

function flzest_unconfirmed_booking_release_hours(): int
{
	return max( 1, min( 720, absint( FlzEstSetting::get_value_by_name( 'UnconfirmedBookingReleaseHours' ) ) ) );
}

function flzest_unconfirmed_booking_warning_hours(): int
{
	return max( 0, min( flzest_unconfirmed_booking_release_hours() - 1, absint( FlzEstSetting::get_value_by_name( 'UnconfirmedBookingWarningHours' ) ) ) );
}

function flzest_unconfirmed_booking_should_warn( FlzEstAppointment $appointment, int $now ): bool
{
	$warning_hours = flzest_unconfirmed_booking_warning_hours();

	return $appointment->parent instanceof FlzEstParent
		&& ! $appointment->isConfirmed
		&& 0 === $appointment->confirmationWarningSentAt
		&& $warning_hours > 0
		&& $appointment->confirmationExpiration > $now
		&& $appointment->confirmationExpiration <= $now + $warning_hours * HOUR_IN_SECONDS;
}

function flzest_unconfirmed_booking_should_release( FlzEstAppointment $appointment, int $now ): bool
{
	return $appointment->parent instanceof FlzEstParent
		&& ! $appointment->isConfirmed
		&& $appointment->confirmationExpiration > 0
		&& $appointment->confirmationExpiration <= $now;
}

function flzest_schedule_unconfirmed_booking_release(): void
{
	if ( wp_next_scheduled( 'flzest_hourly_unconfirmed_booking_release' ) ) {
		return;
	}

	$scheduled = wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'flzest_hourly_unconfirmed_booking_release', array(), true );
	if ( is_wp_error( $scheduled ) || false === $scheduled ) {
		throw new RuntimeException( 'Der stündliche Freigabe-Job für unbestätigte Buchungen konnte nicht eingerichtet werden.' );
	}
}

function flzest_unschedule_unconfirmed_booking_release(): void
{
	$cleared = wp_clear_scheduled_hook( 'flzest_hourly_unconfirmed_booking_release', array(), true );
	if ( is_wp_error( $cleared ) ) {
		throw new RuntimeException( 'Der Freigabe-Job für unbestätigte Buchungen konnte nicht entfernt werden.' );
	}
}

/**
 * @return FlzEstAppointment|null A mail-safe snapshot when the warning was newly marked.
 */
function flzest_mark_unconfirmed_booking_warning( int $appointment_id, int $now ): ?FlzEstAppointment
{
	return flz_wpdb_objects\FlzWpdbTransaction::run(
		static function () use ( $appointment_id, $now ): ?FlzEstAppointment {
			$appointment = FlzEstAppointment::get_by_id_for_update( $appointment_id );
			if ( ! $appointment instanceof FlzEstAppointment || ! flzest_unconfirmed_booking_should_warn( $appointment, $now ) ) {
				return null;
			}
			$notification = clone $appointment;
			$appointment->mark_confirmation_warning_sent( $now );

			return $notification;
		},
		'Kennzeichnen einer Warnung vor automatischer Terminfreigabe'
	);
}

/**
 * @return FlzEstAppointment|null A mail-safe snapshot when a booking was released.
 */
function flzest_release_expired_unconfirmed_booking( int $appointment_id, int $now ): ?FlzEstAppointment
{
	return flz_wpdb_objects\FlzWpdbTransaction::run(
		static function () use ( $appointment_id, $now ): ?FlzEstAppointment {
			$appointment = FlzEstAppointment::get_by_id_for_update( $appointment_id );
			if ( ! $appointment instanceof FlzEstAppointment || ! flzest_unconfirmed_booking_should_release( $appointment, $now ) ) {
				return null;
			}
			$notification = clone $appointment;
			$parent = $appointment->delete_unconfirmed_booking();
			if ( $parent instanceof FlzEstParent && null === FlzEstAppointment::get_by_fields( array( 'parent_id' => (int) $parent->id ) ) ) {
				$parent->delete();
			}

			return $notification;
		},
		'Automatisches Freigeben einer unbestätigten Elternsprechtagsbuchung'
	);
}

/**
 * Runs the hourly warning and release workflow. Mail errors never restore a released slot.
 *
 * @return array{warnings:int,released:int}
 */
function flzest_run_unconfirmed_booking_release(): array
{
	$result = array( 'warnings' => 0, 'released' => 0 );
	if ( ! flzest_unconfirmed_booking_release_enabled() ) {
		return $result;
	}

	$now = time();
	foreach ( FlzEstAppointment::get_all_by() as $appointment ) {
		if ( ! $appointment instanceof FlzEstAppointment || empty( $appointment->id ) ) {
			continue;
		}
		try {
			if ( flzest_unconfirmed_booking_should_warn( $appointment, $now ) ) {
				$warning = flzest_mark_unconfirmed_booking_warning( (int) $appointment->id, $now );
				if ( $warning instanceof FlzEstAppointment ) {
					flzest_send_unconfirmed_booking_lifecycle_mail( $warning, 'MailUnconfirmedBookingWarningSubject', 'MailUnconfirmedBookingWarningBody' );
					++$result['warnings'];
				}
			}
			if ( flzest_unconfirmed_booking_should_release( $appointment, $now ) ) {
				$released = flzest_release_expired_unconfirmed_booking( (int) $appointment->id, $now );
				if ( $released instanceof FlzEstAppointment ) {
					flzest_send_unconfirmed_booking_lifecycle_mail( $released, 'MailUnconfirmedBookingReleasedSubject', 'MailUnconfirmedBookingReleasedBody' );
					++$result['released'];
				}
			}
		} catch ( Throwable $error ) {
			flzest_log_error( $error, 'Warnen oder Freigeben einer unbestätigten Elternsprechtagsbuchung' );
		}
	}

	return $result;
}

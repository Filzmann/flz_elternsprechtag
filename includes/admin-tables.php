<?php

defined( 'ABSPATH' ) || exit;

/**
 * Prüft einen gemeinsamen Admin-Tabellenfilter gegen die sichtbaren Werte
 * einer Zeile. Der Filter wird ausschließlich für die Darstellung verwendet
 * und verändert keine Fach- oder Personen-Daten.
 *
 * @param array<int,int|string|null> $values Sichtbare Werte einer Tabellenzeile.
 */
function flzest_admin_table_matches_search( string $search, array $values ): bool {
	if ( '' === $search ) {
		return true;
	}

	foreach ( $values as $value ) {
		if ( false !== stripos( (string) $value, $search ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Normalisiert den Bestätigungsfilter der Termintabelle auf erlaubte Werte.
 */
function flzest_admin_table_confirmation_filter( string $filter ): string {
	return in_array( $filter, array( 'all', 'confirmed', 'unconfirmed' ), true )
		? $filter
		: 'all';
}

/**
 * Prüft einen Termin gegen den Bestätigungsfilter. Freie Termine besitzen
 * keinen Bestätigungsstatus und werden deshalb nur bei „alle“ angezeigt.
 */
function flzest_admin_table_matches_confirmation_filter( string $filter, bool $is_booked, bool $is_confirmed ): bool {
	if ( 'confirmed' === $filter ) {
		return $is_booked && $is_confirmed;
	}

	if ( 'unconfirmed' === $filter ) {
		return $is_booked && ! $is_confirmed;
	}

	return true;
}

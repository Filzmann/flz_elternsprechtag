<?php

require_once dirname( __DIR__ ) . '/includes/csv-contract.php';

if ( ! in_array( 'Datensatz-ID', flzest_teacher_csv_header(), true ) || ! in_array( 'Datensatz-ID', flzest_appointment_csv_header(), true ) ) {
	throw new RuntimeException( 'Alle Elternsprechtag-CSV-Exporte benötigen eine portable Datensatz-ID.' );
}

echo "OK: portable record IDs in Elternsprechtag CSV contracts\n";

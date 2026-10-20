<?php

declare(strict_types=1);

// Exception-Texte sind ausschließlich lokale CLI-Testdiagnostik.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

$frontend_file = dirname( __DIR__ ) . '/frontend/frontend.php';
$source = file_get_contents( $frontend_file );
if ( false === $source ) {
	throw new RuntimeException( 'Frontend-Quelldatei konnte nicht gelesen werden.' );
}

if ( preg_match( '/@(?:gmail|googlemail)\.[a-z]{2,}/i', $source ) ) {
	throw new RuntimeException( 'Der Mail-Testmodus enthält eine private Gmail-Adresse.' );
}

if ( ! str_contains( $source, "'private-test@example.test'" ) ) {
	throw new RuntimeException( 'Der Mail-Testmodus verwendet keinen eindeutig synthetischen Empfänger.' );
}

echo "OK: flz_elternsprechtag mail privacy smoke test\n";

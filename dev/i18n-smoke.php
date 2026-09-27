<?php
require '/wordpress/wp-load.php';
$changed = switch_to_locale( 'fr_FR' );
$expected = array( 'Bank' => 'Banque', 'Account number' => 'Numéro de compte', 'Sort code' => 'Code bancaire', 'Pay by bank transfer' => 'Payer par virement' );
foreach ( $expected as $english => $french ) {
 if ( $french !== __( $english, 'jeytech-order-transfer-qr' ) ) { throw new RuntimeException( 'Bundled French mismatch: ' . $english ); }
}
if ( ! $changed ) { throw new RuntimeException( 'French core locale unavailable' ); }
restore_previous_locale();
if ( 'Bank' !== __( 'Bank', 'jeytech-order-transfer-qr' ) ) { throw new RuntimeException( 'English restoration failed' ); }
file_put_contents( __DIR__ . '/results/i18n-smoke.txt', 'PASS Bundled French and English restoration from final ZIP. WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . "\n" );

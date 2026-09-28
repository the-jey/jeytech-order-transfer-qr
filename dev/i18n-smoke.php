<?php
require '/wordpress/wp-load.php';
$has_pack = is_file( WP_LANG_DIR . '/plugins/jeytech-order-transfer-qr-fr_FR.mo' );
$early_loading = array();
add_action( 'doing_it_wrong_run', static function ( $function ) use ( &$early_loading ) {
 if ( '_load_textdomain_just_in_time' === $function ) { $early_loading[] = $function; }
} );
$changed = switch_to_locale( 'fr_FR' );
$expected = array( 'Bank' => 'Banque', 'Account number' => 'Numéro de compte', 'Sort code' => 'Code bancaire', 'Pay by bank transfer' => 'Payer par virement' );
foreach ( $expected as $english => $french ) {
 if ( ( $has_pack ? $french : $english ) !== __( $english, 'jeytech-order-transfer-qr' ) ) { throw new RuntimeException( 'WordPress language-pack mismatch: ' . $english ); }
}
if ( ! $changed ) { throw new RuntimeException( 'French core locale unavailable' ); }
restore_previous_locale();
if ( 'Bank' !== __( 'Bank', 'jeytech-order-transfer-qr' ) ) { throw new RuntimeException( 'English restoration failed' ); }
if ( ! empty( $early_loading ) ) { throw new RuntimeException( 'Translations loaded too early' ); }
$fixture = $has_pack ? 'External WordPress French language pack' : 'English fallback without a language pack';
$report = $has_pack ? 'i18n-smoke-pack.txt' : 'i18n-smoke-no-pack.txt';
file_put_contents( __DIR__ . '/results/' . $report, 'PASS ' . $fixture . ' and English restoration from final production files. WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . "; no early-loading notice.\n" );

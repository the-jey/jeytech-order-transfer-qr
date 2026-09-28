<?php
/** Functional tests executed against a real WordPress + WooCommerce instance. */
use JeyTech\OrderTransferQR\Accounts;
use JeyTech\OrderTransferQR\Admin\SettingsPage;
use JeyTech\OrderTransferQR\Display;
use JeyTech\OrderTransferQR\EpcPayload;
use JeyTech\OrderTransferQR\ImageEndpoint;
use JeyTech\OrderTransferQR\Png;
use JeyTech\OrderTransferQR\Settings;
use JeyTech\OrderTransferQR\Transfer;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/lib.php';
$mode = 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ? 'hpos' : 'posts';
$lines = array();
$failures = 0;
$check = static function ( string $name, bool $passed ) use ( &$lines, &$failures ) {
	$lines[] = ( $passed ? 'PASS  ' : 'FAIL  ' ) . $name;
	if ( ! $passed ) { ++$failures; }
};
$capture = static function ( callable $run ): string { ob_start(); $run(); return ob_get_clean(); };
try {
	jeytech_otqr_dev_prepare();
	$check( 'Runtime WordPress/WooCommerce/PHP versions captured', defined( 'WC_VERSION' ) && function_exists( 'gzcompress' ) );
	$check( 'French and German IBANs, spaces and case accepted', EpcPayload::valid_iban( 'fr14 2004 1010 0505 0001 3m02 606' ) && EpcPayload::valid_iban( 'DE71110220330123456789' ) );
	$check( 'Wrong checksum, wrong country length and unsupported country rejected', ! EpcPayload::valid_iban( 'FR1420041010050500013M02607' ) && ! EpcPayload::valid_iban( 'DE7111022033012345678' ) && ! EpcPayload::valid_iban( 'ZZ1420041010050500013M02606' ) );
	$check( 'Exact cents and trailing zero preserved without float rounding', '19.90' === EpcPayload::amount( '19.9' ) && '0.01' === EpcPayload::amount( '0.01' ) && '999999999.99' === EpcPayload::amount( '999999999.99' ) );
	$check( 'Zero, negative, over-limit, exponent and fractional cent rejected', is_wp_error( EpcPayload::amount( '0' ) ) && is_wp_error( EpcPayload::amount( '-1.00' ) ) && is_wp_error( EpcPayload::amount( '1000000000' ) ) && is_wp_error( EpcPayload::amount( '1e2' ) ) && is_wp_error( EpcPayload::amount( '1.001' ) ) );
	$account = array( 'name' => 'JeyTech Démo & Cie', 'iban' => 'FR1420041010050500013M02606', 'bic' => '' );
	$check( 'EEA beneficiary may omit BIC', true === EpcPayload::validate_account( $account ) );
	$uk = array( 'name' => 'Demo UK', 'iban' => 'GB82WEST12345698765432', 'bic' => '' );
	$check( 'Non-EEA beneficiary requires BIC', is_wp_error( EpcPayload::validate_account( $uk ) ) && true === EpcPayload::validate_account( array_merge( $uk, array( 'bic' => 'WESTGB2L' ) ) ) );
	$check( 'Malformed BIC and newline beneficiary rejected', is_wp_error( EpcPayload::validate_account( array_merge( $account, array( 'bic' => 'BAD' ) ) ) ) && is_wp_error( EpcPayload::validate_account( array_merge( $account, array( 'name' => "Name\nOther IBAN" ) ) ) ) );
	$check( 'Overlong reference and separator injection rejected', is_wp_error( EpcPayload::build( $account, '1.00', str_repeat( 'a', 141 ) ) ) && is_wp_error( EpcPayload::build( $account, '1.00', "Order\nSCT" ) ) );
	$payload = EpcPayload::build( $account, '19.90', 'JT-1042' );
	$fields = explode( "\n", $payload );
	$check( 'EPC002 UTF-8 payload has exact amount, empty RF field and no trailing LF', 11 === count( $fields ) && array_slice( $fields, 0, 4 ) === array( 'BCD', '002', '1', 'SCT' ) && 'EUR19.90' === $fields[7] && '' === $fields[9] && 'JT-1042' === $fields[10] && "\n" !== substr( $payload, -1 ) );
	$boundary_account = array( 'name' => str_repeat( 'A', 70 ), 'iban' => 'DE71110220330123456789', 'bic' => 'BHBLDEHHXXX' );
	$boundary = EpcPayload::build( $boundary_account, '999999999.99', str_repeat( 'é', 53 ) . str_repeat( 'B', 87 ) );
	$check( '331-byte multibyte boundary accepted, 332 bytes rejected', is_string( $boundary ) && 331 === strlen( $boundary ) && is_wp_error( EpcPayload::build( $boundary_account, '999999999.99', str_repeat( 'é', 54 ) . str_repeat( 'B', 86 ) ) ) );
	$samples = array( 'utf8' => $payload, 'boundary331' => $boundary, 'greek' => EpcPayload::build( array_merge( $account, array( 'name' => 'Εταιρεία Démo' ) ), '0.01', 'Référence-42' ) );
	$manifest = array();
	foreach ( $samples as $label => $sample ) {
		$png = Png::render( $sample );
		file_put_contents( __DIR__ . '/.test-' . $mode . '-' . $label . '.png', $png );
		$manifest[] = array( 'file' => '.test-' . $mode . '-' . $label . '.png', 'payload' => $sample, 'bytes' => strlen( $sample ) );
		$check( 'PNG generated for ' . $label . ' with no GD/Imagick', "\x89PNG\r\n\x1A\n" === substr( $png, 0, 8 ) );
	}
	file_put_contents( __DIR__ . '/.test-qr-' . $mode . '.json', wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE ) );
	$product = jeytech_otqr_dev_product();
	$order = jeytech_otqr_dev_order( $product );
	$details = Transfer::details( $order );
	$check( 'Real BACS on-hold EUR order receives exact data', null !== $details && '19.90' === $details['amount'] && 'JT-' . $order->get_order_number() === $details['reference'] );
	$stock = wc_get_product( $product->get_id() )->get_stock_quantity();
	$html = Display::html( $order, $details );
	$check( 'HTML contains escaped beneficiary, readable IBAN/amount/reference and hosted PNG', false !== strpos( $html, 'JeyTech Démo &amp; Cie' ) && false !== strpos( $html, $details['account']['iban'] ) && false !== strpos( $html, 'EUR 19.90' ) && false !== strpos( $html, $details['reference'] ) && false !== strpos( $html, 'admin-post.php' ) && false === strpos( $html, 'data:image' ) );
	$check( 'Plain text preserves raw ampersand without HTML', false !== strpos( Display::text( $details ), 'JeyTech Démo & Cie' ) && false === strpos( Display::text( $details ), '<img' ) );
	$order_after = wc_get_order( $order->get_id() );
	$check( 'Rendering does not change order status, total or stock', 'on-hold' === $order_after->get_status() && $order->get_total() === $order_after->get_total() && $stock === wc_get_product( $product->get_id() )->get_stock_quantity() );
	$url = ImageEndpoint::url( $order, $details ); parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $args );
	$check( 'Correct signed link authorized', null !== ImageEndpoint::authorize( $order, (int) $args['expires'], $args['signature'] ) );
	$check( 'Forged, altered expiry and expired links denied', null === ImageEndpoint::authorize( $order, (int) $args['expires'], str_repeat( 'a', 64 ) ) && null === ImageEndpoint::authorize( $order, (int) $args['expires'] + 1, $args['signature'] ) && null === ImageEndpoint::authorize( $order, time() - 1, $args['signature'] ) );
	$other = jeytech_otqr_dev_order( $product );
	$check( 'Signature cannot be reused for another order', null === ImageEndpoint::authorize( $other, (int) $args['expires'], $args['signature'] ) );
	$order->set_total( '20.00' ); $order->save();
	$check( 'Changing total revokes the old image link', null === ImageEndpoint::authorize( $order, (int) $args['expires'], $args['signature'] ) );
	$order->set_total( '19.90' ); $order->save();
	foreach ( array( 'processing', 'completed', 'cancelled', 'refunded', 'failed', 'pending' ) as $status ) {
		$order->update_status( $status );
		$check( 'No QR or image for status ' . $status, null === Transfer::details( $order ) && null === ImageEndpoint::authorize( $order, (int) $args['expires'], $args['signature'] ) );
	}
	$order->update_status( 'on-hold' );
	$order->set_currency( 'USD' ); $order->save();
	$check( 'Non-EUR order omitted', null === Transfer::details( $order ) );
	$order->set_currency( 'EUR' ); $order->set_payment_method( 'cod' ); $order->save();
	$check( 'Other payment methods omitted', null === Transfer::details( $order ) );
	$order->set_payment_method( 'bacs' ); $order->save();
	$settings = Settings::get();
	$reordered = array_reverse( get_option( 'woocommerce_bacs_accounts' ) ); update_option( 'woocommerce_bacs_accounts', $reordered );
	$check( 'Reordering accounts preserves beneficiary and valid link', $details['account'] === Transfer::details( $order )['account'] && null !== ImageEndpoint::authorize( $order, (int) $args['expires'], $args['signature'] ) );
	$changed = $reordered; $changed[1]['account_name'] = 'Different beneficiary'; update_option( 'woocommerce_bacs_accounts', $changed );
	$check( 'Changing selected account requires reselection and revokes link', null === Transfer::details( $order ) && null === ImageEndpoint::authorize( $order, (int) $args['expires'], $args['signature'] ) );
	update_option( 'woocommerce_bacs_accounts', $reordered );
	$filter_accounts = static function () { return array(); }; add_filter( 'woocommerce_bacs_accounts', $filter_accounts );
	$check( 'Per-order account removal omits QR', null === Transfer::details( $order ) ); remove_filter( 'woocommerce_bacs_accounts', $filter_accounts );
	$complete_fields = false;
	$inspect_fields = static function ( $fields ) use ( &$complete_fields ) { $complete_fields = array_keys( $fields ) === array( 'bank_name', 'account_number', 'sort_code', 'iban', 'bic' ) && 'Demo bank' === $fields['bank_name']['value']; return $fields; };
	add_filter( 'woocommerce_bacs_account_fields', $inspect_fields ); Transfer::details( $order );
	$check( 'Bank field filters receive all five native fields and their values', $complete_fields );
	remove_filter( 'woocommerce_bacs_account_fields', $inspect_fields );
	$filter_fields = static function ( $fields ) { $fields['iban']['value'] = 'DE71110220330123456789'; return $fields; }; add_filter( 'woocommerce_bacs_account_fields', $filter_fields );
	$check( 'Changed displayed IBAN never contradicts QR', null === Transfer::details( $order ) ); remove_filter( 'woocommerce_bacs_account_fields', $filter_fields );
	$changed_reference = array_merge( $settings, array( 'reference' => 'OTHER-{order_number}' ) ); update_option( Settings::OPTION, $changed_reference );
	$check( 'Changing reference revokes old URL', null === ImageEndpoint::authorize( $order, (int) $args['expires'], $args['signature'] ) ); update_option( Settings::OPTION, $settings );
	$check( 'Invalid enabled account rejected without changing saved settings', Settings::get() === Settings::sanitize( array_merge( $settings, array( 'account' => 'unknown' ) ) ) );
	$check( 'Missing placeholder and unsupported placeholder rejected', Settings::get() === Settings::sanitize( array_merge( $settings, array( 'reference' => 'constant' ) ) ) && Settings::get() === Settings::sanitize( array_merge( $settings, array( 'reference' => '{order_number}-{email}' ) ) ) );
	$check( 'Settings survive WordPress repeated sanitization', $settings === Settings::sanitize( Settings::sanitize( $settings ) ) );
	$_GET['key'] = $order->get_order_key();
	$thankyou = $capture( static function () use ( $order ) { do_action( 'woocommerce_thankyou_bacs', $order->get_id() ); } );
	$check( 'Classic thank-you action includes QR', false !== strpos( $thankyou, 'jeytech-otqr-payment' ) );
	$_GET['key'] = 'incorrect';
	$check( 'Direct thank-you hook cannot reveal QR with wrong key', '' === $capture( static function () use ( $order ) { Display::thankyou( $order->get_id() ); } ) );
	$_GET['key'] = $order->get_order_key();
	set_query_var( 'order-received', $order->get_id() );
	$block = do_blocks( '<!-- wp:woocommerce/order-confirmation-additional-information /-->' );
	$check( 'Actual WooCommerce order confirmation block includes QR', false !== strpos( $block, 'jeytech-otqr-payment' ) );
	$email = (object) array( 'id' => 'customer_on_hold_order' );
	$check( 'Customer on-hold HTML email hook includes QR', false !== strpos( $capture( static function () use ( $order, $email ) { do_action( 'woocommerce_email_before_order_table', $order, false, false, $email ); } ), 'jeytech-otqr-payment' ) );
	$check( 'Admin and other customer emails never receive QR', '' === $capture( static function () use ( $order, $email ) { Display::email( $order, true, false, $email ); } ) && '' === $capture( static function () use ( $order ) { Display::email( $order, false, false, (object) array( 'id' => 'customer_completed_order' ) ); } ) );
	$check( 'Plain-text email hook has bank coordinates and no PNG', false !== strpos( $capture( static function () use ( $order, $email ) { Display::email( $order, false, true, $email ); } ), $details['account']['iban'] ) );
	update_option( Settings::OPTION, array_merge( $settings, array( 'show_email' => false, 'show_thankyou' => false ) ) );
	$check( 'Display switches independently suppress each location', '' === $capture( static function () use ( $order, $email ) { Display::email( $order, false, false, $email ); } ) && '' === $capture( static function () use ( $order ) { Display::thankyou( $order->get_id() ); } ) ); update_option( Settings::OPTION, $settings );
	update_option( Settings::OPTION, array_merge( $settings, array( 'enabled' => false ) ) );
	$check( 'Disabling plugin setting revokes image links', null === Transfer::details( $order ) && null === ImageEndpoint::authorize( $order, (int) $args['expires'], $args['signature'] ) ); update_option( Settings::OPTION, $settings );
	$customer = wp_create_user( 'qr_customer', 'test-password', 'qr-customer@example.test' ); wp_set_current_user( $customer );
	$check( 'Customer role cannot render administration', '' === $capture( array( SettingsPage::class, 'render' ) ) );
	$order->set_customer_id( $customer ); $order->save(); wp_set_current_user( 0 );
	$check( 'Known customer order needs ownership even with a correct key', '' === $capture( static function () use ( $order ) { Display::thankyou( $order->get_id() ); } ) );
	wp_set_current_user( $customer );
	$check( 'Owning customer can see their QR', false !== strpos( $capture( static function () use ( $order ) { Display::thankyou( $order->get_id() ); } ), 'jeytech-otqr-payment' ) );
	wp_set_current_user( 1 ); SettingsPage::settings(); SettingsPage::register();
	$check( 'Admin settings use a nonce and WooCommerce capability', false !== strpos( $capture( array( SettingsPage::class, 'render' ) ), '_wpnonce' ) && 'manage_woocommerce' === apply_filters( 'option_page_capability_' . Settings::GROUP, 'manage_options' ) );
	SettingsPage::assets( 'woocommerce_page_wc-orders' );
	$check( 'Plugin CSS does not affect other admin screens', ! wp_style_is( 'jeytech-otqr-admin', 'enqueued' ) );
	SettingsPage::assets( 'woocommerce_page_jeytech-otqr' );
	$check( 'Plugin CSS is loaded on its own screen', wp_style_is( 'jeytech-otqr-admin', 'enqueued' ) );
	if ( PHP_VERSION_ID >= 80000 ) {
		$native_email = WC()->mailer()->get_emails()['WC_Email_Customer_On_Hold_Order']; $native_email->object = $order;
		$native = $native_email->get_content_html();
		$check( 'Actual WooCommerce HTML email renders hosted QR', false !== strpos( $native, 'jeytech-otqr-payment' ) && false !== strpos( $native, 'EUR 19.90' ) );
		file_put_contents( __DIR__ . '/.test-' . $mode . '-email.html', $native );
	} else {
		$lines[] = 'NOTE  Native WooCommerce mail template is tested on PHP 8.3; Playground PHP 7.4 cannot render it. Plugin hook and exact text are tested on both.';
	}
	$locale_changed = switch_to_locale( 'fr_FR' );
	$check( 'External WordPress French language pack renders customer instructions', $locale_changed && false !== strpos( Display::html( $order, Transfer::details( $order ) ), 'Payer par virement' ) && false !== strpos( Display::text( Transfer::details( $order ) ), 'Bénéficiaire' ) );
	if ( $locale_changed ) { restore_previous_locale(); }
} catch ( \Throwable $error ) {
	$check( 'Unhandled exception: ' . $error->getMessage() . ' at ' . $error->getFile() . ':' . $error->getLine(), false );
}
$lines[] = 'Versions: WordPress ' . get_bloginfo( 'version' ) . ', WooCommerce ' . WC_VERSION . ', PHP ' . PHP_VERSION . ', storage ' . $mode;
$lines[] = 'Result: ' . count( array_filter( $lines, static function ( $line ) { return 0 === strpos( $line, 'PASS' ); } ) ) . ' passed, ' . $failures . ' failed. No email sent.';
file_put_contents( __DIR__ . '/.test-output-' . $mode . '.txt', implode( "\n", $lines ) . "\n" );
echo implode( "\n", $lines ) . "\n";

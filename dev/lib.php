<?php
/** Test/demo helpers, never distributed. */
defined( 'ABSPATH' ) || exit;

function jeytech_otqr_dev_prepare(): void {
	add_filter( 'pre_wp_mail', '__return_true' );
	foreach ( WC()->mailer()->get_emails() as $email ) {
		add_filter( 'woocommerce_email_enabled_' . $email->id, '__return_false' );
	}
	update_option( 'woocommerce_currency', 'EUR' );
	update_option( 'woocommerce_default_country', 'FR' );
	update_option( 'woocommerce_price_num_decimals', 2 );
	update_option( 'woocommerce_bacs_accounts', array(
		array( 'account_name' => 'JeyTech Démo & Cie', 'iban' => 'FR1420041010050500013M02606', 'bic' => 'PSSTFRPPPAR', 'bank_name' => 'Demo bank', 'account_number' => '', 'sort_code' => '' ),
		array( 'account_name' => 'Second demo account', 'iban' => 'DE71110220330123456789', 'bic' => 'BHBLDEHHXXX', 'bank_name' => 'Demo bank', 'account_number' => '', 'sort_code' => '' ),
	) );
	update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Bank transfer', 'description' => 'Use the bank transfer details after ordering.' ) );
	$accounts = \JeyTech\OrderTransferQR\Accounts::all();
	update_option( \JeyTech\OrderTransferQR\Settings::OPTION, array( 'enabled' => true, 'account' => array_key_first( $accounts ), 'reference' => 'JT-{order_number}', 'show_thankyou' => true, 'show_email' => true ) );
}

function jeytech_otqr_dev_product(): \WC_Product_Simple {
	$product = new \WC_Product_Simple();
	$product->set_name( 'JeyTech demo notebook' );
	$product->set_regular_price( '19.90' );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 10 );
	$product->set_virtual( true );
	$product->set_status( 'publish' );
	$product->save();
	return $product;
}

function jeytech_otqr_dev_order( \WC_Product $product ): \WC_Order {
	$order = wc_create_order();
	$order->set_currency( 'EUR' );
	$order->set_payment_method( 'bacs' );
	$order->set_billing_first_name( 'Camille' );
	$order->set_billing_last_name( 'Demo' );
	$order->set_billing_email( 'customer@example.test' );
	$order->set_billing_country( 'FR' );
	$order->add_product( $product, 1 );
	$order->calculate_totals();
	$order->update_status( 'on-hold' );
	return $order;
}

<?php
/** Local demo fixture. This file and its MU helper never ship. */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/lib.php';
jeytech_otqr_dev_prepare();
$fr = 'fr_FR' === get_locale();
$tag = $fr ? 'fr' : 'en';
wp_mkdir_p( WPMU_PLUGIN_DIR );
file_put_contents( WPMU_PLUGIN_DIR . '/otqr-demo-mail.php', "<?php\nadd_filter('pre_wp_mail', '__return_true');\nadd_filter('woocommerce_email_enabled_customer_on_hold_order', '__return_false');\n" );
update_option( 'blogname', 'JeyTech demo shop' );
update_option( 'woocommerce_store_address', '12 rue de la Démo' );
update_option( 'woocommerce_store_city', 'Paris' );
update_option( 'woocommerce_store_postcode', '75001' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => $fr ? 'Virement bancaire' : 'Bank transfer', 'description' => $fr ? 'Les coordonnées et le QR seront affichés après la commande.' : 'Bank details and the QR appear after ordering.' ) );
update_option( 'woocommerce_email_base_color', '#c6f24e' );
update_option( 'woocommerce_email_background_color', '#0e0f11' );
update_option( 'woocommerce_email_body_background_color', '#16171a' );
update_option( 'woocommerce_email_text_color', '#f4f4f5' );
update_option( 'woocommerce_email_footer_text', 'JeyTech demo shop' );
WC_Install::create_pages();
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
$product = jeytech_otqr_dev_product();
// Browser checkout uses a non-stock virtual fixture; the functional suites independently assert stock preservation.
$product->set_manage_stock( false );
$product->save();
$order = jeytech_otqr_dev_order( $product );
$order->set_billing_address_1( '12 rue de la Démo' );
$order->set_billing_city( 'Paris' );
$order->set_billing_postcode( '75001' );
$order->save();
$classical = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Classic checkout', 'post_name' => 'classic-checkout', 'post_content' => '[woocommerce_checkout]' ) );
$details = \JeyTech\OrderTransferQR\Transfer::details( $order );
$email = WC()->mailer()->get_emails()['WC_Email_Customer_On_Hold_Order'];
$email->object = $order;
$email->placeholders['{order_number}'] = $order->get_order_number();
$email->placeholders['{order_date}'] = wc_format_datetime( $order->get_date_created() );
file_put_contents( __DIR__ . '/preview-email-' . $tag . '.html', $email->get_content_html() );
file_put_contents( __DIR__ . '/preview-email-' . $tag . '.txt', $email->get_content_plain() );
file_put_contents( __DIR__ . '/.demo-' . $tag . '.json', wp_json_encode( array(
 'locale' => get_locale(), 'orderId' => $order->get_id(), 'productId' => $product->get_id(),
 'confirmation' => $order->get_checkout_order_received_url(),
 'classicConfirmation' => get_permalink( $classical ) . 'order-received/' . $order->get_id() . '/?key=' . $order->get_order_key(),
 'checkout' => wc_get_checkout_url(), 'classicCheckout' => get_permalink( $classical ),
 'image' => \JeyTech\OrderTransferQR\ImageEndpoint::url( $order, $details ),
 'payload' => $details['payload'], 'storage' => get_option( 'woocommerce_custom_orders_table_enabled' ),
 'versions' => array( 'wp' => get_bloginfo( 'version' ), 'wc' => WC_VERSION, 'php' => PHP_VERSION )
), JSON_UNESCAPED_UNICODE ) );

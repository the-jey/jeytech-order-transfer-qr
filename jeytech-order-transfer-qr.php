<?php
/**
 * Plugin Name:          JeyTech Order Transfer QR for WooCommerce
 * Description:          Add a locally generated SEPA QR to unpaid bank transfer orders, with the amount and order reference filled in.
 * Version:              1.0.0
 * Requires at least:    6.6
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               JeyTech
 * Author URI:           https://jeytech.app
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          jeytech-order-transfer-qr
 * Domain Path:          /languages
 * WC requires at least: 9.6
 * WC tested up to:      11.1
 *
 * @package JeyTech\OrderTransferQR
 */

defined( 'ABSPATH' ) || exit;

define( 'JEYTECH_OTQR_VERSION', '1.0.0' );
define( 'JEYTECH_OTQR_FILE', __FILE__ );
define( 'JEYTECH_OTQR_PATH', plugin_dir_path( __FILE__ ) );

require_once JEYTECH_OTQR_PATH . 'src/Core/Autoloader.php';
\JeyTech\OrderTransferQR\Core\Autoloader::register( 'JeyTech\\OrderTransferQR\\', JEYTECH_OTQR_PATH . 'src/' );
\JeyTech\OrderTransferQR\Core\Autoloader::register( 'JeyTech\\OrderTransferQR\\Vendor\\BaconQrCode\\', JEYTECH_OTQR_PATH . 'vendor/BaconQrCode/' );
\JeyTech\OrderTransferQR\Core\Autoloader::register( 'JeyTech\\OrderTransferQR\\Vendor\\Enum\\', JEYTECH_OTQR_PATH . 'vendor/Enum/' );

add_action( 'before_woocommerce_init', array( \JeyTech\OrderTransferQR\Core\Compat::class, 'declare_compatibility' ) );
add_action( 'plugins_loaded', array( \JeyTech\OrderTransferQR\Plugin::class, 'boot' ) );

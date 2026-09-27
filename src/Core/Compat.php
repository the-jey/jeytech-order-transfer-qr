<?php
/**
 * WooCommerce feature compatibility declarations.
 *
 * @package JeyTech\OrderTransferQR
 */

namespace JeyTech\OrderTransferQR\Core;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Declares HPOS and Cart/Checkout Blocks compatibility, so WooCommerce does not flag the plugin.
 */
final class Compat {

	/**
	 * Hooked on `before_woocommerce_init`.
	 */
	public static function declare_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}
		FeaturesUtil::declare_compatibility( 'custom_order_tables', JEYTECH_OTQR_FILE, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', JEYTECH_OTQR_FILE, true );
	}
}

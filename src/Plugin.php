<?php
/** Plugin bootstrap. @package JeyTech\OrderTransferQR */
namespace JeyTech\OrderTransferQR;

use JeyTech\OrderTransferQR\Admin\SettingsPage;
use JeyTech\OrderTransferQR\Core\Requirements;

defined( 'ABSPATH' ) || exit;

/** Registers the runtime and administration hooks. */
final class Plugin {

	/** Starts after WooCommerce is loaded. */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'languages' ), 0 );
		if ( ! Requirements::met() ) {
			add_action( 'admin_notices', array( Requirements::class, 'notice' ) );
			return;
		}
		Display::register();
		ImageEndpoint::register();
		if ( is_admin() ) {
			SettingsPage::register();
		}
	}

	/** Registers the bundled fallback without loading translations early or overriding directory packs. */
	public static function languages(): void {
		global $wp_textdomain_registry;
		// The registry API exists since WP 6.1; older supported WP versions do not register Domain Path automatically.
		$wp_textdomain_registry->set_custom_path( 'jeytech-order-transfer-qr', dirname( JEYTECH_OTQR_FILE ) . '/languages' );
	}
}

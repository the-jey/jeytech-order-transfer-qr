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

}

<?php
/** Administration page. @package JeyTech\OrderTransferQR */
namespace JeyTech\OrderTransferQR\Admin;

use JeyTech\OrderTransferQR\Accounts;
use JeyTech\OrderTransferQR\EpcPayload;
use JeyTech\OrderTransferQR\Png;
use JeyTech\OrderTransferQR\Settings;

defined( 'ABSPATH' ) || exit;

/** Owns a scoped JeyTech interface and uses the WordPress settings API. */
final class SettingsPage {
	/** Hooks administration and capability checks. */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'option_page_capability_' . Settings::GROUP, array( self::class, 'capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( JEYTECH_OTQR_FILE ), array( self::class, 'links' ) );
	}

	/** Required capability for WordPress's nonce-protected options.php handler. */
	public static function capability(): string {
		return 'manage_woocommerce';
	}

	/** Adds a WooCommerce submenu. */
	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'JeyTech Order Transfer QR', 'Order Transfer QR', 'manage_woocommerce', 'jeytech-otqr', array( self::class, 'render' ) );
	}

	/** Registers the array option and its validator. */
	public static function settings(): void {
		register_setting( Settings::GROUP, Settings::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( Settings::class, 'sanitize' ) ) );
	}

	/** Loads styling on this page only. @param string $hook Screen hook. */
	public static function assets( string $hook ): void {
		if ( 'woocommerce_page_jeytech-otqr' === $hook ) {
			wp_enqueue_style( 'jeytech-otqr-admin', plugins_url( 'assets/admin.css', JEYTECH_OTQR_FILE ), array(), JEYTECH_OTQR_VERSION );
		}
	}

	/** Adds a settings shortcut in the plugin list. @param array $links Existing links. */
	public static function links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=jeytech-otqr' ) ) . '">' . esc_html__( 'Settings', 'jeytech-order-transfer-qr' ) . '</a>' );
		return $links;
	}

	/** Outputs the escaped form and a clearly marked sample QR. */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = Settings::get();
		$accounts = Accounts::all();
		$chosen   = $accounts[ $settings['account'] ] ?? null;
		$ready    = $settings['enabled'] && null !== $chosen && true === EpcPayload::validate_account( $chosen );
		?>
		<div class="wrap jeytech-otqr">
			<header class="jeytech-otqr-header"><p class="jeytech-otqr-brand">JeyTech / WooCommerce</p><h1>Order Transfer QR</h1><p><?php esc_html_e( 'A SEPA QR with the exact amount and order reference, generated on your own site.', 'jeytech-order-transfer-qr' ); ?></p><span class="jeytech-otqr-status"><?php echo $ready ? esc_html__( 'Enabled', 'jeytech-order-transfer-qr' ) : esc_html__( 'Setup required or disabled', 'jeytech-order-transfer-qr' ); ?></span></header>
			<?php settings_errors( Settings::OPTION ); ?>
			<div class="jeytech-otqr-grid">
				<section class="jeytech-otqr-panel">
					<h2><?php esc_html_e( 'Payment configuration', 'jeytech-order-transfer-qr' ); ?></h2>
					<form action="options.php" method="post">
						<?php settings_fields( Settings::GROUP ); ?>
						<label class="jeytech-otqr-check"><input type="checkbox" name="jeytech_otqr_settings[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Enable QR codes for unpaid bank transfer orders in EUR', 'jeytech-order-transfer-qr' ); ?></label>
						<label for="jeytech-otqr-account"><?php esc_html_e( 'Beneficiary account', 'jeytech-order-transfer-qr' ); ?></label>
						<select id="jeytech-otqr-account" name="jeytech_otqr_settings[account]">
							<option value=""><?php esc_html_e( 'Choose a WooCommerce bank account', 'jeytech-order-transfer-qr' ); ?></option>
							<?php foreach ( $accounts as $key => $account ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['account'], $key ); ?>><?php echo esc_html( $account['name'] . ' / ' . $account['iban'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Choose the account explicitly. Reordering accounts keeps your selection; changing the beneficiary, IBAN or BIC requires a new selection.', 'jeytech-order-transfer-qr' ); ?></p>
						<a class="jeytech-otqr-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bacs' ) ); ?>"><?php esc_html_e( 'Edit bank accounts in WooCommerce', 'jeytech-order-transfer-qr' ); ?></a>
						<label for="jeytech-otqr-reference"><?php esc_html_e( 'Payment reference format', 'jeytech-order-transfer-qr' ); ?></label>
						<input id="jeytech-otqr-reference" type="text" maxlength="140" name="jeytech_otqr_settings[reference]" value="<?php echo esc_attr( $settings['reference'] ); ?>">
						<p class="description"><?php esc_html_e( 'Keep {order_number}. Example: SHOP-{order_number}. The final reference is limited to 140 characters and the full QR payload to 331 bytes.', 'jeytech-order-transfer-qr' ); ?></p>
						<h3><?php esc_html_e( 'Where to display it', 'jeytech-order-transfer-qr' ); ?></h3>
						<label class="jeytech-otqr-check"><input type="checkbox" name="jeytech_otqr_settings[show_thankyou]" value="1" <?php checked( $settings['show_thankyou'] ); ?>> <?php esc_html_e( 'Order confirmation page', 'jeytech-order-transfer-qr' ); ?></label>
						<label class="jeytech-otqr-check"><input type="checkbox" name="jeytech_otqr_settings[show_email]" value="1" <?php checked( $settings['show_email'] ); ?>> <?php esc_html_e( 'Customer on-hold email', 'jeytech-order-transfer-qr' ); ?></label>
						<p class="description"><?php esc_html_e( 'Plain-text emails keep the bank details and reference, without an image. No additional email is sent.', 'jeytech-order-transfer-qr' ); ?></p>
						<?php submit_button( __( 'Save changes', 'jeytech-order-transfer-qr' ) ); ?>
					</form>
				</section>
				<aside class="jeytech-otqr-panel">
					<h2><?php esc_html_e( 'Bank account checks', 'jeytech-order-transfer-qr' ); ?></h2>
					<?php if ( empty( $accounts ) ) : ?>
						<p><?php esc_html_e( 'No bank account found. Add your beneficiary name, IBAN and BIC in WooCommerce first.', 'jeytech-order-transfer-qr' ); ?></p>
						<?php else : ?>
							<?php foreach ( $accounts as $account ) : $validation = EpcPayload::validate_account( $account ); ?>
								<div class="jeytech-otqr-account"><strong><?php echo esc_html( $account['name'] ); ?></strong><code><?php echo esc_html( $account['iban'] ); ?></code><p class="<?php echo is_wp_error( $validation ) ? 'jeytech-otqr-error' : 'jeytech-otqr-ok'; ?>"><?php echo is_wp_error( $validation ) ? esc_html( $validation->get_error_message() ) : esc_html__( 'Valid IBAN and beneficiary details', 'jeytech-order-transfer-qr' ); ?></p></div>
							<?php endforeach; ?>
						<?php endif; ?>
					<h3><?php esc_html_e( 'Before using it', 'jeytech-order-transfer-qr' ); ?></h3>
					<p><?php esc_html_e( 'Banking app support varies. Test the QR with the banks your customers use. The customer must verify the beneficiary, amount and reference before confirming.', 'jeytech-order-transfer-qr' ); ?></p>
					<p><?php esc_html_e( 'Only on-hold BACS orders in EUR receive a QR. It prepares a transfer and never marks an order as paid.', 'jeytech-order-transfer-qr' ); ?></p>
					<p><?php esc_html_e( 'Image links expire after 30 days and stop working if the order is paid, cancelled, changed or the selected bank account changes. Existing copies cached by email providers cannot be revoked.', 'jeytech-order-transfer-qr' ); ?></p>
				</aside>
			</div>
			<?php if ( null !== $chosen && true === EpcPayload::validate_account( $chosen ) ) : ?>
				<?php $payload = EpcPayload::build( $chosen, '19.90', str_replace( '{order_number}', 'DEMO-1042', $settings['reference'] ) ); ?>
				<?php if ( ! is_wp_error( $payload ) ) : ?>
					<section class="jeytech-otqr-panel jeytech-otqr-preview"><div><h2><?php esc_html_e( 'Sample QR', 'jeytech-order-transfer-qr' ); ?></h2><p><?php esc_html_e( 'Example only: EUR 19.90, reference DEMO-1042 with your format. Do not confirm this transfer.', 'jeytech-order-transfer-qr' ); ?></p></div><img src="<?php echo esc_attr( 'data:image/png;base64,' . base64_encode( Png::render( $payload ) ) ); ?>" width="246" height="246" alt="<?php esc_attr_e( 'Sample SEPA QR, not a real order', 'jeytech-order-transfer-qr' ); ?>"></section>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}

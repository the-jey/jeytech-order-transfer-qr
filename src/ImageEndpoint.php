<?php
/** Signed, revocable PNG delivery for anonymous email image viewers. @package JeyTech\OrderTransferQR */
namespace JeyTech\OrderTransferQR;

defined( 'ABSPATH' ) || exit;

/** Avoids public files in uploads and never exposes a PNG from an order ID alone. */
final class ImageEndpoint {

	/** Registers a dedicated admin-post endpoint (including anonymous mail proxies). */
	public static function register(): void {
		add_action( 'admin_post_jeytech_otqr_image', array( self::class, 'serve' ) );
		add_action( 'admin_post_nopriv_jeytech_otqr_image', array( self::class, 'serve' ) );
	}

	/** Signs the exact payment payload, order key and expiry. @param \WC_Order $order Order. @param array $details Validated details. @param int $expires Expiry. */
	private static function signature( \WC_Order $order, array $details, int $expires ): string {
		return hash_hmac( 'sha256', implode( '|', array( $order->get_id(), $expires, $order->get_order_key(), hash( 'sha256', $details['payload'] ) ) ), wp_salt( 'auth' ) );
	}

	/** Creates an image URL valid for 30 days while payment data remains unchanged. @param \WC_Order $order Order. @param array $details Validated data. */
	public static function url( \WC_Order $order, array $details ): string {
		$expires = time() + 30 * DAY_IN_SECONDS;
		return add_query_arg( array(
			'action' => 'jeytech_otqr_image', 'order_id' => $order->get_id(),
			'expires' => $expires, 'signature' => self::signature( $order, $details, $expires ),
		), admin_url( 'admin-post.php' ) );
	}

	/** Validates a bearer link against current order state and payment details. @param \WC_Order $order Order. @param int $expires Expiry. @param string $signature HMAC. */
	public static function authorize( \WC_Order $order, int $expires, string $signature ): ?array {
		if ( $expires < time() || ! preg_match( '/^[a-f0-9]{64}$/D', $signature ) ) {
			return null;
		}
		$details = Transfer::details( $order );
		return null !== $details && hash_equals( self::signature( $order, $details, $expires ), $signature ) ? $details : null;
	}

	/** Serves only a valid signed request. A session nonce would prevent email proxies from loading the image. */
	public static function serve(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Payload-bound HMAC is verified below; anonymous email image requests cannot carry a session nonce.
		$order_id  = isset( $_GET['order_id'] ) && is_scalar( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$expires   = isset( $_GET['expires'] ) && is_scalar( $_GET['expires'] ) ? absint( $_GET['expires'] ) : 0;
		$signature = isset( $_GET['signature'] ) && is_string( $_GET['signature'] ) ? sanitize_text_field( wp_unslash( $_GET['signature'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$order   = $order_id ? wc_get_order( $order_id ) : false;
		$details = $order instanceof \WC_Order ? self::authorize( $order, $expires, $signature ) : null;
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( null === $details ) {
			status_header( 403 );
			exit;
		}
		try {
			$png = Png::render( $details['payload'] );
		} catch ( \Throwable $error ) {
			status_header( 503 );
			exit;
		}
		header( 'Content-Type: image/png' );
		header( 'Content-Length: ' . strlen( $png ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PNG from the local encoder; HTML escaping corrupts image data.
		echo $png;
		exit;
	}
}

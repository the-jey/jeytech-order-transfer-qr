<?php
/** Thank-you page and customer email integration. @package JeyTech\OrderTransferQR */
namespace JeyTech\OrderTransferQR;

defined( 'ABSPATH' ) || exit;

/** Adds payment instructions without changing orders or sending additional emails. */
final class Display {

	/** Registers classic and block-compatible WooCommerce hooks. */
	public static function register(): void {
		add_action( 'woocommerce_thankyou_bacs', array( self::class, 'thankyou' ), 20 );
		add_action( 'woocommerce_email_before_order_table', array( self::class, 'email' ), 20, 4 );
	}

	/** Adds the QR after WooCommerce bank details. @param int $order_id Order ID. */
	public static function thankyou( $order_id ): void {
		if ( ! Settings::get()['show_thankyou'] ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		// WooCommerce checks page access. Also guard direct hook invocations by other templates.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Order key or authenticated ownership authorizes this read-only display.
		$key   = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$owner = $order->get_customer_id() > 0 && get_current_user_id() === $order->get_customer_id();
		if ( $order->get_customer_id() > 0 && apply_filters( 'woocommerce_order_received_verify_known_shoppers', true ) && ! $owner ) {
			return;
		}
		if ( ! $owner && ( '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) ) {
			return;
		}
		$details = Transfer::details( $order );
		if ( null !== $details ) {
			echo wp_kses_post( self::html( $order, $details ) );
		}
	}

	/** Adds instructions only to the customer's on-hold email. @param \WC_Order $order Order. @param bool $sent_to_admin Admin message. @param bool $plain_text Text message. @param object $email WooCommerce email. */
	public static function email( $order, $sent_to_admin, $plain_text, $email ): void {
		if ( ! Settings::get()['show_email'] || $sent_to_admin || ! $order instanceof \WC_Order || ! is_object( $email ) || 'customer_on_hold_order' !== ( $email->id ?? '' ) ) {
			return;
		}
		$details = Transfer::details( $order );
		if ( null === $details ) {
			return;
		}
		if ( $plain_text ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email context; all fields are single-line validated text, not HTML.
			echo self::text( $details );
		} else {
			echo wp_kses_post( self::html( $order, $details ) );
		}
	}

	/** HTML instructions show all QR payment data in readable text as required by EPC069-12. @param \WC_Order $order Order. @param array $details Data. */
	public static function html( \WC_Order $order, array $details ): string {
		$labels = array(
			__( 'Beneficiary', 'jeytech-order-transfer-qr' ) => $details['account']['name'],
			'IBAN' => $details['account']['iban'],
			__( 'Amount', 'jeytech-order-transfer-qr' ) => 'EUR ' . $details['amount'],
			__( 'Reference', 'jeytech-order-transfer-qr' ) => $details['reference'],
		);
		if ( '' !== $details['account']['bic'] ) {
			$labels['BIC'] = $details['account']['bic'];
		}
		$rows = '';
		foreach ( $labels as $label => $value ) {
			$rows .= '<tr><th scope="row" style="padding:8px 12px 8px 0;text-align:left;color:#a1a1aa;vertical-align:top;font-weight:400">' . esc_html( $label ) . '</th><td style="padding:8px 0;color:#f4f4f5;word-break:break-word">' . esc_html( $value ) . '</td></tr>';
		}
		return '<section class="jeytech-otqr-payment" style="box-sizing:border-box;background:#16171a;color:#f4f4f5;border:1px solid #2a2b30;border-radius:14px;padding:24px;margin:24px 0;max-width:680px;line-height:1.6;font-family:Arial,Helvetica,sans-serif">'
			. '<h2 style="margin:0 0 12px;color:#c6f24e;font-size:22px">' . esc_html__( 'Pay by bank transfer', 'jeytech-order-transfer-qr' ) . '</h2>'
			. '<p style="color:#f4f4f5">' . esc_html__( 'Scan this QR with a banking app that supports EPC / GiroCode, then check the details before confirming the transfer.', 'jeytech-order-transfer-qr' ) . '</p>'
			. '<img src="' . esc_url( ImageEndpoint::url( $order, $details ) ) . '" width="246" height="246" alt="' . esc_attr__( 'SEPA bank transfer QR', 'jeytech-order-transfer-qr' ) . '" style="display:block;width:246px;max-width:100%;height:auto;background:#fff;border:8px solid #fff;margin:16px 0;box-sizing:border-box">'
			. '<table style="width:100%;border:0;border-collapse:collapse;font-size:14px"><tbody>' . $rows . '</tbody></table>'
			. '<p style="color:#a1a1aa;font-size:13px;margin-bottom:0">' . esc_html__( 'You can also enter these details manually. This QR prepares the transfer; it does not confirm payment or change the order status.', 'jeytech-order-transfer-qr' ) . '</p></section>';
	}

	/** Plain-text emails keep usable bank coordinates without images or HTML entities. @param array $details Data. */
	public static function text( array $details ): string {
		$lines = array(
			'', __( 'Pay by bank transfer', 'jeytech-order-transfer-qr' ),
			__( 'Beneficiary', 'jeytech-order-transfer-qr' ) . ': ' . $details['account']['name'],
			'IBAN: ' . $details['account']['iban'],
			__( 'Amount', 'jeytech-order-transfer-qr' ) . ': EUR ' . $details['amount'],
			__( 'Reference', 'jeytech-order-transfer-qr' ) . ': ' . $details['reference'],
		);
		if ( '' !== $details['account']['bic'] ) {
			$lines[] = 'BIC: ' . $details['account']['bic'];
		}
		$lines[] = __( 'Enter these details in your banking app and check them before confirming the transfer.', 'jeytech-order-transfer-qr' );
		$lines[] = '';
		return implode( "\n", $lines ) . "\n";
	}
}

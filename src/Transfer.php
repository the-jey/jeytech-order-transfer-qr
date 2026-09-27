<?php
/** Resolves payment details for eligible orders. @package JeyTech\OrderTransferQR */
namespace JeyTech\OrderTransferQR;

defined( 'ABSPATH' ) || exit;

/** Uses WooCommerce order APIs in HPOS and classic storage. */
final class Transfer {

	/** Returns validated data, or nothing when a QR would be unsafe or unnecessary. @param \WC_Order $order Order. */
	public static function details( \WC_Order $order ): ?array {
		$settings = Settings::get();
		if ( ! $settings['enabled'] || 'bacs' !== $order->get_payment_method() || ! $order->has_status( 'on-hold' ) || 'EUR' !== $order->get_currency() ) {
			return null;
		}
		$account = Accounts::selected( (string) $settings['account'], $order->get_id() );
		if ( null === $account ) {
			return null;
		}
		$reference = str_replace( '{order_number}', (string) $order->get_order_number(), (string) $settings['reference'] );
		$payload   = EpcPayload::build( $account, (string) $order->get_total(), $reference );
		if ( is_wp_error( $payload ) ) {
			return null;
		}
		return array(
			'account' => $account, 'reference' => $reference,
			'amount' => EpcPayload::amount( (string) $order->get_total() ), 'payload' => $payload,
		);
	}
}

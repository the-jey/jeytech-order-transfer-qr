<?php
/** WooCommerce BACS accounts. @package JeyTech\OrderTransferQR */
namespace JeyTech\OrderTransferQR;

defined( 'ABSPATH' ) || exit;

/** Resolves the selected account without relying on its position in the list. */
final class Accounts {

	/** Normalizes an account without accepting non-scalar values. @param array $raw WooCommerce account. */
	public static function normalize( array $raw ): array {
		$read = static function ( string $key ) use ( $raw ): string {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( wp_strip_all_tags( (string) $raw[ $key ] ) ) : '';
		};
		return array(
			'name' => $read( 'account_name' ),
			'iban' => EpcPayload::normalize_iban( $read( 'iban' ) ),
			'bic'  => strtoupper( preg_replace( '/\s+/', '', $read( 'bic' ) ) ?? '' ),
			'bank_name' => $read( 'bank_name' ),
			'account_number' => $read( 'account_number' ),
			'sort_code' => $read( 'sort_code' ),
		);
	}

	/** Stable identity includes the beneficiary, IBAN and BIC. @param array $account Normalized account. */
	public static function key( array $account ): string {
		return hash( 'sha256', implode( "\0", array( $account['name'], $account['iban'], $account['bic'] ) ) );
	}

	/** Lists accounts; honors WooCommerce's per-order account filter. @param int $order_id Order ID (0 for settings). */
	public static function all( int $order_id = 0 ): array {
		$raw = get_option( 'woocommerce_bacs_accounts', array() );
		$raw = apply_filters( 'woocommerce_bacs_accounts', is_array( $raw ) ? $raw : array(), $order_id );
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$account = self::normalize( $item );
			$out[ self::key( $account ) ] = $account;
		}
		return $out;
	}

	/** Resolves the chosen account and final values shown by WooCommerce. @param string $key Selected identity. @param int $order_id Order ID. */
	public static function selected( string $key, int $order_id ): ?array {
		$accounts = self::all( $order_id );
		if ( ! isset( $accounts[ $key ] ) ) {
			return null;
		}
		$account = $accounts[ $key ];
		$fields  = apply_filters( 'woocommerce_bacs_account_fields', array(
			'bank_name' => array( 'label' => __( 'Bank', 'jeytech-order-transfer-qr' ), 'value' => $account['bank_name'] ),
			'account_number' => array( 'label' => __( 'Account number', 'jeytech-order-transfer-qr' ), 'value' => $account['account_number'] ),
			'sort_code' => array( 'label' => __( 'Sort code', 'jeytech-order-transfer-qr' ), 'value' => $account['sort_code'] ),
			'iban' => array( 'label' => 'IBAN', 'value' => $account['iban'] ),
			'bic'  => array( 'label' => 'BIC', 'value' => $account['bic'] ),
		), $order_id );
		// Reject altered payment details instead of contradicting the displayed bank account.
		if ( ! is_array( $fields ) || ! isset( $fields['iban']['value'], $fields['bic']['value'] ) || ! is_scalar( $fields['iban']['value'] ) || ! is_scalar( $fields['bic']['value'] ) || EpcPayload::normalize_iban( (string) $fields['iban']['value'] ) !== $account['iban'] || strtoupper( trim( (string) $fields['bic']['value'] ) ) !== $account['bic'] ) {
			return null;
		}
		return $account;
	}
}

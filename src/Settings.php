<?php
/** Plugin settings and validation. @package JeyTech\OrderTransferQR */
namespace JeyTech\OrderTransferQR;

defined( 'ABSPATH' ) || exit;

/** Stores only the QR configuration; bank coordinates remain in WooCommerce. */
final class Settings {
	const OPTION = 'jeytech_otqr_settings';
	const GROUP  = 'jeytech_otqr';

	/** Reads settings with safe defaults. */
	public static function get(): array {
		$value = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $value ) ? $value : array(), array(
			'enabled' => false, 'account' => '', 'reference' => '{order_number}',
			'show_thankyou' => true, 'show_email' => true,
		) );
	}

	/** Validates a settings submission; rejects the whole change if payment data is invalid. @param mixed $input Submitted setting. */
	public static function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return self::get();
		}
		$read = static function ( string $key ) use ( $input ): string {
			return isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
		};
		$output = array(
			'enabled'       => '1' === $read( 'enabled' ),
			'account'       => sanitize_text_field( $read( 'account' ) ),
			'reference'     => trim( wp_strip_all_tags( $read( 'reference' ) ) ),
			'show_thankyou' => '1' === $read( 'show_thankyou' ),
			'show_email'    => '1' === $read( 'show_email' ),
		);
		$error = null;
		if ( ! EpcPayload::valid_text( $output['reference'], 140 ) || false === strpos( $output['reference'], '{order_number}' ) || preg_match( '/[{}]/', str_replace( '{order_number}', '', $output['reference'] ) ) ) {
			$error = __( 'Use a single-line reference format with {order_number}, up to 140 characters. Other placeholders are not supported.', 'jeytech-order-transfer-qr' );
		}
		if ( $output['enabled'] ) {
			$accounts = Accounts::all();
			$account  = $accounts[ $output['account'] ] ?? null;
			if ( null === $account ) {
				$error = __( 'Select a bank account before enabling QR codes.', 'jeytech-order-transfer-qr' );
			} else {
				$valid = EpcPayload::validate_account( $account );
				if ( is_wp_error( $valid ) ) {
					$error = $valid->get_error_message();
				}
			}
		}
		if ( null !== $error ) {
			add_settings_error( self::OPTION, 'jeytech_otqr_invalid', $error );
			return self::get();
		}
		return $output;
	}
}

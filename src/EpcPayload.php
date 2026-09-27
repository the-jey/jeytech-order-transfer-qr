<?php
/** SEPA data validation and EPC069-12 payload. @package JeyTech\OrderTransferQR */
namespace JeyTech\OrderTransferQR;

defined( 'ABSPATH' ) || exit;

/** Builds UTF-8 EPC version 002 payloads without changing payment data. */
final class EpcPayload {

	/** IBAN lengths for the SEPA scope, including Gibraltar (EPC409-09 v8.0). */
	private const IBAN_LENGTHS = array(
		'AD' => 24, 'AL' => 28, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21,
		'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DK' => 18, 'EE' => 20, 'ES' => 24,
		'FI' => 18, 'FR' => 27, 'GB' => 22, 'GI' => 23, 'GR' => 27, 'HR' => 21,
		'HU' => 28, 'IE' => 22, 'IS' => 26, 'IT' => 27, 'LI' => 21, 'LT' => 20,
		'LU' => 20, 'LV' => 21, 'MC' => 27, 'MD' => 24, 'ME' => 22, 'MK' => 19,
		'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28, 'PT' => 25, 'RO' => 24,
		'RS' => 22, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'VA' => 22,
	);

	/** EEA beneficiaries may omit their BIC in EPC version 002. */
	private const EEA = array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK' );

	/** Normalizes spaces and casing only. @param string $iban Bank IBAN. */
	public static function normalize_iban( string $iban ): string {
		return strtoupper( preg_replace( '/\s+/', '', $iban ) ?? '' );
	}

	/** Validates country length, structure and the full mod-97 checksum. @param string $iban IBAN. */
	public static function valid_iban( string $iban ): bool {
		$iban    = self::normalize_iban( $iban );
		$country = substr( $iban, 0, 2 );
		if ( ! isset( self::IBAN_LENGTHS[ $country ] ) || strlen( $iban ) !== self::IBAN_LENGTHS[ $country ] || ! preg_match( '/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/D', $iban ) ) {
			return false;
		}
		$rearranged = substr( $iban, 4 ) . substr( $iban, 0, 4 );
		$remainder  = 0;
		foreach ( str_split( $rearranged ) as $character ) {
			$digits = ctype_digit( $character ) ? $character : (string) ( ord( $character ) - 55 );
			foreach ( str_split( $digits ) as $digit ) {
				$remainder = ( $remainder * 10 + (int) $digit ) % 97;
			}
		}
		return 1 === $remainder;
	}

	/** Counts UTF-8 characters and rejects control characters. @param string $value Field. @param int $limit Character limit. */
	public static function valid_text( string $value, int $limit ): bool {
		return '' !== $value && ! preg_match( '/[\x00-\x1F\x7F]/', $value ) && 1 === preg_match( '//u', $value ) && preg_match_all( '/./us', $value ) <= $limit;
	}

	/** Validates the selected beneficiary. @param array $account Normalized WooCommerce account. @return true|\WP_Error */
	public static function validate_account( array $account ) {
		if ( ! self::valid_iban( $account['iban'] ?? '' ) ) {
			return new \WP_Error( 'iban', __( 'Enter a valid IBAN from a SEPA country in the WooCommerce bank transfer settings.', 'jeytech-order-transfer-qr' ) );
		}
		if ( ! self::valid_text( $account['name'] ?? '', 70 ) ) {
			return new \WP_Error( 'name', __( 'The beneficiary name must contain 1 to 70 characters on one line.', 'jeytech-order-transfer-qr' ) );
		}
		$bic = $account['bic'] ?? '';
		if ( '' !== $bic && ! preg_match( '/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}(?:[A-Z0-9]{3})?$/D', $bic ) ) {
			return new \WP_Error( 'bic', __( 'The BIC must contain 8 or 11 characters.', 'jeytech-order-transfer-qr' ) );
		}
		if ( '' === $bic && ! in_array( substr( $account['iban'], 0, 2 ), self::EEA, true ) ) {
			return new \WP_Error( 'bic_required', __( 'A BIC is required for a beneficiary outside the EEA.', 'jeytech-order-transfer-qr' ) );
		}
		return true;
	}

	/** Preserves cents exactly, without float rounding. @param string $amount Order total. @return string|\WP_Error */
	public static function amount( string $amount ) {
		if ( ! preg_match( '/^([0-9]+)(?:\.([0-9]{1,2}))?$/D', $amount, $parts ) ) {
			return new \WP_Error( 'amount', __( 'The order total must be an exact euro amount with at most two decimal places.', 'jeytech-order-transfer-qr' ) );
		}
		$whole = ltrim( $parts[1], '0' );
		$whole = '' === $whole ? '0' : $whole;
		$cents = str_pad( $parts[2] ?? '', 2, '0' );
		if ( strlen( $whole ) > 9 || ( '0' === $whole && '00' === $cents ) ) {
			return new \WP_Error( 'amount_range', __( 'The amount must be between EUR 0.01 and EUR 999999999.99.', 'jeytech-order-transfer-qr' ) );
		}
		return $whole . '.' . $cents;
	}

	/** Builds a complete, bounded payment payload. @param array $account Beneficiary. @param string $amount Total. @param string $reference Reference. @return string|\WP_Error */
	public static function build( array $account, string $amount, string $reference ) {
		$valid = self::validate_account( $account );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$amount = self::amount( $amount );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}
		if ( ! self::valid_text( $reference, 140 ) ) {
			return new \WP_Error( 'reference', __( 'The payment reference must contain 1 to 140 characters on one line.', 'jeytech-order-transfer-qr' ) );
		}
		// Purpose and structured reference remain empty; only unstructured text is populated.
		$payload = implode( "\n", array( 'BCD', '002', '1', 'SCT', $account['bic'], $account['name'], $account['iban'], 'EUR' . $amount, '', '', $reference ) );
		if ( strlen( $payload ) > 331 ) {
			return new \WP_Error( 'payload_size', __( 'The payment data exceeds the 331-byte EPC limit. Shorten the reference format.', 'jeytech-order-transfer-qr' ) );
		}
		return $payload;
	}
}

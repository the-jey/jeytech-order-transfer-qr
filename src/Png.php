<?php
/** PNG rendering without GD or Imagick. @package JeyTech\OrderTransferQR */
namespace JeyTech\OrderTransferQR;

use JeyTech\OrderTransferQR\Vendor\BaconQrCode\Common\ErrorCorrectionLevel;
use JeyTech\OrderTransferQR\Vendor\BaconQrCode\Encoder\Encoder;

defined( 'ABSPATH' ) || exit;

/** Renders a black/white QR with a four-module quiet zone. */
final class Png {

	/** Encodes at correction level M and rejects QR versions above 13. @param string $payload Validated EPC data. */
	public static function render( string $payload ): string {
		if ( strlen( $payload ) > 331 ) {
			throw new \InvalidArgumentException( 'EPC payload too long.' );
		}
		$qr     = Encoder::encode( $payload, ErrorCorrectionLevel::M(), 'UTF-8', null, false );
		$matrix = $qr->getMatrix();
		$size   = $matrix->getWidth();
		if ( $size > 69 ) {
			throw new \InvalidArgumentException( 'EPC QR version above 13.' );
		}
		$scale = 6;
		$width = ( $size + 8 ) * $scale;
		$white = "\0" . str_repeat( "\xFF", $width );
		$raw   = str_repeat( $white, 4 * $scale );
		for ( $y = 0; $y < $size; ++$y ) {
			$row = "\0" . str_repeat( "\xFF", 4 * $scale );
			for ( $x = 0; $x < $size; ++$x ) {
				$row .= str_repeat( 1 === $matrix->get( $x, $y ) ? "\0" : "\xFF", $scale );
			}
			$row .= str_repeat( "\xFF", 4 * $scale );
			$raw .= str_repeat( $row, $scale );
		}
		$raw .= str_repeat( $white, 4 * $scale );
		return "\x89PNG\r\n\x1A\n" . self::chunk( 'IHDR', pack( 'NNCCCCC', $width, $width, 8, 0, 0, 0, 0 ) ) . self::chunk( 'IDAT', gzcompress( $raw, 6 ) ) . self::chunk( 'IEND', '' );
	}

	/** Writes a PNG chunk and its CRC. @param string $tag Chunk tag. @param string $data Chunk data. */
	private static function chunk( string $tag, string $data ): string {
		return pack( 'N', strlen( $data ) ) . $tag . $data . hash( 'crc32b', $tag . $data, true );
	}
}

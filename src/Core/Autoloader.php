<?php
/**
 * Minimal PSR-4 autoloader (no Composer needed at runtime).
 *
 * @package JeyTech\OrderTransferQR
 */

namespace JeyTech\OrderTransferQR\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Maps a namespace prefix to a base directory.
 */
final class Autoloader {

	/**
	 * Registers the autoloader.
	 *
	 * @param string $prefix   Namespace prefix, with trailing backslash.
	 * @param string $base_dir Directory holding the classes, with trailing slash.
	 */
	public static function register( string $prefix, string $base_dir ): void {
		spl_autoload_register(
			static function ( $class ) use ( $prefix, $base_dir ) {
				if ( 0 !== strpos( $class, $prefix ) ) {
					return;
				}
				$file = $base_dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}

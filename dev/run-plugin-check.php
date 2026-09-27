<?php
/**
 * Runs Plugin Check on the built plugin (dist/) and writes a readable report to dev/.plugin-check.txt.
 *
 * Executed by a `runPHP` blueprint step, not WP-CLI: under WP-CLI in Playground, PHP_CodeSniffer
 * tries to read php://stdin and crashes. Outside the CLI, STDIN is undefined and PHPCS skips it.
 *
 * @package JeyTech\OrderTransferQR
 */

defined( 'ABSPATH' ) || exit;

use WordPress\Plugin_Check\Checker\AJAX_Runner;
use WordPress\Plugin_Check\Checker\Check_Repository;
use WordPress\Plugin_Check\Checker\Default_Check_Repository;

$jeytech_otqr_out = __DIR__ . '/.plugin-check.txt';
$jeytech_otqr_txt = array();

try {
	// Static checks only: runtime checks need a secondary test database that Playground's SQLite refuses.
	$static = ( new Default_Check_Repository() )->get_checks( Check_Repository::TYPE_STATIC | Check_Repository::INCLUDE_EXPERIMENTAL );

	$runner = new AJAX_Runner();
	$runner->set_plugin( 'jeytech-order-transfer-qr' );
	$runner->set_experimental_flag( true );
	$runner->set_check_slugs( array_keys( $static->to_map() ) );
	$jeytech_otqr_txt[] = 'Vérifications statiques lancées : ' . implode( ', ', array_keys( $static->to_map() ) );
	$cleanup = $runner->prepare();
	$result  = $runner->run();
	$cleanup();

	foreach ( array( 'ERROR' => $result->get_errors(), 'WARNING' => $result->get_warnings() ) as $type => $files ) {
		foreach ( $files as $file => $lines ) {
			foreach ( $lines as $line => $columns ) {
				foreach ( $columns as $column => $messages ) {
					foreach ( $messages as $message ) {
						$jeytech_otqr_txt[] = sprintf( '%-7s %s:%d:%d  [%s] %s', $type, $file, $line, $column, $message['code'] ?? '', wp_strip_all_tags( $message['message'] ?? '' ) );
					}
				}
			}
		}
	}
	$jeytech_otqr_summary = sprintf( 'Plugin Check — %d erreur(s), %d avertissement(s)', $result->get_error_count(), $result->get_warning_count() );
} catch ( \Throwable $e ) {
	$jeytech_otqr_summary = 'Plugin Check — exception : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')';
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
file_put_contents( $jeytech_otqr_out, $jeytech_otqr_summary . "\n" . implode( "\n", $jeytech_otqr_txt ) . "\n" );

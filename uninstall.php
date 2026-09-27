<?php
/** Removes only this plugin's configuration. @package JeyTech\OrderTransferQR */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'jeytech_otqr_settings' );

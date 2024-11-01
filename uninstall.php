<?php
// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}
// Delete all meta
global $wpdb;
$wpdb->query("DELETE FROM " . $wpdb->postmeta . " WHERE meta_key = 'wcag_validate'");
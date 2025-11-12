<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hamnaghsheh_files" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hamnaghsheh_projects" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hamnaghsheh_user_limits" );

// Remove uploads folder - optional, be cautious
$upload_dir = wp_upload_dir()['basedir'] . '/hamnaghsheh/';
if ( is_dir( $upload_dir ) ) {
    // Recursive delete (use with care)
    $it = new RecursiveDirectoryIterator( $upload_dir, RecursiveDirectoryIterator::SKIP_DOTS );
    $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $files as $file ) {
        if ( $file->isDir() ) rmdir( $file->getRealPath() );
        else unlink( $file->getRealPath() );
    }
    @rmdir( $upload_dir );
}

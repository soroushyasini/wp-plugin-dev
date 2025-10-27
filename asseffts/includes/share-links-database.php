<?php
/**
 * Project Share Links Database Schema
 * Add this to your includes/projects-database.php or create as separate file
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create share links table
 * Call this function during plugin activation
 */
function ufs_create_share_links_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Project share links table
    $share_links_table = $wpdb->prefix . 'project_share_links';
    $sql_share_links = "CREATE TABLE $share_links_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        project_id mediumint(9) NOT NULL,
        share_token varchar(64) NOT NULL,
        created_by mediumint(9) NOT NULL,
        permission_level enum('view','download','upload','manage') DEFAULT 'view',
        max_uses int DEFAULT NULL,
        current_uses int DEFAULT 0,
        expires_at datetime DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY share_token (share_token),
        KEY project_id (project_id),
        KEY created_by (created_by)
    ) $charset_collate;";

    // Share link usage tracking table
    $share_link_usage_table = $wpdb->prefix . 'share_link_usage';
    $sql_usage = "CREATE TABLE $share_link_usage_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        share_link_id mediumint(9) NOT NULL,
        user_id mediumint(9) DEFAULT NULL,
        ip_address varchar(45),
        user_agent varchar(255),
        accessed_at datetime DEFAULT CURRENT_TIMESTAMP,
        was_new_user tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        KEY share_link_id (share_link_id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_share_links);
    dbDelta($sql_usage);
}

// Hook this into your main activation function
// Add to ufs_activate_plugin() in main plugin file:
// ufs_create_share_links_table();
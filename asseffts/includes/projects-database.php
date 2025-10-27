<?php
/**
 * Projects Database Schema
 * Add this file to your plugin and include it in main plugin file
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create projects-related database tables
 * Call this function on plugin activation OR add it to your existing ufs_create_database_tables()
 */
function ufs_create_projects_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Projects table
    $projects_table = $wpdb->prefix . 'user_projects';
    $sql_projects = "CREATE TABLE $projects_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        project_name varchar(255) NOT NULL,
        project_description text,
        owner_id mediumint(9) NOT NULL,
        status enum('active','completed','archived') DEFAULT 'active',
        project_type varchar(100),
        created_date datetime DEFAULT CURRENT_TIMESTAMP,
        updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY owner_id (owner_id),
        KEY status (status)
    ) $charset_collate;";
    
    // Project members table (for sharing projects)
    $members_table = $wpdb->prefix . 'project_members';
    $sql_members = "CREATE TABLE $members_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        project_id mediumint(9) NOT NULL,
        user_id mediumint(9) NOT NULL,
        role varchar(50) DEFAULT 'member',
        permission_level enum('view','download','upload','manage') DEFAULT 'view',
        added_by mediumint(9) NOT NULL,
        added_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY project_id (project_id),
        KEY user_id (user_id),
        UNIQUE KEY unique_member (project_id, user_id)
    ) $charset_collate;";
    
    // Project folders table
    $folders_table = $wpdb->prefix . 'project_folders';
    $sql_folders = "CREATE TABLE $folders_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        project_id mediumint(9) NOT NULL,
        folder_name varchar(255) NOT NULL,
        parent_folder_id mediumint(9) DEFAULT NULL,
        created_by mediumint(9) NOT NULL,
        created_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY project_id (project_id),
        KEY parent_folder_id (parent_folder_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_projects);
    dbDelta($sql_members);
    dbDelta($sql_folders);
}
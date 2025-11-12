<?php
if (!defined('ABSPATH'))
    exit;

class Hamnaghsheh_Activator
{

    public static function activate()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $projects_table = $wpdb->prefix . 'hamnaghsheh_projects';
        $files_table = $wpdb->prefix . 'hamnaghsheh_files';
        $users_table = $wpdb->prefix . 'hamnaghsheh_users';
        $shares_table = $wpdb->prefix . 'hamnaghsheh_shares';
        $assignments_table = $wpdb->prefix . 'hamnaghsheh_project_assignments';
        $file_logs_table  = $wpdb->prefix . 'hamnaghsheh_file_logs';

        $current_db_version = '2.9';
        $installed_db_version = get_option('hamnaghsheh_db_version');

        if ($installed_db_version !== $current_db_version) {

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $sql2 = "CREATE TABLE {$projects_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                type ENUM('residential','commercial','renovation','infrastructure') DEFAULT 'residential',
                status ENUM('active','completed','archived') DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                share_token VARCHAR(100) DEFAULT NULL,
                archive TINYINT(1) DEFAULT 0,
                permission ENUM('view','download') DEFAULT 'view',
                PRIMARY KEY (id),
                KEY user_id (user_id)
            ) {$charset_collate};";

            $sql3 = "CREATE TABLE {$files_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                project_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path TEXT NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL,
                file_type VARCHAR(50),
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY project_id (project_id),
                KEY user_id (user_id)
            ) {$charset_collate};";

            $sql4 = "CREATE TABLE {$users_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                username VARCHAR(255) NOT NULL,
                display_name VARCHAR(255) DEFAULT NULL,
                email VARCHAR(255) DEFAULT NULL,
                active TINYINT(1) DEFAULT 1,
                storage_limit  BIGINT UNSIGNED DEFAULT 52428800,
                access_level ENUM('free', 'premium') DEFAULT 'free',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};";

            $sql5 = "CREATE TABLE $shares_table (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                owner_id BIGINT UNSIGNED NOT NULL,
                token VARCHAR(64) NOT NULL,
                permission ENUM('view', 'upload') DEFAULT 'view',
                expires_at DATETIME NULL,
                usage_limit INT DEFAULT 0,
                usage_count INT DEFAULT 0,
                is_guest TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $charset_collate;";


            $sql6 = "CREATE TABLE $assignments_table (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                project_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                permission ENUM('view', 'upload') DEFAULT 'view',
                assigned_by BIGINT UNSIGNED NULL,
                assigned_via_token VARCHAR(64) NULL,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_assignment (project_id, user_id)
            ) $charset_collate;";

             $sql7 = "CREATE TABLE $file_logs_table (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                file_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                action_type ENUM('upload', 'replace', 'delete') NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";

            dbDelta($sql2);
            dbDelta($sql3);
            dbDelta($sql4);
            dbDelta($sql5);
            dbDelta($sql6);
            dbDelta($sql7);

            wp_mkdir_p(HAMNAGHSHEH_UPLOAD_DIR);

            update_option('hamnaghsheh_db_version', $current_db_version);
        }
    }
}

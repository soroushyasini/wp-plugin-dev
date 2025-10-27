<?php
/**
 * Activity Log System
 */

if (!defined('ABSPATH')) {
    exit;
}

class UFS_Activity_Log {
    
    public function __construct() {
        $this->create_activity_table();
        $this->init_hooks();
    }
    
    /**
     * Create activity log table
     */
    private function create_activity_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'user_file_activity_log';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            target_user_id mediumint(9) DEFAULT NULL,
            project_id mediumint(9) DEFAULT NULL,
            file_id mediumint(9) DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            action_details text,
            ip_address varchar(45),
            user_agent varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY project_id (project_id),
            KEY file_id (file_id),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create view tracking table
        $view_table = $wpdb->prefix . 'user_file_views';
        
        $sql_views = "CREATE TABLE IF NOT EXISTS $view_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_id mediumint(9) NOT NULL,
            project_id mediumint(9) NOT NULL,
            user_id mediumint(9) NOT NULL,
            first_viewed datetime DEFAULT CURRENT_TIMESTAMP,
            last_viewed datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            view_count int DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY unique_view (file_id, user_id),
            KEY project_id (project_id)
        ) $charset_collate;";
        
        dbDelta($sql_views);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Track project shares
        add_action('wp_ajax_share_project', array($this, 'track_project_share'), 5);
        
        // Track file uploads
        add_action('wp_ajax_upload_user_file', array($this, 'track_file_upload'), 99);
        
        // Track file views
        add_action('wp_ajax_view_file', array($this, 'track_file_view'));
        add_action('wp_ajax_get_project_details', array($this, 'track_project_view'), 5);
        
        // AJAX endpoints
        add_action('wp_ajax_get_activity_log', array($this, 'get_activity_log'));
        add_action('wp_ajax_get_file_statistics', array($this, 'get_file_statistics'));
    }
    
    /**
     * Log an activity
     */
    public static function log_activity($user_id, $action_type, $details = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'user_file_activity_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'target_user_id' => $details['target_user_id'] ?? null,
                'project_id' => $details['project_id'] ?? null,
                'file_id' => $details['file_id'] ?? null,
                'action_type' => $action_type,
                'action_details' => isset($details['details']) ? json_encode($details['details']) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Track project share
     */
    public function track_project_share() {
        if (isset($_POST['project_id']) && isset($_POST['target_user_id'])) {
            $project_id = intval($_POST['project_id']);
            $target_user_id = intval($_POST['target_user_id']);
            $permission = sanitize_text_field($_POST['permission'] ?? 'view');
            $role = sanitize_text_field($_POST['role'] ?? 'member');
            
            self::log_activity(get_current_user_id(), 'project_shared', array(
                'project_id' => $project_id,
                'target_user_id' => $target_user_id,
                'details' => array(
                    'permission' => $permission,
                    'role' => $role
                )
            ));
        }
    }
    
    /**
     * Track file upload
     */
    public function track_file_upload() {
        // This runs after successful upload
        // We'll hook into it from the main upload function
    }
    
    /**
     * Track file view
     */
    public function track_file_view() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('view_file', 'nonce');
        
        $file_id = intval($_POST['file_id']);
        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $views_table = $wpdb->prefix . 'user_file_views';
        
        // Check if already viewed
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $views_table WHERE file_id = %d AND user_id = %d",
            $file_id,
            $user_id
        ));
        
        if ($existing) {
            // Update view count and last viewed
            $wpdb->query($wpdb->prepare(
                "UPDATE $views_table SET view_count = view_count + 1, last_viewed = NOW() 
                 WHERE file_id = %d AND user_id = %d",
                $file_id,
                $user_id
            ));
        } else {
            // Insert new view record
            $wpdb->insert(
                $views_table,
                array(
                    'file_id' => $file_id,
                    'project_id' => $project_id,
                    'user_id' => $user_id
                ),
                array('%d', '%d', '%d')
            );
        }
        
        // Log activity
        self::log_activity($user_id, 'file_viewed', array(
            'file_id' => $file_id,
            'project_id' => $project_id
        ));
        
        wp_send_json_success('View tracked');
    }
    
    /**
     * Track project view
     */
    public function track_project_view() {
        if (isset($_POST['project_id'])) {
            $project_id = intval($_POST['project_id']);
            
            self::log_activity(get_current_user_id(), 'project_viewed', array(
                'project_id' => $project_id
            ));
        }
    }
    
    /**
     * Get activity log
     */
    public function get_activity_log() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('get_activity_log', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $user_id = get_current_user_id();
        
        global $wpdb;
        $log_table = $wpdb->prefix . 'user_file_activity_log';
        $users_table = $wpdb->users;
        $projects_table = $wpdb->prefix . 'user_projects';
        $files_table = $wpdb->prefix . 'user_files';
        
        $query = "SELECT 
                    l.*,
                    u.display_name as user_name,
                    tu.display_name as target_user_name,
                    p.project_name,
                    f.file_name
                  FROM $log_table l
                  LEFT JOIN $users_table u ON l.user_id = u.ID
                  LEFT JOIN $users_table tu ON l.target_user_id = tu.ID
                  LEFT JOIN $projects_table p ON l.project_id = p.id
                  LEFT JOIN $files_table f ON l.file_id = f.id
                  WHERE 1=1";
        
        $params = array();
        
        if ($project_id) {
            $query .= " AND l.project_id = %d";
            $params[] = $project_id;
        } else {
            // Show only user's activities or activities in their projects
            $query .= " AND (l.user_id = %d OR l.target_user_id = %d 
                         OR l.project_id IN (
                            SELECT id FROM $projects_table WHERE owner_id = %d
                            UNION
                            SELECT project_id FROM {$wpdb->prefix}project_members WHERE user_id = %d
                         ))";
            $params[] = $user_id;
            $params[] = $user_id;
            $params[] = $user_id;
            $params[] = $user_id;
        }
        
        $query .= " ORDER BY l.created_at DESC LIMIT %d";
        $params[] = $limit;
        
        $activities = $wpdb->get_results($wpdb->prepare($query, $params));
        
        wp_send_json_success(array('activities' => $activities));
    }
    
    /**
     * Get file statistics
     */
    public function get_file_statistics() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('get_file_statistics', 'nonce');
        
        $file_id = intval($_POST['file_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $views_table = $wpdb->prefix . 'user_file_views';
        $log_table = $wpdb->prefix . 'user_file_activity_log';
        $files_table = $wpdb->prefix . 'user_files';
        
        // Verify user has access to this file
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT f.*, p.owner_id 
             FROM $files_table f
             LEFT JOIN {$wpdb->prefix}user_projects p ON f.project_id = p.id
             WHERE f.id = %d",
            $file_id
        ));
        
        if (!$file) {
            wp_send_json_error('File not found');
        }
        
        // Check if user owns the file or has access to the project
        $has_access = false;
        if ($file->user_id == $user_id || $file->owner_id == $user_id) {
            $has_access = true;
        } else {
            // Check if user is project member
            $is_member = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}project_members 
                 WHERE project_id = %d AND user_id = %d",
                $file->project_id,
                $user_id
            ));
            if ($is_member > 0) {
                $has_access = true;
            }
        }
        
        if (!$has_access) {
            wp_send_json_error('No access to this file');
        }
        
        // Get view statistics
        $views = $wpdb->get_results($wpdb->prepare("
            SELECT v.*, u.display_name, u.user_email
            FROM $views_table v
            JOIN {$wpdb->users} u ON v.user_id = u.ID
            WHERE v.file_id = %d
            ORDER BY v.last_viewed DESC
        ", $file_id));
        
        // Get total views
        $total_views = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(view_count), 0) FROM $views_table WHERE file_id = %d
        ", $file_id));
        
        // Get unique viewers
        $unique_viewers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id) FROM $views_table WHERE file_id = %d
        ", $file_id));
        
        // Get download count from activity log
        $downloads = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $log_table 
            WHERE file_id = %d AND action_type = 'file_downloaded'
        ", $file_id));
        
        // Get recent activity
        $recent_activity = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, u.display_name
            FROM $log_table l
            JOIN {$wpdb->users} u ON l.user_id = u.ID
            WHERE l.file_id = %d
            ORDER BY l.created_at DESC
            LIMIT 10
        ", $file_id));
        
        wp_send_json_success(array(
            'views' => $views,
            'total_views' => $total_views,
            'unique_viewers' => $unique_viewers,
            'downloads' => $downloads,
            'recent_activity' => $recent_activity
        ));
    }
}

new UFS_Activity_Log();
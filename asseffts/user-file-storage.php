<?php
/**
 * Plugin Name: User File Storage System
 * Plugin URI: https://hamnaghsheh.ir
 * Description: Custom file storage system for WooCommerce users with sharing capabilities. Users can upload files, manage storage quotas, and share files with other users.
 * Version: 2.0.0
 * version 1 : Sep 28 2025, version 2 : oct 7 2025
 * Author: Soroush Yasini
 * Author URI: https://hamnaghsheh.ir
 * Telegram ID : @Romanlegioner
 * Text Domain: user-file-storage
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UFS_VERSION', '1.0.0');
define('UFS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UFS_PLUGIN_PATH', plugin_dir_path(__FILE__));

/// project management update :
// Include project management modules
// Include project management modules
require_once(UFS_PLUGIN_PATH . 'includes/projects-database.php');
require_once(UFS_PLUGIN_PATH . 'includes/class-projects-manager.php');
require_once(UFS_PLUGIN_PATH . 'includes/projects-frontend.php');
require_once(UFS_PLUGIN_PATH . 'includes/remove-old-interface.php');
require_once(UFS_PLUGIN_PATH . 'includes/storage-dashboard.php');
require_once(UFS_PLUGIN_PATH . 'includes/activity-log.php');
require_once(UFS_PLUGIN_PATH . 'includes/share-links-database.php');
require_once(UFS_PLUGIN_PATH . 'includes/class-share-links-manager.php');


///---///

/**
 * Plugin activation hook - ONLY runs when plugin is activated
 */
register_activation_hook(__FILE__, 'ufs_activate_plugin');
function ufs_activate_plugin() {
    ufs_create_database_tables();
    ufs_set_default_options();
    ufs_create_share_links_table(); // ADD THIS
    flush_rewrite_rules();
    
    //// project managerment update :
    ufs_create_projects_tables();
  //  ufs_create_share_links_table();

    ///---///
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'ufs_deactivate_plugin');
function ufs_deactivate_plugin() {
    flush_rewrite_rules();
}

/**
 * Create database tables - ONLY runs on plugin activation
 */
function ufs_create_database_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // User files table
    $files_table = $wpdb->prefix . 'user_files';
    // User files table - project manamgent update
    $sql_files = "CREATE TABLE $files_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        project_id mediumint(9) DEFAULT NULL,
        folder_id mediumint(9) DEFAULT NULL,
        file_name varchar(255) NOT NULL,
        file_path varchar(500) NOT NULL,
        file_size bigint(20) NOT NULL,
        mime_type varchar(100) NOT NULL,
        upload_date datetime DEFAULT CURRENT_TIMESTAMP,
        is_public tinyint(1) DEFAULT 0,
        download_count int DEFAULT 0,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY project_id (project_id),
        KEY folder_id (folder_id)
    ) $charset_collate;";
        
    // File permissions table for sharing
    $permissions_table = $wpdb->prefix . 'user_file_permissions';
    $sql_permissions = "CREATE TABLE $permissions_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        file_id mediumint(9) NOT NULL,
        user_id mediumint(9) NOT NULL,
        permission_type enum('view','download','edit') DEFAULT 'view',
        granted_by mediumint(9) NOT NULL,
        granted_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY file_id (file_id),
        KEY user_id (user_id),
        UNIQUE KEY unique_permission (file_id, user_id, permission_type)
    ) $charset_collate;";
    
    // User storage quotas table
    $quotas_table = $wpdb->prefix . 'user_storage_quotas';
    $sql_quotas = "CREATE TABLE $quotas_table (
        user_id mediumint(9) NOT NULL,
        quota_limit bigint(20) DEFAULT 104857600,
        used_space bigint(20) DEFAULT 0,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_files);
    dbDelta($sql_permissions);
    dbDelta($sql_quotas);
    
    // Create upload directory
    $upload_dir = wp_upload_dir();
    $user_storage_dir = $upload_dir['basedir'] . '/user-storage';
    if (!file_exists($user_storage_dir)) {
        wp_mkdir_p($user_storage_dir);
        // Add .htaccess for security
        file_put_contents($user_storage_dir . '/.htaccess', 'deny from all');
    }
}

/**
 * Set default plugin options - ONLY runs on plugin activation
 */
function ufs_set_default_options() {
    if (!get_option('ufs_max_file_size')) {
        update_option('ufs_max_file_size', 10);
    }
    if (!get_option('ufs_default_quota')) {
        update_option('ufs_default_quota', 100);
    }
    if (!get_option('ufs_allowed_types')) {
        update_option('ufs_allowed_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip,dwg,dwf,aci,dxf');
    }
}

/**
 * Main Plugin Class
 */
class UserFileStorage {
    
    private $upload_dir;
    private $max_file_size;
    private $allowed_types;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/user-storage';
        $this->max_file_size = get_option('ufs_max_file_size', 10) * 1048576; // Convert MB to bytes
        $this->allowed_types = explode(',', get_option('ufs_allowed_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip'));
        
        // Initialize hooks
        $this->init_hooks();
        
        /// update project managent
        new UFS_Projects_Manager();
        ///---///
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_upload_user_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_delete_user_file', array($this, 'delete_user_file'));
        add_action('wp_ajax_share_user_file', array($this, 'share_file'));
        add_action('wp_ajax_download_user_file', array($this, 'download_file'));
        add_action('wp_ajax_get_user_files', array($this, 'get_user_files'));
        add_action('wp_ajax_search_users', array($this, 'search_users'));
        add_action('wp_ajax_get_file_shares', array($this, 'get_file_shares'));
        add_action('wp_ajax_remove_file_share', array($this, 'remove_file_share'));
        
        // WooCommerce integration
        add_action('init', array($this, 'add_file_storage_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_file_storage_menu_item'));
        add_action('woocommerce_account_file-storage_endpoint', array($this, 'file_storage_content'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Admin functionality
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    /// managent plugin:
    private function get_user_project_permission_level($user_id, $project_id) {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'user_projects';
    $members_table = $wpdb->prefix . 'project_members';
    
    $is_owner = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $projects_table WHERE id = %d AND owner_id = %d",
        $project_id, $user_id
    ));
    
    if ($is_owner) return 'manage';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT permission_level FROM $members_table WHERE project_id = %d AND user_id = %d",
        $project_id, $user_id
    )) ?: 'none';
    }
///
    
    /**
     * Add WooCommerce endpoint for file storage
     */
    public function add_file_storage_endpoint() {
        add_rewrite_endpoint('file-storage', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add file storage menu item to WooCommerce My Account
     */
    public function add_file_storage_menu_item($items) {
        // Insert after dashboard but before logout
        $new_items = array();
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            if ($key === 'dashboard') {
                $new_items['file-storage'] = __('My Files', 'user-file-storage');
            }
        }
        return $new_items;
    }
    
    /**
     * Enqueue scripts and styles
     */
public function enqueue_scripts() {
    if (is_account_page() || is_page()) {
        wp_enqueue_script('jquery');
        
        // Create base nonces array
        $nonces = array(
            'upload' => wp_create_nonce('user_file_upload'),
            'delete' => wp_create_nonce('delete_user_file'),
            'download' => wp_create_nonce('download_user_file'),
            'get_files' => wp_create_nonce('get_user_files'),
            'share_file' => wp_create_nonce('share_file'),
            'search_users' => wp_create_nonce('search_users'),
            'get_file_shares' => wp_create_nonce('get_file_shares'),
            'remove_file_share' => wp_create_nonce('remove_file_share'),
            'create_project' => wp_create_nonce('create_project'),
            'get_user_projects' => wp_create_nonce('get_user_projects'),
            'get_project_details' => wp_create_nonce('get_project_details'),
            'update_project' => wp_create_nonce('update_project'),
            'delete_project' => wp_create_nonce('delete_project'),
            'share_project' => wp_create_nonce('share_project'),
            'get_project_members' => wp_create_nonce('get_project_members'),
            'remove_project_member' => wp_create_nonce('remove_project_member'),
            'create_folder' => wp_create_nonce('create_folder'),
            'get_project_folders' => wp_create_nonce('get_project_folders'),
            'delete_folder' => wp_create_nonce('delete_folder'),
            'get_storage_stats' => wp_create_nonce('get_storage_stats'),
            'get_activity_log' => wp_create_nonce('get_activity_log'),
            'get_file_statistics' => wp_create_nonce('get_file_statistics'),
            'view_file' => wp_create_nonce('view_file'),
            'generate_share_link' => wp_create_nonce('generate_share_link'),
            'get_project_share_links' => wp_create_nonce('get_project_share_links'),
            'revoke_share_link' => wp_create_nonce('revoke_share_link')
        );
        
        // Localize script for AJAX
        wp_localize_script('jquery', 'ufs_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => $nonces
        ));
        
        // Add inline JavaScript
        add_action('wp_footer', array($this, 'add_inline_javascript'));
    }
}
    
    /**
     * Handle file upload via AJAX
     */
    public function handle_file_upload() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('user_file_upload', 'nonce');
        
        $user_id = get_current_user_id();
        $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
        $folder_id = !empty($_POST['folder_id']) ? intval($_POST['folder_id']) : null;
        
        // Validate project access if project_id is provided
        if ($project_id) {
            $projects_manager = new UFS_Projects_Manager();
            $permission = $this->get_user_project_permission_level($user_id, $project_id);
            
            if (!in_array($permission, array('upload', 'manage'))) {
                wp_send_json_error('No permission to upload to this project');
            }
        } else {
            wp_send_json_error('Project is required for file upload');
        }
        
        if (empty($_FILES['user_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['user_file'];
        
        // Validate file
        if (!$this->validate_file($file)) {
            wp_send_json_error('Invalid file type or size');
        }
        
        // Check storage quota
        if (!$this->check_storage_quota($user_id, $file['size'])) {
            wp_send_json_error('Storage quota exceeded');
        }
        
        // Create user directory
        $user_dir = $this->upload_dir . '/' . $user_id;
        if ($project_id) {
            $user_dir .= '/project_' . $project_id;
        }
        if (!file_exists($user_dir)) {
            wp_mkdir_p($user_dir);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
        $unique_filename = $filename . '_' . time() . '.' . $file_extension;
        $file_path = $user_dir . '/' . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Save to database with project_id and folder_id
            $file_id = $this->save_file_to_db($user_id, $file['name'], $file_path, $file['size'], $file['type'], $project_id, $folder_id);
            // Log file upload activity
            UFS_Activity_Log::log_activity($user_id, 'file_uploaded', array(
                'file_id' => $file_id,
                'project_id' => $project_id,
                'details' => array('filename' => $filename)
            ));
            if ($file_id) {
                // Update storage quota
                $this->update_storage_usage($user_id, $file['size']);
                wp_send_json_success(array(
                    'file_id' => $file_id, 
                    'message' => 'File uploaded successfully'
                ));
            } else {
                unlink($file_path);
                wp_send_json_error('Database error');
            }
        } else {
            wp_send_json_error('Upload failed');
        }
    }
    
    /**
     * Validate uploaded file
     */
private function validate_file($file) {
    // Check file size
    if ($file['size'] > $this->max_file_size) {
        return false;
    }
    
    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $this->allowed_types)) {
        return false;
    }
    
    // Additional MIME type check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = array(
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf', 'text/plain',
        'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        // AutoCAD file types
        'application/acad',
        'application/x-acad',
        'application/autocad_dwg',
        'image/x-dwg',
        'application/dwg',
        'application/x-dwg',
        'application/x-autocad',
        'image/vnd.dwg',
        'drawing/x-dwf',
        'application/x-dwf',
        // DXF files
        'application/dxf',
        'application/x-dxf',
        'image/vnd.dxf',
        'image/x-dxf',
        // Generic fallback for binary CAD files
        'application/octet-stream'
    );
    
    // For CAD files, MIME type detection can be unreliable
    // So we'll be more lenient if the extension is correct
    if (in_array($file_extension, array('dwg', 'dwf', 'aci', 'dxf'))) {
        // Accept if MIME is in allowed list OR is generic binary
        return in_array($mime_type, $allowed_mimes) || 
               $mime_type === 'application/octet-stream';
    }
    
    return in_array($mime_type, $allowed_mimes);
}
    
    /**
     * Check if user has enough storage quota
     */
    private function check_storage_quota($user_id, $file_size) {
        global $wpdb;
        
        $quota_table = $wpdb->prefix . 'user_storage_quotas';
        
        // Get or create user quota
        $quota = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $quota_table WHERE user_id = %d",
            $user_id
        ));
        
        if (!$quota) {
            // Create default quota
            $default_quota = get_option('ufs_default_quota', 100) * 1048576; // Convert MB to bytes
            $wpdb->insert($quota_table, array(
                'user_id' => $user_id,
                'quota_limit' => $default_quota,
                'used_space' => 0
            ));
            $quota = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $quota_table WHERE user_id = %d",
                $user_id
            ));
        }
        
        return ($quota->used_space + $file_size) <= $quota->quota_limit;
    }
    
    /**
     * Save file information to database
     */
     /// project managemnt !~
    private function save_file_to_db($user_id, $filename, $filepath, $filesize, $mimetype, $project_id = null, $folder_id = null) {
        global $wpdb;
        
        $files_table = $wpdb->prefix . 'user_files';
        
        $result = $wpdb->insert(
            $files_table,
            array(
                'user_id' => $user_id,
                'project_id' => $project_id,
                'folder_id' => $folder_id,
                'file_name' => $filename,
                'file_path' => $filepath,
                'file_size' => $filesize,
                'mime_type' => $mimetype
            ),
            array('%d', '%d', '%d', '%s', '%s', '%d', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
        
    /**
     * Update user storage usage
     */
    private function update_storage_usage($user_id, $size_change) {
        global $wpdb;
        
        $quota_table = $wpdb->prefix . 'user_storage_quotas';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $quota_table SET used_space = used_space + %d WHERE user_id = %d",
            $size_change,
            $user_id
        ));
    }
    
    /**
     * Display file storage content in WooCommerce My Account
     */
    public function file_storage_content() {
        if (!is_user_logged_in()) {
            echo '<p>Please log in to access your files.</p>';
            return;
        }
        
        $user_id = get_current_user_id();
        $storage_info = $this->get_storage_info($user_id);
        $max_file_size_mb = get_option('ufs_max_file_size', 10);
        $allowed_types = get_option('ufs_allowed_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip');
        
        ?>
        <div id="user-file-storage">
            <h3><?php _e('My File Storage', 'user-file-storage'); ?></h3>
            
            <!-- Storage Usage -->
            <div class="storage-info">
                <p><strong><?php _e('Storage Used:', 'user-file-storage'); ?></strong> 
                    <?php echo $this->format_bytes($storage_info->used_space); ?> / 
                    <?php echo $this->format_bytes($storage_info->quota_limit); ?>
                </p>
                <div class="storage-bar">
                    <?php 
                    $percentage = ($storage_info->quota_limit > 0) ? 
                        ($storage_info->used_space / $storage_info->quota_limit) * 100 : 0;
                    ?>
                    <div class="storage-progress" style="width: <?php echo min($percentage, 100); ?>%"></div>
                </div>
            </div>
            
            <!-- Upload Form -->
            <div class="file-upload-section">
                <h4><?php _e('Upload New File', 'user-file-storage'); ?></h4>
                <form id="file-upload-form" enctype="multipart/form-data">
                    <?php wp_nonce_field('user_file_upload', 'nonce'); ?>
                    <input type="file" id="user-file-input" name="user_file" accept=".<?php echo str_replace(',', ',.', $allowed_types); ?>">
                    <button type="submit"><?php _e('Upload File', 'user-file-storage'); ?></button>
                    <div id="upload-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <span class="progress-text">0%</span>
                    </div>
                </form>
                <p class="upload-info">
                    <small>
                        <?php printf(
                            __('Max file size: %sMB. Allowed types: %s', 'user-file-storage'),
                            $max_file_size_mb,
                            strtoupper($allowed_types)
                        ); ?>
                    </small>
                </p>
            </div>
            
            <!-- Files List -->
            <div class="files-section">
                <h4><?php _e('My Files', 'user-file-storage'); ?></h4>
                <div class="files-toolbar">
                    <input type="text" id="search-files" placeholder="<?php _e('Search files...', 'user-file-storage'); ?>">
                    <select id="filter-files">
                        <option value=""><?php _e('All Files', 'user-file-storage'); ?></option>
                        <option value="image"><?php _e('Images', 'user-file-storage'); ?></option>
                        <option value="document"><?php _e('Documents', 'user-file-storage'); ?></option>
                        <option value="archive"><?php _e('Archives', 'user-file-storage'); ?></option>
                    </select>
                </div>
                <div id="files-list">
                    <p><?php _e('Loading files...', 'user-file-storage'); ?></p>
                </div>
            </div>
            
            <!-- Share Modal -->
            <div id="share-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h4><?php _e('Share File', 'user-file-storage'); ?></h4>
                    <form id="share-file-form">
                        <input type="hidden" id="share-file-id">
                        <input type="hidden" id="selected-user-id">
                        
                        <label for="share-user-search"><?php _e('Search Users:', 'user-file-storage'); ?></label>
                        <input type="text" id="share-user-search" placeholder="<?php _e('Type username or email...', 'user-file-storage'); ?>">
                        <div id="user-search-results"></div>
                        
                        <label for="permission-type"><?php _e('Permission Level:', 'user-file-storage'); ?></label>
                        <select id="permission-type">
                            <option value="view"><?php _e('View Only', 'user-file-storage'); ?></option>
                            <option value="download"><?php _e('View & Download', 'user-file-storage'); ?></option>
                        </select>
                        
                        <div class="shared-users">
                            <h5><?php _e('Currently Shared With:', 'user-file-storage'); ?></h5>
                            <div id="current-shares"></div>
                        </div>
                        
                        <button type="submit"><?php _e('Share File', 'user-file-storage'); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        
        // Add CSS styles
        $this->add_inline_styles();
    }
    
    /**
     * Add inline CSS styles
     */
    private function add_inline_styles() {
        ?>
        <style>
        #user-file-storage {
            max-width: 800px;
        }
        
        .storage-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        
        .storage-bar {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .storage-progress {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #FFC107, #f44336);
            transition: width 0.3s ease;
        }
        
        .file-upload-section {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .file-upload-section:hover, .file-upload-section.drag-over {
            border-color: #0073aa;
            background-color: #f0f8ff;
        }
        
        .file-upload-section input[type="file"] {
            margin: 10px 0;
        }
        
        .file-upload-section button {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 0;
        }
        
        .file-upload-section button:hover {
            background: #005a87;
        }
        
        #upload-progress {
            margin-top: 15px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background: #4CAF50;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .files-toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        #search-files, #filter-files {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            background: white;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            border-radius: 5px;
            font-size: 18px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .file-meta {
            font-size: 12px;
            color: #666;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary { background: #0073aa; color: white; }
        .btn-secondary { background: #666; color: white; }
        .btn-danger { background: #d63638; color: white; }
        .btn-sm { padding: 3px 8px; font-size: 11px; }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover { color: black; }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            margin: 5px 0;
            cursor: pointer;
            border-radius: 3px;
        }
        
        .user-item:hover {
            background: #f0f0f0;
        }
        
        .shared-user {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            background: #f9f9f9;
            border-radius: 3px;
            border: 1px solid #eee;
        }
        
        .permission-badge {
            background: #0073aa;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 10px;
        }
        
        .file-owner {
            color: #666;
            font-style: italic;
        }
        
        .view-only {
            color: #999;
            font-style: italic;
        }
        
        #user-search-results {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-top: none;
            display: none;
        }
        
        #share-file-form label {
            display: block;
            margin: 15px 0 5px 0;
            font-weight: bold;
        }
        
        #share-file-form input, #share-file-form select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        #share-file-form button {
            background: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
        }
        
        #share-file-form button:hover {
            background: #005a87;
        }
        
        .shared-users h5 {
            margin: 20px 0 10px 0;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .files-toolbar {
                flex-direction: column;
            }
            
            .file-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .file-actions {
                margin-top: 10px;
                width: 100%;
            }
            
            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Add inline JavaScript
     */
    public function add_inline_javascript() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Load files on page load
            loadUserFiles();
            
            // File upload
            $('#file-upload-form').on('submit', function(e) {
                e.preventDefault();
                uploadFile();
            });
            
            // Search and filter
            $('#search-files, #filter-files').on('input change', function() {
                loadUserFiles();
            });
            
            // Modal controls
            $('.close').on('click', function() {
                $('.modal').hide();
            });
            
            $(window).on('click', function(e) {
                if ($(e.target).hasClass('modal')) {
                    $('.modal').hide();
                }
            });
            
            // User search for sharing
            $('#share-user-search').on('input', function() {
                var search = $(this).val();
                if (search.length < 2) {
                    $('#user-search-results').hide().empty();
                    return;
                }
                searchUsers(search);
            });
            
            // Share file form
            $('#share-file-form').on('submit', function(e) {
                e.preventDefault();
                shareFileWithUser();
            });
            
            // Drag and drop
            initializeDragDrop();
        });
        
        function loadUserFiles() {
            var search = jQuery('#search-files').val();
            var filter = jQuery('#filter-files').val();
            
            jQuery.ajax({
                url: ufs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_user_files',
                    search: search,
                    filter: filter,
                    nonce: ufs_ajax.nonces.get_files
                },
                success: function(response) {
                    if (response.success) {
                        jQuery('#files-list').html(response.data.html);
                    } else {
                        jQuery('#files-list').html('<p>Error loading files.</p>');
                    }
                }
            });
        }
        
        function uploadFile() {
            var formData = new FormData();
            var fileInput = document.getElementById('user-file-input');
            var file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a file to upload.');
                return;
            }
            
            formData.append('user_file', file);
            formData.append('action', 'upload_user_file');
            formData.append('nonce', ufs_ajax.nonces.upload);
            
            jQuery('#upload-progress').show();
            
            jQuery.ajax({
                url: ufs_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            var percentComplete = evt.loaded / evt.total * 100;
                            jQuery('.progress-fill').css('width', percentComplete + '%');
                            jQuery('.progress-text').text(Math.round(percentComplete) + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    jQuery('#upload-progress').hide();
                    if (response.success) {
                        alert('File uploaded successfully!');
                        jQuery('#user-file-input').val('');
                        loadUserFiles();
                        location.reload(); // Reload to update storage info
                    } else {
                        alert('Upload failed: ' + response.data);
                    }
                },
                error: function() {
                    jQuery('#upload-progress').hide();
                    alert('Upload failed. Please try again.');
                }
            });
        }
        
        function shareFile(fileId) {
            jQuery('#share-file-id').val(fileId);
            jQuery('#share-modal').show();
            loadCurrentShares(fileId);
        }
        
        function deleteFile(fileId) {
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }
            
            jQuery.ajax({
                url: ufs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_user_file',
                    file_id: fileId,
                    nonce: ufs_ajax.nonces.delete
                },
                success: function(response) {
                    if (response.success) {
                        alert('File deleted successfully!');
                        loadUserFiles();
                        location.reload();
                    } else {
                        alert('Delete failed: ' + response.data);
                    }
                }
            });
        }
        
        function downloadFile(fileId) {
            window.location.href = ufs_ajax.ajax_url + '?action=download_user_file&file_id=' + fileId + '&nonce=' + ufs_ajax.nonces.download;
        }
        
        function searchUsers(search) {
            jQuery.ajax({
                url: ufs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'search_users',
                    search: search,
                    nonce: ufs_ajax.nonces.search_users
                },
                success: function(response) {
                    if (response.success) {
                        displayUserSearchResults(response.data.users);
                    }
                }
            });
        }
        
        function displayUserSearchResults(users) {
            var html = '';
            users.forEach(function(user) {
                html += '<div class="user-item" onclick="selectUser(' + user.id + ', \'' + 
                        user.name.replace(/'/g, "\\'") + '\', \'' + user.email + '\')">' +
                        '<div class="user-info">' +
                        '<strong>' + user.name + '</strong><br>' +
                        '<small>' + user.email + ' (' + user.username + ')</small>' +
                        '</div>' +
                        '</div>';
            });
            
            jQuery('#user-search-results').html(html).show();
        }
        
        function selectUser(userId, name, email) {
            jQuery('#selected-user-id').val(userId);
            jQuery('#share-user-search').val(name + ' (' + email + ')');
            jQuery('#user-search-results').hide().empty();
        }
        
        function shareFileWithUser() {
            var fileId = jQuery('#share-file-id').val();
            var targetUserId = jQuery('#selected-user-id').val();
            var permissionType = jQuery('#permission-type').val();
            
            if (!targetUserId) {
                alert('Please select a user to share with.');
                return;
            }
            
            jQuery.ajax({
                url: ufs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'share_user_file',
                    file_id: fileId,
                    target_user_id: targetUserId,
                    permission_type: permissionType,
                    nonce: ufs_ajax.nonces.share_file
                },
                success: function(response) {
                    if (response.success) {
                        alert('File shared successfully!');
                        loadCurrentShares(fileId);
                        jQuery('#share-user-search').val('');
                        jQuery('#selected-user-id').val('');
                    } else {
                        alert('Sharing failed: ' + response.data);
                    }
                }
            });
        }
        
        function loadCurrentShares(fileId) {
            jQuery.ajax({
                url: ufs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_file_shares',
                    file_id: fileId,
                    nonce: ufs_ajax.nonces.get_file_shares
                },
                success: function(response) {
                    if (response.success) {
                        displayCurrentShares(response.data.shares);
                    }
                }
            });
        }
        
        function displayCurrentShares(shares) {
            var html = '';
            
            if (shares.length === 0) {
                html = '<p><em>Not shared with anyone yet.</em></p>';
            } else {
                shares.forEach(function(share) {
                    var date = new Date(share.granted_date).toLocaleDateString();
                    html += '<div class="shared-user">' +
                            '<div class="share-info">' +
                            '<strong>' + share.name + '</strong> (' + share.email + ')<br>' +
                            '<small>Permission: ' + share.permission + ' • Since: ' + date + '</small>' +
                            '</div>' +
                            '<button class="btn btn-danger btn-sm" onclick="removeShare(' + 
                            jQuery('#share-file-id').val() + ', ' + share.user_id + ')">Remove</button>' +
                            '</div>';
                });
            }
            
            jQuery('#current-shares').html(html);
        }
        
        function removeShare(fileId, userId) {
            if (!confirm('Remove sharing access for this user?')) {
                return;
            }
            
            jQuery.ajax({
                url: ufs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'remove_file_share',
                    file_id: fileId,
                    target_user_id: userId,
                    nonce: ufs_ajax.nonces.remove_file_share
                },
                success: function(response) {
                    if (response.success) {
                        alert('Share removed successfully!');
                        loadCurrentShares(fileId);
                    } else {
                        alert('Failed to remove share: ' + response.data);
                    }
                }
            });
        }
        
        function initializeDragDrop() {
            var dropZone = jQuery('.file-upload-section');
            
            dropZone.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                jQuery(this).addClass('drag-over');
            });
            
            dropZone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                jQuery(this).removeClass('drag-over');
            });
            
            dropZone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                jQuery(this).removeClass('drag-over');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    jQuery('#user-file-input')[0].files = files;
                    uploadFile();
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Get storage information for user
     */
    private function get_storage_info($user_id) {
        global $wpdb;
        
        $quota_table = $wpdb->prefix . 'user_storage_quotas';
        
        $storage_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $quota_table WHERE user_id = %d",
            $user_id
        ));
        
        if (!$storage_info) {
            // Create default quota
            $default_quota = get_option('ufs_default_quota', 100) * 1048576;
            $wpdb->insert($quota_table, array(
                'user_id' => $user_id,
                'quota_limit' => $default_quota,
                'used_space' => 0
            ));
            $storage_info = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $quota_table WHERE user_id = %d",
                $user_id
            ));
        }
        
        return $storage_info;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get user files via AJAX
     */
    public function get_user_files() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('get_user_files', 'nonce');
        
        $user_id = get_current_user_id();
        $search = sanitize_text_field($_POST['search'] ?? '');
        $filter = sanitize_text_field($_POST['filter'] ?? '');
        
        global $wpdb;
        $files_table = $wpdb->prefix . 'user_files';
        
        // Build query
        $query = "SELECT * FROM $files_table WHERE user_id = %d";
        $params = array($user_id);
        
        // Add search condition
        if (!empty($search)) {
            $query .= " AND file_name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        
        // Add filter condition
        if (!empty($filter)) {
            switch ($filter) {
                case 'image':
                    $query .= " AND mime_type LIKE 'image/%'";
                    break;
                case 'document':
                    $query .= " AND (mime_type LIKE 'application/%' OR mime_type = 'text/plain')";
                    break;
                case 'archive':
                    $query .= " AND mime_type IN ('application/zip', 'application/x-rar-compressed')";
                    break;
            }
        }
        
        $query .= " ORDER BY upload_date DESC";
        
        $files = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // Also get shared files
        $permissions_table = $wpdb->prefix . 'user_file_permissions';
        $shared_files_query = "
            SELECT f.*, p.permission_type, u.display_name as owner_name 
            FROM $files_table f 
            JOIN $permissions_table p ON f.id = p.file_id 
            JOIN {$wpdb->users} u ON f.user_id = u.ID
            WHERE p.user_id = %d
        ";
        
        if (!empty($search)) {
            $shared_files_query .= " AND f.file_name LIKE %s";
            $shared_params = array($user_id, '%' . $wpdb->esc_like($search) . '%');
        } else {
            $shared_params = array($user_id);
        }
        
        $shared_files = $wpdb->get_results($wpdb->prepare($shared_files_query, $shared_params));
        
        $html = $this->render_files_list($files, $shared_files);
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Render files list HTML
     */
    private function render_files_list($files, $shared_files = array()) {
        if (empty($files) && empty($shared_files)) {
            return '<p>' . __('No files found.', 'user-file-storage') . '</p>';
        }
        
        $html = '';
        
        // My Files
        if (!empty($files)) {
            $html .= '<h5>' . __('My Files', 'user-file-storage') . '</h5>';
            foreach ($files as $file) {
                $html .= $this->render_file_item($file, true);
            }
        }
        
        // Shared Files
        if (!empty($shared_files)) {
            $html .= '<h5 style="margin-top: 30px;">' . __('Shared with Me', 'user-file-storage') . '</h5>';
            foreach ($shared_files as $file) {
                $html .= $this->render_file_item($file, false);
            }
        }
        
        return $html;
    }
    
    /**
     * Render individual file item
     */
    private function render_file_item($file, $is_owner = true) {
        $file_icon = $this->get_file_icon($file->mime_type);
        $file_size = $this->format_bytes($file->file_size);
        $upload_date = date('M j, Y', strtotime($file->upload_date));
        
        $owner_info = '';
        if (!$is_owner && isset($file->owner_name)) {
            $owner_info = ' <span class="file-owner">' . sprintf(__('(Shared by %s)', 'user-file-storage'), esc_html($file->owner_name)) . '</span>';
        }
        
        $permission_info = '';
        if (!$is_owner && isset($file->permission_type)) {
            $permission_info = '<span class="permission-badge">' . ucfirst($file->permission_type) . '</span>';
        }
        
        $actions = '';
        if ($is_owner) {
            $actions = '
                <button class="btn btn-primary" onclick="downloadFile(' . $file->id . ')">' . __('Download', 'user-file-storage') . '</button>
                <button class="btn btn-secondary" onclick="shareFile(' . $file->id . ')">' . __('Share', 'user-file-storage') . '</button>
                <button class="btn btn-danger" onclick="deleteFile(' . $file->id . ')">' . __('Delete', 'user-file-storage') . '</button>
            ';
        } else {
            $can_download = isset($file->permission_type) && in_array($file->permission_type, ['download', 'edit']);
            if ($can_download) {
                $actions = '<button class="btn btn-primary" onclick="downloadFile(' . $file->id . ')">' . __('Download', 'user-file-storage') . '</button>';
            } else {
                $actions = '<span class="view-only">' . __('View Only', 'user-file-storage') . '</span>';
            }
        }
        
        return '
            <div class="file-item">
                <div class="file-icon">' . $file_icon . '</div>
                <div class="file-info">
                    <div class="file-name">' . esc_html($file->file_name) . $owner_info . '</div>
                    <div class="file-meta">
                        ' . $file_size . ' • ' . $upload_date . ' • ' . sprintf(__('Downloads: %d', 'user-file-storage'), $file->download_count) . '
                        ' . $permission_info . '
                    </div>
                </div>
                <div class="file-actions">
                    ' . $actions . '
                </div>
            </div>
        ';
    }
    
    /**
     * Get file icon based on MIME type
     */
    private function get_file_icon($mime_type) {
        $icons = array(
            'image/' => '🖼️',
            'application/pdf' => '📄',
            'application/msword' => '📝',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '📝',
            'text/plain' => '📝',
            'application/zip' => '📦',
            'application/x-rar-compressed' => '📦',
            'application/octet-stream' => '📐',
        );
        
        foreach ($icons as $type => $icon) {
            if (strpos($mime_type, $type) === 0) {
                return $icon;
            }
        }
        
        return '📁'; // Default icon
    }
    
    // Add all the remaining methods for delete, download, share, search users, etc.
    // [Rest of the AJAX handler methods would continue here...]
    
    /**
     * Delete user file
     */
    public function delete_user_file() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('delete_user_file', 'nonce');
        
        $file_id = intval($_POST['file_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $files_table = $wpdb->prefix . 'user_files';
        $permissions_table = $wpdb->prefix . 'user_file_permissions';
        
        // Get file info and verify ownership
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $files_table WHERE id = %d AND user_id = %d",
            $file_id,
            $user_id
        ));
        
        if (!$file) {
            wp_send_json_error('File not found or unauthorized');
        }
        
        // Delete physical file
        if (file_exists($file->file_path)) {
            unlink($file->file_path);
        }
        
        // Delete from database
        $wpdb->delete($files_table, array('id' => $file_id), array('%d'));
        
        // Delete permissions
        $wpdb->delete($permissions_table, array('file_id' => $file_id), array('%d'));
        
        // Update storage usage
        $this->update_storage_usage($user_id, -$file->file_size);
        
        wp_send_json_success('File deleted successfully');
    }
    
    /**
     * Download user file
     */
    public function download_file() {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'download_user_file')) {
            wp_die('Security check failed');
        }
        
        $file_id = intval($_GET['file_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $files_table = $wpdb->prefix . 'user_files';
        $permissions_table = $wpdb->prefix . 'user_file_permissions';
        
        // Check if user owns the file or has permission
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $files_table WHERE id = %d AND (user_id = %d OR id IN (
                SELECT file_id FROM $permissions_table WHERE user_id = %d AND permission_type IN ('download', 'edit')
            ))",
            $file_id,
            $user_id,
            $user_id
        ));
        
        if (!$file || !file_exists($file->file_path)) {
            wp_die('File not found or access denied');
        }
        
        // Update download count
        $wpdb->update(
            $files_table,
            array('download_count' => $file->download_count + 1),
            array('id' => $file_id),
            array('%d'),
            array('%d')
        );
        
        // Force download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file->file_name) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file->file_path));
        
        // Log download activity
        UFS_Activity_Log::log_activity($user_id, 'file_downloaded', array(
            'file_id' => $file_id,
            'project_id' => $file->project_id
        ));
        readfile($file->file_path);
        exit;
    }
    
    /**
     * Share file with other users
     */
    public function share_file() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('share_file', 'nonce');
        
        $file_id = intval($_POST['file_id']);
        $target_user_id = intval($_POST['target_user_id']);
        $permission_type = sanitize_text_field($_POST['permission_type']);
        $user_id = get_current_user_id();
        
        if (!in_array($permission_type, array('view', 'download', 'edit'))) {
            wp_send_json_error('Invalid permission type');
        }
        
        global $wpdb;
        $files_table = $wpdb->prefix . 'user_files';
        $permissions_table = $wpdb->prefix . 'user_file_permissions';
        
        // Verify file ownership
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $files_table WHERE id = %d AND user_id = %d",
            $file_id,
            $user_id
        ));
        
        if (!$file) {
            wp_send_json_error('File not found or unauthorized');
        }
        
        // Verify target user exists
        $target_user = get_user_by('id', $target_user_id);
        if (!$target_user) {
            wp_send_json_error('Target user not found');
        }
        
        // Insert or update permission
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $permissions_table WHERE file_id = %d AND user_id = %d",
            $file_id,
            $target_user_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $permissions_table,
                array('permission_type' => $permission_type),
                array('file_id' => $file_id, 'user_id' => $target_user_id),
                array('%s'),
                array('%d', '%d')
            );
        } else {
            $wpdb->insert(
                $permissions_table,
                array(
                    'file_id' => $file_id,
                    'user_id' => $target_user_id,
                    'permission_type' => $permission_type,
                    'granted_by' => $user_id
                ),
                array('%d', '%d', '%s', '%d')
            );
        }
        
        wp_send_json_success('File shared successfully');
    }
    
    /**
     * Search users for sharing
     */
    public function search_users() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('search_users', 'nonce');
        
        $search = sanitize_text_field($_POST['search']);
        $current_user_id = get_current_user_id();
        
        if (strlen($search) < 2) {
            wp_send_json_success(array('users' => array()));
        }
        
        $users = get_users(array(
            'search' => '*' . $search . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'exclude' => array($current_user_id),
            'number' => 10
        ));
        
        $results = array();
        foreach ($users as $user) {
            $results[] = array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'username' => $user->user_login
            );
        }
        
        wp_send_json_success(array('users' => $results));
    }
    
    /**
     * Get current file shares
     */
    public function get_file_shares() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('get_file_shares', 'nonce');
        
        $file_id = intval($_POST['file_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $files_table = $wpdb->prefix . 'user_files';
        $permissions_table = $wpdb->prefix . 'user_file_permissions';
        
        // Verify file ownership
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $files_table WHERE id = %d AND user_id = %d",
            $file_id,
            $user_id
        ));
        
        if (!$file) {
            wp_send_json_error('File not found or unauthorized');
        }
        
        // Get current shares
        $shares = $wpdb->get_results($wpdb->prepare("
            SELECT p.*, u.display_name, u.user_email 
            FROM $permissions_table p 
            JOIN {$wpdb->users} u ON p.user_id = u.ID 
            WHERE p.file_id = %d
        ", $file_id));
        
        $results = array();
        foreach ($shares as $share) {
            $results[] = array(
                'user_id' => $share->user_id,
                'name' => $share->display_name,
                'email' => $share->user_email,
                'permission' => $share->permission_type,
                'granted_date' => $share->granted_date
            );
        }
        
        wp_send_json_success(array('shares' => $results));
    }
    
    /**
     * Remove file share
     */
    public function remove_file_share() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('remove_file_share', 'nonce');
        
        $file_id = intval($_POST['file_id']);
        $target_user_id = intval($_POST['target_user_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $files_table = $wpdb->prefix . 'user_files';
        $permissions_table = $wpdb->prefix . 'user_file_permissions';
        
        // Verify file ownership
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $files_table WHERE id = %d AND user_id = %d",
            $file_id,
            $user_id
        ));
        
        if (!$file) {
            wp_send_json_error('File not found or unauthorized');
        }
        
        // Remove permission
        $wpdb->delete(
            $permissions_table,
            array('file_id' => $file_id, 'user_id' => $target_user_id),
            array('%d', '%d')
        );
        
        wp_send_json_success('Share removed successfully');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('User File Storage Settings', 'user-file-storage'),
            __('User File Storage', 'user-file-storage'),
            'manage_options',
            'user-file-storage',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin settings initialization
     */
    public function admin_init() {
        register_setting('user_file_storage', 'ufs_max_file_size');
        register_setting('user_file_storage', 'ufs_default_quota');
        register_setting('user_file_storage', 'ufs_allowed_types');
        
        add_settings_section(
            'ufs_main_section',
            __('File Storage Settings', 'user-file-storage'),
            null,
            'user-file-storage'
        );
        
        add_settings_field(
            'ufs_max_file_size',
            __('Max File Size (MB)', 'user-file-storage'),
            array($this, 'max_file_size_callback'),
            'user-file-storage',
            'ufs_main_section'
        );
        
        add_settings_field(
            'ufs_default_quota',
            __('Default User Quota (MB)', 'user-file-storage'),
            array($this, 'default_quota_callback'),
            'user-file-storage',
            'ufs_main_section'
        );
        
        add_settings_field(
            'ufs_allowed_types',
            __('Allowed File Types', 'user-file-storage'),
            array($this, 'allowed_types_callback'),
            'user-file-storage',
            'ufs_main_section'
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('User File Storage Settings', 'user-file-storage'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('user_file_storage');
                do_settings_sections('user-file-storage');
                submit_button();
                ?>
            </form>
            
            <h2><?php _e('Storage Statistics', 'user-file-storage'); ?></h2>
            <?php $this->display_storage_stats(); ?>
            
            <h2><?php _e('User Quota Management', 'user-file-storage'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('update_user_quota', 'quota_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('User', 'user-file-storage'); ?></th>
                        <td>
                            <select name="user_id" required>
                                <option value=""><?php _e('Select User...', 'user-file-storage'); ?></option>
                                <?php
                                $users = get_users();
                                foreach ($users as $user) {
                                    echo '<option value="' . $user->ID . '">' . $user->display_name . ' (' . $user->user_email . ')</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('New Quota (MB)', 'user-file-storage'); ?></th>
                        <td><input type="number" name="new_quota" step="0.1" required></td>
                    </tr>
                </table>
                <?php submit_button(__('Update User Quota', 'user-file-storage')); ?>
            </form>
            
            <?php
            if (isset($_POST['quota_nonce']) && wp_verify_nonce($_POST['quota_nonce'], 'update_user_quota')) {
                $this->update_user_quota($_POST['user_id'], $_POST['new_quota']);
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Settings field callbacks
     */
    public function max_file_size_callback() {
        $value = get_option('ufs_max_file_size', 10);
        echo '<input type="number" name="ufs_max_file_size" value="' . $value . '" step="0.1">';
        echo '<p class="description">' . __('Maximum file size in megabytes', 'user-file-storage') . '</p>';
    }
    
    public function default_quota_callback() {
        $value = get_option('ufs_default_quota', 100);
        echo '<input type="number" name="ufs_default_quota" value="' . $value . '" step="0.1">';
        echo '<p class="description">' . __('Default storage quota for new users in megabytes', 'user-file-storage') . '</p>';
    }
    
    public function allowed_types_callback() {
        $value = get_option('ufs_allowed_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip');
        echo '<input type="text" name="ufs_allowed_types" value="' . $value . '" class="regular-text">';
        echo '<p class="description">' . __('Comma-separated list of allowed file extensions', 'user-file-storage') . '</p>';
    }
    
    /**
     * Display storage statistics
     */
    private function display_storage_stats() {
        global $wpdb;
        
        $files_table = $wpdb->prefix . 'user_files';
        $quotas_table = $wpdb->prefix . 'user_storage_quotas';
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                AVG(file_size) as avg_file_size
            FROM $files_table
        ");
        
        $user_stats = $wpdb->get_results("
            SELECT 
                u.display_name,
                u.user_email,
                q.quota_limit,
                q.used_space,
                COUNT(f.id) as file_count
            FROM {$wpdb->users} u
            LEFT JOIN $quotas_table q ON u.ID = q.user_id
            LEFT JOIN $files_table f ON u.ID = f.user_id
            WHERE q.user_id IS NOT NULL OR f.user_id IS NOT NULL
            GROUP BY u.ID
            ORDER BY q.used_space DESC
            LIMIT 10
        ");
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Statistic', 'user-file-storage'); ?></th>
                    <th><?php _e('Value', 'user-file-storage'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('Total Files', 'user-file-storage'); ?></td>
                    <td><?php echo number_format($stats->total_files); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Total Storage Used', 'user-file-storage'); ?></td>
                    <td><?php echo $this->format_bytes($stats->total_size); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Average File Size', 'user-file-storage'); ?></td>
                    <td><?php echo $this->format_bytes($stats->avg_file_size); ?></td>
                </tr>
            </tbody>
        </table>
        
        <?php if (!empty($user_stats)): ?>
        <h3><?php _e('Top Storage Users', 'user-file-storage'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('User', 'user-file-storage'); ?></th>
                    <th><?php _e('Files', 'user-file-storage'); ?></th>
                    <th><?php _e('Used', 'user-file-storage'); ?></th>
                    <th><?php _e('Quota', 'user-file-storage'); ?></th>
                    <th><?php _e('Usage %', 'user-file-storage'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_stats as $user): 
                    $usage_percent = $user->quota_limit > 0 ? ($user->used_space / $user->quota_limit) * 100 : 0;
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($user->display_name); ?></strong><br>
                        <small><?php echo esc_html($user->user_email); ?></small>
                    </td>
                    <td><?php echo number_format($user->file_count); ?></td>
                    <td><?php echo $this->format_bytes($user->used_space); ?></td>
                    <td><?php echo $this->format_bytes($user->quota_limit); ?></td>
                    <td>
                        <span style="color: <?php echo $usage_percent > 90 ? 'red' : ($usage_percent > 75 ? 'orange' : 'green'); ?>">
                            <?php echo number_format($usage_percent, 1); ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Update user quota
     */
     
    private function update_user_quota($user_id, $new_quota_mb) {
        if (empty($user_id) || empty($new_quota_mb)) {
            echo '<div class="notice notice-error"><p>' . __('Please fill in all fields.', 'user-file-storage') . '</p></div>';
            return;
        }
        
        global $wpdb;
        
        $quota_bytes = $new_quota_mb * 1048576; // Convert MB to bytes
        $quotas_table = $wpdb->prefix . 'user_storage_quotas';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $quotas_table WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $quotas_table,
                array('quota_limit' => $quota_bytes),
                array('user_id' => $user_id),
                array('%d'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $quotas_table,
                array(
                    'user_id' => $user_id,
                    'quota_limit' => $quota_bytes,
                    'used_space' => 0
                ),
                array('%d', '%d', '%d')
            );
        }
        
        echo '<div class="notice notice-success"><p>' . __('User quota updated successfully!', 'user-file-storage') . '</p></div>';
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'user_files_widget',
            __('User File Storage Overview', 'user-file-storage'),
            array($this, 'dashboard_widget_content')
        );
    }
    
    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        global $wpdb;
        
        $files_table = $wpdb->prefix . 'user_files';
        $recent_files = $wpdb->get_results("
            SELECT f.*, u.display_name 
            FROM $files_table f 
            JOIN {$wpdb->users} u ON f.user_id = u.ID 
            ORDER BY f.upload_date DESC 
            LIMIT 5
        ");
        
        echo '<h4>' . __('Recent Uploads', 'user-file-storage') . '</h4>';
        if ($recent_files) {
            echo '<ul>';
            foreach ($recent_files as $file) {
                echo '<li>';
                echo '<strong>' . esc_html($file->file_name) . '</strong><br>';
                echo sprintf(__('by %s • %s', 'user-file-storage'), 
                    esc_html($file->display_name), 
                    date('M j, Y', strtotime($file->upload_date))
                );
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No files uploaded yet.', 'user-file-storage') . '</p>';
        }
        
        echo '<p><a href="' . admin_url('options-general.php?page=user-file-storage') . '">' . __('Manage Settings', 'user-file-storage') . '</a></p>';
    }
}
// Initialize projects frontend
new UFS_Projects_Frontend();
new UFS_Share_Links_Manager();
// Initialize the plugin
add_action('plugins_loaded', function() {
    // Check if WooCommerce is active
    if (class_exists('WooCommerce')) {
        new UserFileStorage();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . __('User File Storage requires WooCommerce to be installed and activated.', 'user-file-storage') . '</p></div>';
        });
    }
});

/**
 * Add shortcode for frontend display (optional - for use outside WooCommerce)
 */
function ufs_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>' . __('Please log in to access your files.', 'user-file-storage') . '</p>';
    }
    
    if (!class_exists('UserFileStorage')) {
        return '<p>' . __('File storage system not available.', 'user-file-storage') . '</p>';
    }
    
    $storage = new UserFileStorage();
    ob_start();
    $storage->file_storage_content();
    return ob_get_clean();
}
add_shortcode('user_file_storage', 'ufs_shortcode');

/**
 * Load text domain for translations
 */
add_action('plugins_loaded', function() {
    load_plugin_textdomain('user-file-storage', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

/**
 * Plugin uninstall hook (create separate uninstall.php file for this)
 */
// register_uninstall_hook(__FILE__, 'ufs_uninstall_plugin');
function ufs_uninstall_plugin() {
    global $wpdb;
    
    // Remove tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}user_files");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}user_file_permissions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}user_storage_quotas");
    
    // Remove options
    delete_option('ufs_max_file_size');
    delete_option('ufs_default_quota');
    delete_option('ufs_allowed_types');
    
    // Optionally remove upload directory (commented out for safety)
    // $upload_dir = wp_upload_dir();
    // $user_storage_dir = $upload_dir['basedir'] . '/user-storage';
    // if (file_exists($user_storage_dir)) {
    //     // Remove directory and all files (be very careful with this!)
    //     // wp_delete_file($user_storage_dir);
    // }
}
<?php
/**
 * Projects Manager Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class UFS_Projects_Manager {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_create_project', array($this, 'create_project'));
        add_action('wp_ajax_get_user_projects', array($this, 'get_user_projects'));
        add_action('wp_ajax_get_project_details', array($this, 'get_project_details'));
        add_action('wp_ajax_update_project', array($this, 'update_project'));
        add_action('wp_ajax_delete_project', array($this, 'delete_project'));
        add_action('wp_ajax_share_project', array($this, 'share_project'));
        add_action('wp_ajax_get_project_members', array($this, 'get_project_members'));
        add_action('wp_ajax_remove_project_member', array($this, 'remove_project_member'));
        add_action('wp_ajax_create_folder', array($this, 'create_folder'));
        add_action('wp_ajax_get_project_folders', array($this, 'get_project_folders'));
        add_action('wp_ajax_delete_folder', array($this, 'delete_folder'));
        
        // Add nonces
        add_filter('ufs_ajax_nonces', array($this, 'add_project_nonces'));
    }
    
    public function add_project_nonces($nonces) {
        $nonces['create_project'] = wp_create_nonce('create_project');
        $nonces['get_user_projects'] = wp_create_nonce('get_user_projects');
        $nonces['get_project_details'] = wp_create_nonce('get_project_details');
        $nonces['update_project'] = wp_create_nonce('update_project');
        $nonces['delete_project'] = wp_create_nonce('delete_project');
        $nonces['share_project'] = wp_create_nonce('share_project');
        $nonces['get_project_members'] = wp_create_nonce('get_project_members');
        $nonces['remove_project_member'] = wp_create_nonce('remove_project_member');
        $nonces['create_folder'] = wp_create_nonce('create_folder');
        $nonces['get_project_folders'] = wp_create_nonce('get_project_folders');
        $nonces['delete_folder'] = wp_create_nonce('delete_folder');
        return $nonces;
    }
    
    /**
     * Create new project
     */
    public function create_project() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('create_project', 'nonce');
        
        $user_id = get_current_user_id();
        $project_name = sanitize_text_field($_POST['project_name']);
        $project_description = sanitize_textarea_field($_POST['project_description'] ?? '');
        $project_type = sanitize_text_field($_POST['project_type'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'active');
        
        if (empty($project_name)) {
            wp_send_json_error('Project name is required');
        }
        
        global $wpdb;
        $projects_table = $wpdb->prefix . 'user_projects';
        
        $result = $wpdb->insert(
            $projects_table,
            array(
                'project_name' => $project_name,
                'project_description' => $project_description,
                'owner_id' => $user_id,
                'status' => $status,
                'project_type' => $project_type
            ),
            array('%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'project_id' => $wpdb->insert_id,
                'message' => 'Project created successfully'
            ));
        } else {
            wp_send_json_error('Failed to create project');
        }
    }
    
    /**
     * Get user's projects (owned and shared)
     */
    public function get_user_projects() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('get_user_projects', 'nonce');
        
        $user_id = get_current_user_id();
        $filter_status = sanitize_text_field($_POST['filter_status'] ?? '');
        
        global $wpdb;
        $projects_table = $wpdb->prefix . 'user_projects';
        $members_table = $wpdb->prefix . 'project_members';
        $files_table = $wpdb->prefix . 'user_files';
        
        // Get owned projects
        $owned_query = "SELECT p.*, COUNT(DISTINCT f.id) as file_count, 
                        COUNT(DISTINCT pm.user_id) as member_count,
                        'owner' as user_role, 'manage' as user_permission
                        FROM $projects_table p
                        LEFT JOIN $files_table f ON p.id = f.project_id
                        LEFT JOIN $members_table pm ON p.id = pm.project_id
                        WHERE p.owner_id = %d";
        
        if (!empty($filter_status)) {
            $owned_query .= " AND p.status = %s";
            $owned_params = array($user_id, $filter_status);
        } else {
            $owned_params = array($user_id);
        }
        
        $owned_query .= " GROUP BY p.id";
        
        $owned_projects = $wpdb->get_results($wpdb->prepare($owned_query, $owned_params));
        
        // Get shared projects
        $shared_query = "SELECT p.*, COUNT(DISTINCT f.id) as file_count,
                         COUNT(DISTINCT pm.user_id) as member_count,
                         pm2.role as user_role, pm2.permission_level as user_permission,
                         u.display_name as owner_name
                         FROM $projects_table p
                         JOIN $members_table pm2 ON p.id = pm2.project_id AND pm2.user_id = %d
                         LEFT JOIN $files_table f ON p.id = f.project_id
                         LEFT JOIN $members_table pm ON p.id = pm.project_id
                         JOIN {$wpdb->users} u ON p.owner_id = u.ID";
        
        if (!empty($filter_status)) {
            $shared_query .= " WHERE p.status = %s";
            $shared_params = array($user_id, $filter_status);
        } else {
            $shared_params = array($user_id);
        }
        
        $shared_query .= " GROUP BY p.id";
        
        $shared_projects = $wpdb->get_results($wpdb->prepare($shared_query, $shared_params));
        
        wp_send_json_success(array(
            'owned' => $owned_projects,
            'shared' => $shared_projects
        ));
    }
    
    /**
     * Get project details including files and folders
     */
    public function get_project_details() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('get_project_details', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();
        
        if (!$this->user_has_project_access($user_id, $project_id)) {
            wp_send_json_error('No access to this project');
        }
        
        global $wpdb;
        $projects_table = $wpdb->prefix . 'user_projects';
        $files_table = $wpdb->prefix . 'user_files';
        $folders_table = $wpdb->prefix . 'project_folders';
        
        // Get project info
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $projects_table WHERE id = %d",
            $project_id
        ));
        
        if (!$project) {
            wp_send_json_error('Project not found');
        }
        
        // Get user permission
        $permission = $this->get_user_project_permission($user_id, $project_id);
        
        // Get folders
        $folders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $folders_table WHERE project_id = %d ORDER BY folder_name",
            $project_id
        ));
        
        // Get files
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, u.display_name as uploader_name, 
             COALESCE(pf.folder_name, 'Root') as folder_name
             FROM $files_table f
             JOIN {$wpdb->users} u ON f.user_id = u.ID
             LEFT JOIN $folders_table pf ON f.folder_id = pf.id
             WHERE f.project_id = %d
             ORDER BY f.upload_date DESC",
            $project_id
        ));
        
        wp_send_json_success(array(
            'project' => $project,
            'permission' => $permission,
            'folders' => $folders,
            'files' => $files
        ));
    }
    
    /**
     * Update project
     */
    public function update_project() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('update_project', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();
        
        if (!$this->user_can_manage_project($user_id, $project_id)) {
            wp_send_json_error('No permission to manage this project');
        }
        
        global $wpdb;
        $projects_table = $wpdb->prefix . 'user_projects';
        
        $update_data = array();
        
        if (isset($_POST['project_name'])) {
            $update_data['project_name'] = sanitize_text_field($_POST['project_name']);
        }
        if (isset($_POST['project_description'])) {
            $update_data['project_description'] = sanitize_textarea_field($_POST['project_description']);
        }
        if (isset($_POST['status'])) {
            $update_data['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['project_type'])) {
            $update_data['project_type'] = sanitize_text_field($_POST['project_type']);
        }
        
        if (empty($update_data)) {
            wp_send_json_error('No data to update');
        }
        
        $result = $wpdb->update(
            $projects_table,
            $update_data,
            array('id' => $project_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Project updated successfully');
        } else {
            wp_send_json_error('Failed to update project');
        }
    }
    
    /**
     * Delete project
     */
    public function delete_project() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('delete_project', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $projects_table = $wpdb->prefix . 'user_projects';
        
        // Only owner can delete
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $projects_table WHERE id = %d AND owner_id = %d",
            $project_id,
            $user_id
        ));
        
        if (!$project) {
            wp_send_json_error('Project not found or unauthorized');
        }
        
        // Delete all project files
        $files_table = $wpdb->prefix . 'user_files';
        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $files_table WHERE project_id = %d",
            $project_id
        ));
        
        foreach ($files as $file) {
            if (file_exists($file->file_path)) {
                unlink($file->file_path);
            }
        }
        
        // Delete from database
        $wpdb->delete($files_table, array('project_id' => $project_id), array('%d'));
        $wpdb->delete($wpdb->prefix . 'project_members', array('project_id' => $project_id), array('%d'));
        $wpdb->delete($wpdb->prefix . 'project_folders', array('project_id' => $project_id), array('%d'));
        $wpdb->delete($projects_table, array('id' => $project_id), array('%d'));
        
        wp_send_json_success('Project deleted successfully');
    }
    
    /**
     * Share project with user
     */
    public function share_project() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('share_project', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $target_user_id = intval($_POST['target_user_id']);
        $role = sanitize_text_field($_POST['role'] ?? 'member');
        $permission = sanitize_text_field($_POST['permission'] ?? 'view');
        $user_id = get_current_user_id();
        
        if (!$this->user_can_manage_project($user_id, $project_id)) {
            wp_send_json_error('No permission to share this project');
        }
        
        if (!in_array($permission, array('view', 'download', 'upload', 'manage'))) {
            wp_send_json_error('Invalid permission level');
        }
        
        global $wpdb;
        $members_table = $wpdb->prefix . 'project_members';
        
        // Check if already shared
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE project_id = %d AND user_id = %d",
            $project_id,
            $target_user_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $members_table,
                array(
                    'role' => $role,
                    'permission_level' => $permission
                ),
                array('id' => $existing->id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $members_table,
                array(
                    'project_id' => $project_id,
                    'user_id' => $target_user_id,
                    'role' => $role,
                    'permission_level' => $permission,
                    'added_by' => $user_id
                ),
                array('%d', '%d', '%s', '%s', '%d')
            );
        }
        
        wp_send_json_success('Project shared successfully');
    }
    
    /**
     * Get project members
     */
    public function get_project_members() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('get_project_members', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();
        
        if (!$this->user_has_project_access($user_id, $project_id)) {
            wp_send_json_error('No access to this project');
        }
        
        global $wpdb;
        $members_table = $wpdb->prefix . 'project_members';
        
        $members = $wpdb->get_results($wpdb->prepare("
            SELECT pm.*, u.display_name, u.user_email
            FROM $members_table pm
            JOIN {$wpdb->users} u ON pm.user_id = u.ID
            WHERE pm.project_id = %d
            ORDER BY pm.added_date DESC
        ", $project_id));
        
        wp_send_json_success(array('members' => $members));
    }
    
    /**
     * Remove project member
     */
    public function remove_project_member() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('remove_project_member', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $target_user_id = intval($_POST['target_user_id']);
        $user_id = get_current_user_id();
        
        if (!$this->user_can_manage_project($user_id, $project_id)) {
            wp_send_json_error('No permission to manage members');
        }
        
        global $wpdb;
        $members_table = $wpdb->prefix . 'project_members';
        
        $wpdb->delete(
            $members_table,
            array('project_id' => $project_id, 'user_id' => $target_user_id),
            array('%d', '%d')
        );
        
        wp_send_json_success('Member removed successfully');
    }
    
    /**
     * Create folder in project
     */
    public function create_folder() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('create_folder', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $folder_name = sanitize_text_field($_POST['folder_name']);
        $parent_folder_id = !empty($_POST['parent_folder_id']) ? intval($_POST['parent_folder_id']) : null;
        $user_id = get_current_user_id();
        
        $permission = $this->get_user_project_permission($user_id, $project_id);
        if (!in_array($permission, array('upload', 'manage'))) {
            wp_send_json_error('No permission to create folders');
        }
        
        global $wpdb;
        $folders_table = $wpdb->prefix . 'project_folders';
        
        $result = $wpdb->insert(
            $folders_table,
            array(
                'project_id' => $project_id,
                'folder_name' => $folder_name,
                'parent_folder_id' => $parent_folder_id,
                'created_by' => $user_id
            ),
            array('%d', '%s', '%d', '%d')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'folder_id' => $wpdb->insert_id,
                'message' => 'Folder created successfully'
            ));
        } else {
            wp_send_json_error('Failed to create folder');
        }
    }
    
    /**
     * Get project folders
     */
    public function get_project_folders() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('get_project_folders', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();
        
        if (!$this->user_has_project_access($user_id, $project_id)) {
            wp_send_json_error('No access to this project');
        }
        
        global $wpdb;
        $folders_table = $wpdb->prefix . 'project_folders';
        
        $folders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $folders_table WHERE project_id = %d ORDER BY folder_name",
            $project_id
        ));
        
        wp_send_json_success(array('folders' => $folders));
    }
    
    /**
     * Delete folder
     */
    public function delete_folder() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('delete_folder', 'nonce');
        
        $folder_id = intval($_POST['folder_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $folders_table = $wpdb->prefix . 'project_folders';
        
        $folder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $folders_table WHERE id = %d",
            $folder_id
        ));
        
        if (!$folder) {
            wp_send_json_error('Folder not found');
        }
        
        if (!$this->user_can_manage_project($user_id, $folder->project_id)) {
            wp_send_json_error('No permission to delete folders');
        }
        
        // Check if folder has files
        $files_table = $wpdb->prefix . 'user_files';
        $file_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $files_table WHERE folder_id = %d",
            $folder_id
        ));
        
        if ($file_count > 0) {
            wp_send_json_error('Cannot delete folder with files');
        }
        
        $wpdb->delete($folders_table, array('id' => $folder_id), array('%d'));
        
        wp_send_json_success('Folder deleted successfully');
    }
    
    // Helper functions
    
    private function user_has_project_access($user_id, $project_id) {
        global $wpdb;
        $projects_table = $wpdb->prefix . 'user_projects';
        $members_table = $wpdb->prefix . 'project_members';
        
        $has_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $projects_table p
             WHERE p.id = %d AND (p.owner_id = %d OR EXISTS (
                SELECT 1 FROM $members_table pm WHERE pm.project_id = p.id AND pm.user_id = %d
             ))",
            $project_id,
            $user_id,
            $user_id
        ));
        
        return $has_access > 0;
    }
    
    private function user_can_manage_project($user_id, $project_id) {
        global $wpdb;
        $projects_table = $wpdb->prefix . 'user_projects';
        $members_table = $wpdb->prefix . 'project_members';
        
        $can_manage = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $projects_table p
             WHERE p.id = %d AND (p.owner_id = %d OR EXISTS (
                SELECT 1 FROM $members_table pm 
                WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.permission_level = 'manage'
             ))",
            $project_id,
            $user_id,
            $user_id
        ));
        
        return $can_manage > 0;
    }
    
    private function get_user_project_permission($user_id, $project_id) {
        global $wpdb;
        $projects_table = $wpdb->prefix . 'user_projects';
        $members_table = $wpdb->prefix . 'project_members';
        
        // Check if owner
        $is_owner = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $projects_table WHERE id = %d AND owner_id = %d",
            $project_id,
            $user_id
        ));
        
        if ($is_owner) {
            return 'manage';
        }
        
        // Check member permission
        $permission = $wpdb->get_var($wpdb->prepare(
            "SELECT permission_level FROM $members_table WHERE project_id = %d AND user_id = %d",
            $project_id,
            $user_id
        ));
        
        return $permission ?: 'none';
    }
}
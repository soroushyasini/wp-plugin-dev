<?php
/**
 * Storage Dashboard for Project Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class UFS_Storage_Dashboard {
    
    public function __construct() {
        add_action('wp_ajax_get_storage_stats', array($this, 'get_storage_stats'));
    }
    
    /**
     * Get user storage statistics
     */
public function get_storage_stats() {
    error_log('get_storage_stats called'); // DEBUG
    
    if (!is_user_logged_in()) {
        error_log('User not logged in'); // DEBUG
        wp_send_json_error('Unauthorized');
    }
    
    // Check nonce
    if (!check_ajax_referer('get_storage_stats', 'nonce', false)) {
        error_log('Nonce check failed'); // DEBUG
        wp_send_json_error('Security check failed');
    }
    
    $user_id = get_current_user_id();
    error_log('Getting stats for user: ' . $user_id); // DEBUG
    
    global $wpdb;
    $files_table = $wpdb->prefix . 'user_files';
    $quotas_table = $wpdb->prefix . 'user_storage_quotas';
    $projects_table = $wpdb->prefix . 'user_projects';
    $members_table = $wpdb->prefix . 'project_members';
    
    // Get quota info
    $quota = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $quotas_table WHERE user_id = %d",
        $user_id
    ));
    
    if (!$quota) {
        // Create default quota
        $default_quota = get_option('ufs_default_quota', 100) * 1048576;
        $wpdb->insert($quotas_table, array(
            'user_id' => $user_id,
            'quota_limit' => $default_quota,
            'used_space' => 0
        ));
        $quota = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $quotas_table WHERE user_id = %d",
            $user_id
        ));
    }
    
    // Get file statistics
    $file_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) as total_files, 
                COALESCE(SUM(file_size), 0) as total_size,
                COUNT(DISTINCT project_id) as project_count
         FROM $files_table 
         WHERE user_id = %d",
        $user_id
    ));
    
    // Get accessible projects count - FIXED QUERY
    $owned_projects = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $projects_table WHERE owner_id = %d",
        $user_id
    ));
    
    $shared_projects = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT project_id) FROM $members_table WHERE user_id = %d",
        $user_id
    ));
    
    $accessible_projects = $owned_projects + $shared_projects;
    
    // Get file type breakdown
    $file_types = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            CASE 
                WHEN mime_type LIKE 'image/%%' THEN 'Images'
                WHEN mime_type = 'application/pdf' THEN 'PDFs'
                WHEN mime_type LIKE '%%word%%' OR mime_type = 'text/plain' THEN 'Documents'
                WHEN mime_type LIKE '%%zip%%' OR mime_type LIKE '%%rar%%' THEN 'Archives'
                WHEN mime_type LIKE '%%dwg%%' OR mime_type LIKE '%%dxf%%' OR mime_type LIKE '%%dwf%%' THEN 'CAD Files'
                ELSE 'Other'
            END as file_type,
            COUNT(*) as count,
            SUM(file_size) as size
         FROM $files_table
         WHERE user_id = %d
         GROUP BY file_type",
        $user_id
    ));
    
    // Get largest files
    $largest_files = $wpdb->get_results($wpdb->prepare(
        "SELECT f.file_name, f.file_size, p.project_name
         FROM $files_table f
         LEFT JOIN $projects_table p ON f.project_id = p.id
         WHERE f.user_id = %d
         ORDER BY f.file_size DESC
         LIMIT 5",
        $user_id
    ));
    
    // Get recent uploads
    $recent_files = $wpdb->get_results($wpdb->prepare(
        "SELECT f.file_name, f.file_size, f.upload_date, p.project_name
         FROM $files_table f
         LEFT JOIN $projects_table p ON f.project_id = p.id
         WHERE f.user_id = %d
         ORDER BY f.upload_date DESC
         LIMIT 5",
        $user_id
    ));
    
    // Calculate max file size
    $max_file_size = get_option('ufs_max_file_size', 10) * 1048576;
    
    error_log('Stats retrieved successfully'); // DEBUG
    
    wp_send_json_success(array(
        'quota_limit' => $quota->quota_limit,
        'used_space' => $quota->used_space,
        'available_space' => $quota->quota_limit - $quota->used_space,
        'usage_percentage' => ($quota->quota_limit > 0) ? ($quota->used_space / $quota->quota_limit) * 100 : 0,
        'total_files' => $file_stats->total_files,
        'project_count' => $file_stats->project_count,
        'accessible_projects' => $accessible_projects,
        'file_types' => $file_types,
        'largest_files' => $largest_files,
        'recent_files' => $recent_files,
        'max_file_size' => $max_file_size,
        'max_file_size_mb' => get_option('ufs_max_file_size', 10)
    ));
}
    
    /**
     * Render storage dashboard HTML
     */
    public static function render_dashboard() {
        ?>
        <div id="storage-dashboard" class="storage-dashboard">
            <div class="dashboard-header">
                <h3><?php _e('Storage Overview', 'user-file-storage'); ?></h3>
                <button id="refresh-stats" class="btn btn-secondary btn-sm">
                    <?php _e('🔄 Refresh', 'user-file-storage'); ?>
                </button>
            </div>
            
            <!-- Main Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">💾</div>
                    <div class="stat-info">
                        <div class="stat-label"><?php _e('Storage Used', 'user-file-storage'); ?></div>
                        <div class="stat-value" id="used-storage">-- MB</div>
                        <div class="stat-subtext" id="storage-limit">of -- MB</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📁</div>
                    <div class="stat-info">
                        <div class="stat-label"><?php _e('Total Files', 'user-file-storage'); ?></div>
                        <div class="stat-value" id="total-files">0</div>
                        <div class="stat-subtext" id="accessible-projects">in 0 projects</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-info">
                        <div class="stat-label"><?php _e('Max File Size', 'user-file-storage'); ?></div>
                        <div class="stat-value" id="max-file-size">-- MB</div>
                        <div class="stat-subtext"><?php _e('per file', 'user-file-storage'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Storage Progress Bar -->
            <div class="storage-progress-section">
                <div class="progress-header">
                    <span><?php _e('Storage Usage', 'user-file-storage'); ?></span>
                    <span id="usage-percentage">0%</span>
                </div>
                <div class="storage-bar">
                    <div class="storage-progress" id="storage-progress-bar" style="width: 0%"></div>
                </div>
            </div>

            <!-- File Type Breakdown /// removed for now -->
            <!--<div class="file-types-section">-->
            <!--    <h4><?php _e('Files by Type', 'user-file-storage'); ?></h4>-->
            <!--    <div id="file-types-list" class="file-types-grid">-->
            <!--        <p><?php _e('Loading...', 'user-file-storage'); ?></p>-->
            <!--    </div>-->
            <!--</div>-->

            <!-- Recent Activity -->
            <div class="dashboard-row">
                <div class="dashboard-column">
                    <div class="collapsible-header" onclick="toggleSection('recent-files')">
                        <h4>بارگذاری‌های اخیر</h4>
                        <span class="toggle-icon" id="recent-files-toggle">▶</span>
                    </div>
                    <div id="recent-files-section" class="collapsible-content" style="display: none;">
                        <div id="recent-files-list" class="activity-list">
                            <p><?php _e('Loading...', 'user-file-storage'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-column">
                    <div class="collapsible-header" onclick="toggleSection('largest-files')">
                        <h4>Largest Files</h4>
                        <span class="toggle-icon" id="largest-files-toggle">▶</span>
                    </div>
                    <div id="largest-files-section" class="collapsible-content" style="display: none;">
                        <div id="largest-files-list" class="activity-list">
                            <p><?php _e('Loading...', 'user-file-storage'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
                .collapsible-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 6px;
            margin-bottom: 10px;
            transition: background 0.2s;
        }
        
        .collapsible-header:hover {
            background: #e0e0e0;
        }
        
        .collapsible-header h4 {
            margin: 0;
        }
        
        .toggle-icon {
            font-size: 14px;
            transition: transform 0.3s;
        }
        
        .collapsible-content {
            overflow: hidden;
        }
        .storage-dashboard {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .stat-icon {
            font-size: 32px;
            display: flex;
            align-items: center;
        }
        
        .stat-info {
            flex: 1;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        
        .stat-subtext {
            font-size: 12px;
            color: #999;
        }
        
        .storage-progress-section {
            margin-bottom: 25px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .storage-bar {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .storage-progress {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #FFC107, #f44336);
            transition: width 0.5s ease;
        }
        
        .file-types-section {
            margin-bottom: 25px;
        }
        
        .file-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .file-type-item {
            padding: 15px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            text-align: center;
        }
        
        .file-type-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .file-type-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .file-type-count {
            font-size: 12px;
            color: #666;
        }
        
        .file-type-size {
            font-size: 11px;
            color: #999;
        }
        
        .dashboard-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .dashboard-column h4 {
            margin-bottom: 15px;
        }
        
        .activity-list {
            background: #f9f9f9;
            border-radius: 6px;
            padding: 15px;
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-file-name {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .activity-meta {
            font-size: 12px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-row {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Load storage stats on page load
            loadStorageStats();
            
            $('#refresh-stats').on('click', function() {
                loadStorageStats();
            });
            
            function loadStorageStats() {
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_storage_stats',
                        nonce: ufs_ajax.nonces.get_storage_stats
                    },
                    success: function(response) {
                        if (response.success) {
                            displayStorageStats(response.data);
                        } else {
                            console.error('Storage stats error:', response.data);
                            $('#recent-files-list').html('<p>Error loading data</p>');
                            $('#largest-files-list').html('<p>Error loading data</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        $('#recent-files-list').html('<p>Error loading data</p>');
                        $('#largest-files-list').html('<p>Error loading data</p>');
                    }
                });
            }
            
            function displayStorageStats(data) {
                // Main stats
                $('#used-storage').text(formatBytes(data.used_space));
                $('#storage-limit').text('of ' + formatBytes(data.quota_limit));
                $('#total-files').text(data.total_files);
                $('#accessible-projects').text('in ' + data.accessible_projects + ' projects');
                $('#max-file-size').text(data.max_file_size_mb + ' MB');
                
                // Progress bar
                var percentage = Math.min(data.usage_percentage, 100);
                $('#storage-progress-bar').css('width', percentage + '%');
                $('#usage-percentage').text(percentage.toFixed(1) + '%');
                
                // File types // removed for now
             //   displayFileTypes(data.file_types);
                
                // Recent files
                displayRecentFiles(data.recent_files);
                
                // Largest files
                displayLargestFiles(data.largest_files);
            }
            // removed for nwo.
            // function displayFileTypes(types) {
            //     if (types.length === 0) {
            //         $('#file-types-list').html('<p>No files yet</p>');
            //         return;
            //     }
                
            //     var icons = {
            //         'Images': '🖼️',
            //         'PDFs': '📄',
            //         'Documents': '📝',
            //         'Archives': '📦',
            //         'CAD Files': '📐',
            //         'Other': '📁'
            //     };
                
            //     var html = '';
            //     types.forEach(function(type) {
            //         html += '<div class="file-type-item">' +
            //                 '<div class="file-type-icon">' + (icons[type.file_type] || '📁') + '</div>' +
            //                 '<div class="file-type-name">' + type.file_type + '</div>' +
            //                 '<div class="file-type-count">' + type.count + ' files</div>' +
            //                 '<div class="file-type-size">' + formatBytes(type.size) + '</div>' +
            //                 '</div>';
            //     });
                
            //     $('#file-types-list').html(html);
            // }
            
            function displayRecentFiles(files) {
                if (files.length === 0) {
                    $('#recent-files-list').html('<p><em>No recent files</em></p>');
                    return;
                }
                
                var html = '';
                files.forEach(function(file) {
                    var date = new Date(file.upload_date).toLocaleDateString();
                    html += '<div class="activity-item">' +
                            '<div class="activity-file-name">' + file.file_name + '</div>' +
                            '<div class="activity-meta">' +
                            formatBytes(file.file_size) + ' • ' + date;
                    if (file.project_name) {
                        html += '<br>Project: ' + file.project_name;
                    }
                    html += '</div></div>';
                });
                
                $('#recent-files-list').html(html);
            }
            
            function displayLargestFiles(files) {
                if (files.length === 0) {
                    $('#largest-files-list').html('<p><em>No files</em></p>');
                    return;
                }
                
                var html = '';
                files.forEach(function(file) {
                    html += '<div class="activity-item">' +
                            '<div class="activity-file-name">' + file.file_name + '</div>' +
                            '<div class="activity-meta">' +
                            formatBytes(file.file_size);
                    if (file.project_name) {
                        html += '<br>Project: ' + file.project_name;
                    }
                    html += '</div></div>';
                });
                
                $('#largest-files-list').html(html);
            }
            
            function formatBytes(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            }
            
            // Toggle collapsible sections
            window.toggleSection = function(sectionId) {
                var content = $('#' + sectionId + '-section');
                var icon = $('#' + sectionId + '-toggle');
                
                if (content.is(':visible')) {
                    content.slideUp(300);
                    icon.text('▶');
                } else {
                    content.slideDown(300);
                    icon.text('▼');
                }
            }
        });
        </script>
        <?php
    }
}

new UFS_Storage_Dashboard();
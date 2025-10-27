<?php
/**
 * Projects Frontend Interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class UFS_Projects_Frontend {
    
    public function __construct() {
        add_action('woocommerce_account_file-storage_endpoint', array($this, 'render_projects_interface'), 5);
    }
    
    public function render_projects_interface() {
        if (!is_user_logged_in()) {
            echo '<p>Please log in to access your projects.</p>';
            return;
        }
        
        ?>  
        <div id="user-projects-system">
          <!-- ADD STORAGE DASHBOARD HERE - Before projects navigation -->
            <?php UFS_Storage_Dashboard::render_dashboard(); ?>
            
            <!-- Projects Navigation -->
        <div class="projects-header">
            <h3><?php _e('My Projects', 'user-file-storage'); ?></h3>
            <div class="header-actions">
                <button id="view-activity-log-btn" class="btn btn-secondary">
                    <?php _e('📊 Activity Log', 'user-file-storage'); ?>
                </button>
                <button id="create-project-btn" class="btn btn-primary">
                    <?php _e('+ New Project', 'user-file-storage'); ?>
                </button>
            </div>
        </div>
            <!-- Projects Filter -->
            <div class="projects-filter">
                <select id="project-status-filter">
                    <option value=""><?php _e('All Projects', 'user-file-storage'); ?></option>
                    <option value="active"><?php _e('Active', 'user-file-storage'); ?></option>
                    <option value="completed"><?php _e('Completed', 'user-file-storage'); ?></option>
                    <option value="archived"><?php _e('Archived', 'user-file-storage'); ?></option>
                </select>
            </div>
            
            <!-- Projects List -->
            <div id="projects-list" class="projects-grid">
                <p><?php _e('Loading projects...', 'user-file-storage'); ?></p>
            </div>
            
            <!-- Project Details View (hidden by default) -->
            <div id="project-details-view" style="display: none;">
                <div class="project-details-header">
                    <button id="back-to-projects" class="btn btn-secondary">
                        <?php _e('← Back to Projects', 'user-file-storage'); ?>
                    </button>
                    <div class="project-actions">
                        <button id="edit-project-btn" class="btn btn-secondary">
                            <?php _e('Edit Project', 'user-file-storage'); ?>
                        </button>
                        <button id="share-project-btn" class="btn btn-secondary">
                            <?php _e('👥 Share with Users', 'user-file-storage'); ?>
                        </button>

                        <button id="delete-project-btn" class="btn btn-danger">
                            <?php _e('Delete Project', 'user-file-storage'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="project-info-bar">
                    <h3 id="project-title"></h3>
                    <div class="project-meta">
                        <span id="project-status-badge"></span>
                        <span id="project-file-count"></span>
                        <span id="project-member-count"></span>
                    </div>
                    <p id="project-description"></p>
                </div>
                
                <!-- Folder Management -->
                <div class="folder-section">
                    <div class="folder-toolbar">
                        <button id="create-folder-btn" class="btn btn-sm btn-secondary">
                            <?php _e('+ New Folder', 'user-file-storage'); ?>
                        </button>
                        <select id="current-folder-select">
                            <option value=""><?php _e('Root Folder', 'user-file-storage'); ?></option>
                        </select>
                    </div>
                </div>
                
                <!-- File Upload Section -->
                <div class="project-upload-section">
                    <form id="project-file-upload-form" enctype="multipart/form-data">
                        <input type="hidden" id="upload-project-id">
                        <input type="hidden" id="upload-folder-id">
                        <input type="file" id="project-file-input" name="user_file">
                        <button type="submit"><?php _e('Upload File', 'user-file-storage'); ?></button>
                    </form>
                </div>
                
                <!-- Project Files List -->
                <div id="project-files-list">
                    <p><?php _e('Loading files...', 'user-file-storage'); ?></p>
                </div>
                
                <!-- Project Members List -->
                <div class="project-members-section">
                    <h4><?php _e('Project Team', 'user-file-storage'); ?></h4>
                    <div id="project-members-list"></div>
                </div>
            </div>
            
            <!-- Create/Edit Project Modal -->
            <div id="project-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h4 id="project-modal-title"><?php _e('Create New Project', 'user-file-storage'); ?></h4>
                    <form id="project-form">
                        <input type="hidden" id="edit-project-id">
                        
                        <label><?php _e('Project Name *', 'user-file-storage'); ?></label>
                        <input type="text" id="project-name" required>
                        
                        <label><?php _e('Description', 'user-file-storage'); ?></label>
                        <textarea id="project-description" rows="4"></textarea>
                        
                        <label><?php _e('Project Type', 'user-file-storage'); ?></label>
                        <select id="project-type">
                            <option value=""><?php _e('Select Type', 'user-file-storage'); ?></option>
                            <option value="residential"><?php _e('Residential Construction', 'user-file-storage'); ?></option>
                            <option value="commercial"><?php _e('Commercial Construction', 'user-file-storage'); ?></option>
                            <option value="renovation"><?php _e('Renovation', 'user-file-storage'); ?></option>
                            <option value="infrastructure"><?php _e('Infrastructure', 'user-file-storage'); ?></option>
                            <option value="other"><?php _e('Other', 'user-file-storage'); ?></option>
                        </select>
                        
                        <label><?php _e('Status', 'user-file-storage'); ?></label>
                        <select id="project-status">
                            <option value="active"><?php _e('Active', 'user-file-storage'); ?></option>
                            <option value="completed"><?php _e('Completed', 'user-file-storage'); ?></option>
                            <option value="archived"><?php _e('Archived', 'user-file-storage'); ?></option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">
                            <?php _e('Save Project', 'user-file-storage'); ?>
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Share Project Modal -->
            <div id="share-project-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h4><?php _e('Share Project', 'user-file-storage'); ?></h4>
                    <form id="share-project-form">
                        <input type="hidden" id="share-project-id">
                        <input type="hidden" id="share-selected-user-id">
                        
                        <label><?php _e('Search Users', 'user-file-storage'); ?></label>
                        <input type="text" id="share-user-search" placeholder="<?php _e('Type username or email...', 'user-file-storage'); ?>">
                        <div id="share-user-results"></div>
                        
                        <label><?php _e('Role', 'user-file-storage'); ?></label>
                        <select id="member-role">
                            <option value="owner"><?php _e('Owner', 'user-file-storage'); ?></option>
                            <option value="engineer"><?php _e('Engineer', 'user-file-storage'); ?></option>
                            <option value="surveyor"><?php _e('Surveyor', 'user-file-storage'); ?></option>
                            <option value="contractor"><?php _e('Contractor', 'user-file-storage'); ?></option>
                            <option value="architect"><?php _e('Architect', 'user-file-storage'); ?></option>
                            <option value="member"><?php _e('Member', 'user-file-storage'); ?></option>
                        </select>
                        
                        <label><?php _e('Permission Level', 'user-file-storage'); ?></label>
                        <select id="member-permission">
                            <option value="view"><?php _e('View Only', 'user-file-storage'); ?></option>
                            <option value="download"><?php _e('View & Download', 'user-file-storage'); ?></option>
                            <option value="upload"><?php _e('View, Download & Upload', 'user-file-storage'); ?></option>
                            <option value="manage"><?php _e('Full Management', 'user-file-storage'); ?></option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">
                            <?php _e('Add Member', 'user-file-storage'); ?>
                        </button>
                    </form>
                    
                    <div class="current-members">
                        <h5><?php _e('Current Team Members', 'user-file-storage'); ?></h5>
                        <div id="current-project-members"></div>
                    </div>
                </div>
            </div>
            
            <!-- Create Folder Modal -->
            <div id="folder-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h4><?php _e('Create New Folder', 'user-file-storage'); ?></h4>
                    <form id="folder-form">
                        <input type="hidden" id="folder-project-id">
                        <input type="hidden" id="folder-parent-id">
                        
                        <label><?php _e('Folder Name', 'user-file-storage'); ?></label>
                        <input type="text" id="folder-name" required>
                        
                        <button type="submit" class="btn btn-primary">
                            <?php _e('Create Folder', 'user-file-storage'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <?php
        $this->add_projects_styles();
        $this->add_projects_javascript();
    }
    
    private function add_projects_styles() {
        ?>
        <style>
        /* Projects Styles */
        #user-projects-system {
            max-width: 1200px;
        }
        
        .projects-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .projects-filter {
            margin-bottom: 20px;
        }
        
        .projects-filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 200px;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        /* Responsive - 2 columns on tablets */
        @media (max-width: 1200px) {
            .projects-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Responsive - 1 column on mobile */
        @media (max-width: 768px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .project-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: white;  
            transition: all 0.3s ease;
            cursor: pointer;
            min-height: 200px; /* Increased from 180px */
            display: flex;
            flex-direction: column;
        }
        
        .project-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-4px);
            border-color: #0073aa;
        }
                
        .project-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .project-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .project-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .project-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .project-status.completed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .project-status.archived {
            background: #f8d7da;
            color: #721c24;
        }
        
        .project-description {
            color: #666;
            font-size: 14px;
            margin: 10px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .project-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #666;
        }
        
        .project-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .project-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .project-actions {
            display: flex;
            gap: 10px;
        }
        
        .project-info-bar {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .project-meta {
            display: flex;
            gap: 15px;
            margin: 10px 0;
            flex-wrap: wrap;
        }
        
        .folder-section {
            background: white;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .folder-toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .folder-toolbar select {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .project-upload-section {
            background: #f0f8ff;
            border: 2px dashed #0073aa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .project-upload-section form {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }
        
        .project-members-section {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .member-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-role {
            display: inline-block;
            padding: 3px 8px;
            background: #0073aa;
            color: white;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 10px;
        }
        
        .member-permission {
            color: #666;
            font-size: 12px;
            margin-left: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
            
            .project-details-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .project-actions {
                width: 100%;
                flex-direction: column;
            }
        }
        </style>
        <?php
    }
    
    private function add_projects_javascript() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            let currentProjectId = null;
            let currentFolderId = null;
            
            // Load projects on page load
            loadProjects();
            
            // Create project
            $('#create-project-btn').on('click', function() {
                $('#project-modal-title').text('Create New Project');
                $('#edit-project-id').val('');
                $('#project-form')[0].reset();
                $('#project-modal').show();
            });
            
            // Submit project form
            $('#project-form').on('submit', function(e) {
                e.preventDefault();
                saveProject();
            });
            
            // Filter projects
            $('#project-status-filter').on('change', function() {
                loadProjects();
            });
            
            // Back to projects
            $('#back-to-projects').on('click', function() {
                $('#project-details-view').hide();
                $('#projects-list').show();
                $('.projects-header, .projects-filter').show();
                currentProjectId = null;
            });
            
            // Edit project
            $('#edit-project-btn').on('click', function() {
                editProject(currentProjectId);
            });
            
            // Delete project
            $('#delete-project-btn').on('click', function() {
                deleteProject(currentProjectId);
            });
            
            // // Share project
            // $('#share-project-btn').on('click', function() {
            //     $('#share-project-id').val(currentProjectId);
            //     $('#share-project-modal').show();
            //     loadProjectMembers(currentProjectId);
            // });
            
            // Share project form
            $('#share-project-form').on('submit', function(e) {
                e.preventDefault();
                shareProject();
            });
            
            // User search for sharing
            $('#share-user-search').on('input', function() {
                var search = $(this).val();
                if (search.length < 2) {
                    $('#share-user-results').hide().empty();
                    return;
                }
                searchUsersForProject(search);
            });
            
            // Create folder
            $('#create-folder-btn').on('click', function() {
                $('#folder-project-id').val(currentProjectId);
                $('#folder-parent-id').val(currentFolderId || '');
                $('#folder-modal').show();
            });
            
            // Submit folder form
            $('#folder-form').on('submit', function(e) {
                e.preventDefault();
                createFolder();
            });
            
            // Change folder
            $('#current-folder-select').on('change', function() {
                currentFolderId = $(this).val() || null;
                loadProjectDetails(currentProjectId);
            });
            
            // Upload file to project
            $('#project-file-upload-form').on('submit', function(e) {
                e.preventDefault();
                uploadProjectFile();
            });
            
            // Modal close
            $('.close').on('click', function() {
                $(this).closest('.modal').hide();
            });
            
            $(window).on('click', function(e) {
                if ($(e.target).hasClass('modal')) {
                    $('.modal').hide();
                }
            });
            
            // Functions
            function loadProjects() {
                var status = $('#project-status-filter').val();
                
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_user_projects',
                        filter_status: status,
                        nonce: ufs_ajax.nonces.get_user_projects
                    },
                    success: function(response) {
                        if (response.success) {
                            displayProjects(response.data.owned, response.data.shared);
                        }
                    }
                });
            }
            
        function displayProjects(owned, shared) {
            var html = '';
            
            if (owned.length === 0 && shared.length === 0) {
                html = '<div style="text-align: center; padding: 40px;"><p>No projects found. Create your first project!</p></div>';
            } else {
                if (owned.length > 0) {
                    html += '<div class="projects-section-wrapper">';
                    html += '<h4 class="section-title">My Projects</h4>';
                    html += '<div class="projects-grid">';  // IMPORTANT: Add this wrapper
                    owned.forEach(function(project) {
                        html += renderProjectCard(project, false);
                    });
                    html += '</div>';  // Close projects-grid
                    html += '</div>';  // Close projects-section-wrapper
                }
                
                if (shared.length > 0) {
                    html += '<div class="projects-section-wrapper" style="margin-top: 30px;">';
                    html += '<h4 class="section-title">Shared with Me</h4>';
                    html += '<div class="projects-grid">';  // IMPORTANT: Add this wrapper
                    shared.forEach(function(project) {
                        html += renderProjectCard(project, true);
                    });
                    html += '</div>';  // Close projects-grid
                    html += '</div>';  // Close projects-section-wrapper
                }
            }
            
            $('#projects-list').html(html);
        }
            
            function renderProjectCard(project, isShared) {
                var sharedClass = isShared ? 'shared' : '';
                var ownerInfo = isShared ? '<small>Owner: ' + project.owner_name + '</small>' : '';
                
                return `
                    <div class="project-card ${sharedClass}" onclick="viewProject(${project.id})">
                        <div class="project-card-header">
                            <div>
                                <div class="project-name">${project.project_name}</div>
                                ${ownerInfo}
                            </div>
                            <span class="project-status ${project.status}">${project.status}</span>
                        </div>
                        <div class="project-description">${project.project_description || 'No description'}</div>
                        <div class="project-stats">


<span class="project-stat">
                                📄 ${project.file_count || 0} files
                            </span>
                            <span class="project-stat">
                                👥 ${project.member_count || 0} members
                            </span>
                            ${project.project_type ? `<span class="project-stat">📋 ${project.project_type}</span>` : ''}
                        </div>
                    </div>
                `;
            }
            
            window.viewProject = function(projectId) {
                currentProjectId = projectId;
                loadProjectDetails(projectId);
                $('#projects-list').hide();
                $('.projects-header, .projects-filter').hide();
                $('#project-details-view').show();
            }
            
            function loadProjectDetails(projectId) {
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_project_details',
                        project_id: projectId,
                        nonce: ufs_ajax.nonces.get_project_details
                    },
                    success: function(response) {
                        if (response.success) {
                            displayProjectDetails(response.data);
                        }
                    }
                });
            }
            
            function displayProjectDetails(data) {
                $('#project-title').text(data.project.project_name);
                $('#project-description').text(data.project.project_description || 'No description');
                $('#project-status-badge').html('<span class="project-status ' + data.project.status + '">' + data.project.status + '</span>');
                $('#project-file-count').text('📄 ' + data.files.length + ' files');
                $('#project-member-count').text('👥 ' + (data.members ? data.members.length : 0) + ' members');
                
                // Show/hide buttons based on permission

                if (data.permission === 'manage') {
                    // $('#edit-project-btn, #delete-project-btn, #share-project-btn, #share-link-btn').show();
                    $('#create-folder-btn').show();
                } else if (data.permission === 'upload') {
                    $('#edit-project-btn, #delete-project-btn, #share-project-btn, #share-link-btn').hide();
                    $('#create-folder-btn').show();
                } else {
                    $('#edit-project-btn, #delete-project-btn, #share-project-btn, #share-link-btn').hide();
                    $('#create-folder-btn').hide();
                }
                // Upload section visibility
                if (['upload', 'manage'].includes(data.permission)) {
                    $('.project-upload-section').show();
                } else {
                    $('.project-upload-section').hide();
                }
                
                // Set project ID for upload
                $('#upload-project-id').val(data.project.id);
                $('#upload-folder-id').val(currentFolderId || '');
                
                // Display folders
                displayFolders(data.folders);
                
                // Display files
                displayProjectFiles(data.files, data.permission);
            }
            
            function displayFolders(folders) {
                var html = '<option value="">Root Folder</option>';
                folders.forEach(function(folder) {
                    html += '<option value="' + folder.id + '">' + folder.folder_name + '</option>';
                });
                $('#current-folder-select').html(html);
            }
            
            function displayProjectFiles(files, permission) {
                if (files.length === 0) {
                    $('#project-files-list').html('<p>No files in this project yet.</p>');
                    return;
                }
                
                var html = '<h4>Project Files</h4>';
                
                // Group by folder
                var filesByFolder = {};
                files.forEach(function(file) {
                    var folderName = file.folder_name || 'Root';
                    if (!filesByFolder[folderName]) {
                        filesByFolder[folderName] = [];
                    }
                    filesByFolder[folderName].push(file);
                });
                
                // Display files by folder
                Object.keys(filesByFolder).forEach(function(folderName) {
                    html += '<div class="folder-group"><h5>📁 ' + folderName + '</h5>';
                    
                    filesByFolder[folderName].forEach(function(file) {
                        var canDownload = ['download', 'upload', 'manage'].includes(permission);
                        var fileIcon = getFileIcon(file.mime_type);
                        var fileSize = formatBytes(file.file_size);
                        var uploadDate = new Date(file.upload_date).toLocaleDateString();
                        
                        html += `
                            <div class="file-item">
                                <div class="file-icon">${fileIcon}</div>
                                <div class="file-info">
                                    <div class="file-name">${file.file_name}</div>
                                    <div class="file-meta">
                                        ${fileSize} • ${uploadDate} • Uploaded by ${file.uploader_name}
                                    </div>
                                </div>
                                <div class="file-actions">
                                    ${canDownload ? '<button class="btn btn-primary btn-sm" onclick="downloadFile(' + file.id + ')">Download</button>' : '<span class="view-only">View Only</span>'}
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                });
                
                $('#project-files-list').html(html);
            }
            
            function saveProject() {
                var projectId = $('#edit-project-id').val();
                var isEdit = projectId !== '';
                
                var data = {
                    action: isEdit ? 'update_project' : 'create_project',
                    nonce: isEdit ? ufs_ajax.nonces.update_project : ufs_ajax.nonces.create_project,
                    project_name: $('#project-name').val(),
                    project_description: $('#project-description').val(),
                    project_type: $('#project-type').val(),
                    status: $('#project-status').val()
                };
                    // DEBUG - Check what's being sent
                console.log('Sending data:', data);
                console.log('Available nonces:', ufs_ajax.nonces);
                
                if (isEdit) {
                    data.project_id = projectId;
                }
                
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            alert(isEdit ? 'Project updated!' : 'Project created!');
                            $('#project-modal').hide();
                            if (isEdit) {
                                loadProjectDetails(projectId);
                            } else {
                                loadProjects();
                            }
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            }
            
            function editProject(projectId) {
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_project_details',
                        project_id: projectId,
                        nonce: ufs_ajax.nonces.get_project_details
                    },
                    success: function(response) {
                        if (response.success) {
                            var project = response.data.project;
                            $('#project-modal-title').text('Edit Project');
                            $('#edit-project-id').val(project.id);
                            $('#project-name').val(project.project_name);
                            $('#project-description').val(project.project_description);
                            $('#project-type').val(project.project_type);
                            $('#project-status').val(project.status);
                            $('#project-modal').show();
                        }
                    }
                });
            }
            
            function deleteProject(projectId) {
                if (!confirm('Are you sure you want to delete this project? All files will be deleted!')) {
                    return;
                }
                
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'delete_project',
                        project_id: projectId,
                        nonce: ufs_ajax.nonces.delete_project
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Project deleted successfully!');
                            $('#back-to-projects').click();
                            loadProjects();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            }
            
            function searchUsersForProject(search) {
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'search_users',
                        search: search,
                        nonce: ufs_ajax.nonces.search_users
                    },
                    success: function(response) {
                        if (response.success) {
                            displayUserResults(response.data.users);
                        }
                    }
                });
            }
            
            function displayUserResults(users) {
                var html = '';
                users.forEach(function(user) {
                    html += '<div class="user-item" onclick="selectUserForProject(' + user.id + ', \'' + 
                            user.name.replace(/'/g, "\\'") + '\', \'' + user.email + '\')">' +
                            '<strong>' + user.name + '</strong><br>' +
                            '<small>' + user.email + ' (' + user.username + ')</small>' +
                            '</div>';
                });
                $('#share-user-results').html(html).show();
            }
            
            window.selectUserForProject = function(userId, name, email) {
                $('#share-selected-user-id').val(userId);
                $('#share-user-search').val(name + ' (' + email + ')');
                $('#share-user-results').hide().empty();
            }
            
            function shareProject() {
                var projectId = $('#share-project-id').val();
                var userId = $('#share-selected-user-id').val();
                var role = $('#member-role').val();
                var permission = $('#member-permission').val();
                
                if (!userId) {
                    alert('Please select a user');
                    return;
                }
                
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'share_project',
                        project_id: projectId,
                        target_user_id: userId,
                        role: role,
                        permission: permission,
                        nonce: ufs_ajax.nonces.share_project
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Project shared successfully!');
                            $('#share-user-search').val('');
                            $('#share-selected-user-id').val('');
                            loadProjectMembers(projectId);
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            }
            
            function loadProjectMembers(projectId) {
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_project_members',
                        project_id: projectId,
                        nonce: ufs_ajax.nonces.get_project_members
                    },
                    success: function(response) {
                        if (response.success) {
                            displayProjectMembers(response.data.members);
                        }
                    }
                });
            }
            
            function displayProjectMembers(members) {
                if (members.length === 0) {
                    $('#current-project-members').html('<p><em>No members yet</em></p>');
                    return;
                }
                
                var html = '';
                members.forEach(function(member) {
                    html += `
                        <div class="member-item">
                            <div class="member-info">
                                <strong>${member.display_name}</strong>
                                <span class="member-role">${member.role}</span>
                                <span class="member-permission">${member.permission_level}</span>
                                <br><small>${member.user_email}</small>
                            </div>
                            <button class="btn btn-danger btn-sm" onclick="removeMember(${$('#share-project-id').val()}, ${member.user_id})">
                                Remove
                            </button>
                        </div>
                    `;
                });
                
                $('#current-project-members').html(html);
                $('#project-members-list').html(html);
            }
            
            window.removeMember = function(projectId, userId) {
                if (!confirm('Remove this member?')) return;
                
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'remove_project_member',
                        project_id: projectId,
                        target_user_id: userId,
                        nonce: ufs_ajax.nonces.remove_project_member
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Member removed!');
                            loadProjectMembers(projectId);
                        }
                    }
                });
            }
            
            function createFolder() {
                var projectId = $('#folder-project-id').val();
                var folderName = $('#folder-name').val();
                var parentId = $('#folder-parent-id').val();
                
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'create_folder',
                        project_id: projectId,
                        folder_name: folderName,
                        parent_folder_id: parentId || null,
                        nonce: ufs_ajax.nonces.create_folder
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Folder created!');
                            $('#folder-modal').hide();
                            $('#folder-form')[0].reset();
                            loadProjectDetails(projectId);
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            }
            
            function uploadProjectFile() {
                var formData = new FormData();
                var fileInput = document.getElementById('project-file-input');
                var file = fileInput.files[0];
                
                if (!file) {
                    alert('Please select a file');
                    return;
                }
                
                var projectId = $('#upload-project-id').val();
                var folderId = $('#upload-folder-id').val();
                
                formData.append('user_file', file);
                formData.append('action', 'upload_user_file');
                formData.append('nonce', ufs_ajax.nonces.upload);
                formData.append('project_id', projectId);
                if (folderId) {
                    formData.append('folder_id', folderId);
                }
                
                $.ajax({
                    url: ufs_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('File uploaded successfully!');
                            $('#project-file-input').val('');
                            loadProjectDetails(projectId);
                        } else {
                            alert('Upload failed: ' + response.data);
                        }
                    }
                });
            }
            
            // Helper functions
            function getFileIcon(mimeType) {
                if (mimeType.startsWith('image/')) return '🖼️';
                if (mimeType === 'application/pdf') return '📄';
                if (mimeType.includes('word')) return '📝';
                if (mimeType.includes('zip') || mimeType.includes('rar')) return '📦';
                if (mimeType.includes('dwg') || mimeType.includes('dxf') || mimeType.includes('dwf')) return '📐';
                return '📁';
            }
            
            function formatBytes(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            }
            // Activity Log functionality
$('#view-activity-log-btn').on('click', function() {
    loadActivityLog();
    $('#activity-log-modal').show();
});

$('#activity-project-filter, #activity-type-filter').on('change', function() {
    loadActivityLog();
});

function loadActivityLog() {
    var projectId = $('#activity-project-filter').val();
    var activityType = $('#activity-type-filter').val();
    
    $.ajax({
        url: ufs_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'get_activity_log',
            project_id: projectId || null,
            activity_type: activityType || null,
            nonce: ufs_ajax.nonces.get_activity_log
        },
        success: function(response) {
            if (response.success) {
                displayActivityLog(response.data.activities);
            }
        }
    });
}

function displayActivityLog(activities) {
    if (activities.length === 0) {
        $('#activity-log-content').html('<p><em>No activities found</em></p>');
        return;
    }
    
    var html = '';
    activities.forEach(function(activity) {
        var icon = getActivityIcon(activity.action_type);
        var description = getActivityDescription(activity);
        var timestamp = new Date(activity.created_at).toLocaleString();
        
        html += `
            <div class="activity-log-item">
                <div class="activity-icon">${icon}</div>
                <div class="activity-content">
                    <div class="activity-description">${description}</div>
                    <div class="activity-meta">
                        <span class="activity-user">${activity.user_name}</span>
                        <span class="activity-time">${timestamp}</span>
                        ${activity.ip_address ? '<span class="activity-ip">IP: ' + activity.ip_address + '</span>' : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    $('#activity-log-content').html(html);
}

function getActivityIcon(actionType) {
    var icons = {
        'project_created': '✨',
        'project_shared': '🤝',
        'project_viewed': '👁️',
        'file_uploaded': '📤',
        'file_viewed': '👀',
        'file_downloaded': '📥',
        'folder_created': '📁',
        'member_added': '👤',
        'member_removed': '🚫'
    };
    return icons[actionType] || '📋';
}

function getActivityDescription(activity) {
    var descriptions = {
        'project_created': 'created project <strong>' + (activity.project_name || 'Unknown') + '</strong>',
        'project_shared': 'shared project <strong>' + (activity.project_name || 'Unknown') + '</strong> with ' + (activity.target_user_name || 'user'),
        'project_viewed': 'viewed project <strong>' + (activity.project_name || 'Unknown') + '</strong>',
        'file_uploaded': 'uploaded file <strong>' + (activity.file_name || 'Unknown') + '</strong>' + (activity.project_name ? ' to project <strong>' + activity.project_name + '</strong>' : ''),
        'file_viewed': 'viewed file <strong>' + (activity.file_name || 'Unknown') + '</strong>',
        'file_downloaded': 'downloaded file <strong>' + (activity.file_name || 'Unknown') + '</strong>',
        'folder_created': 'created a new folder',
        'member_added': 'added member to project',
        'member_removed': 'removed member from project'
    };
    
    return descriptions[activity.action_type] || activity.action_type;
}

// File statistics
window.showFileStats = function(fileId, fileName) {
    $('#file-stats-title').text('Statistics: ' + fileName);
    loadFileStatistics(fileId);
    $('#file-stats-modal').show();
}

function loadFileStatistics(fileId) {
    $.ajax({
        url: ufs_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'get_file_statistics',
            file_id: fileId,
            nonce: ufs_ajax.nonces.get_file_statistics
        },
        success: function(response) {
            if (response.success) {
                displayFileStatistics(response.data);
            }
        }
    });
}

function displayFileStatistics(data) {
    $('#stat-total-views').text(data.total_views);
    $('#stat-unique-viewers').text(data.unique_viewers);
    $('#stat-downloads').text(data.downloads);
    
    // Display viewers
    if (data.views.length === 0) {
        $('#file-viewers-list').html('<p><em>No views yet</em></p>');
    } else {
        var viewersHtml = '';
        data.views.forEach(function(view) {
            var firstViewed = new Date(view.first_viewed).toLocaleDateString();
            var lastViewed = new Date(view.last_viewed).toLocaleString();
            
            viewersHtml += `
                <div class="viewer-item">
                    <strong>${view.display_name}</strong> (${view.user_email})
                    <br><small>
                        First viewed: ${firstViewed} | 
                        Last viewed: ${lastViewed} | 
                        Views: ${view.view_count}
                    </small>
                </div>
            `;
        });
        $('#file-viewers-list').html(viewersHtml);
    }
    
    // Display recent activity
    if (data.recent_activity.length === 0) {
        $('#file-recent-activity').html('<p><em>No recent activity</em></p>');
    } else {
        var activityHtml = '';
        data.recent_activity.forEach(function(activity) {
            var timestamp = new Date(activity.created_at).toLocaleString();
            var icon = getActivityIcon(activity.action_type);
            
            activityHtml += `
                <div class="timeline-item">
                    <span class="timeline-icon">${icon}</span>
                    <div class="timeline-content">
                        <strong>${activity.display_name}</strong> ${activity.action_type.replace('_', ' ')}
                        <br><small>${timestamp}</small>
                    </div>
                </div>
            `;
        });
        $('#file-recent-activity').html(activityHtml);
    }
}

// Track file view when opening project details
var originalLoadProjectDetails = loadProjectDetails;
loadProjectDetails = function(projectId) {
    originalLoadProjectDetails(projectId);
    
    // Track project view
    $.ajax({
        url: ufs_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'view_file',
            project_id: projectId,
            nonce: ufs_ajax.nonces.view_file
        }
    });
}

    // Update file item rendering to include stats button
    var originalRenderFileItem = window.renderFileItem || function() {};
            });

        </script>
        <?php
    }
}
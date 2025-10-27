<?php
/**
 * Project Share Links Manager Class
 * Save as: includes/class-share-links-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class UFS_Share_Links_Manager {
    
    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_generate_share_link', array($this, 'generate_share_link'));
        add_action('wp_ajax_get_project_share_links', array($this, 'get_project_share_links'));
        add_action('wp_ajax_revoke_share_link', array($this, 'revoke_share_link'));
        add_action('wp_ajax_nopriv_access_shared_project', array($this, 'access_shared_project'));
        add_action('wp_ajax_access_shared_project', array($this, 'access_shared_project'));
        add_action('wp_ajax_nopriv_register_via_share_link', array($this, 'register_via_share_link'));
        
        // Handle share link access page
        add_action('init', array($this, 'handle_share_link_access'));
        add_action('template_redirect', array($this, 'redirect_after_share_login'));
        
        // Add nonces
        add_filter('ufs_ajax_nonces', array($this, 'add_share_link_nonces'));
    }

    public function add_share_link_nonces($nonces) {
        $nonces['generate_share_link'] = wp_create_nonce('generate_share_link');
        $nonces['get_project_share_links'] = wp_create_nonce('get_project_share_links');
        $nonces['revoke_share_link'] = wp_create_nonce('revoke_share_link');
        return $nonces;
    }

    /**
     * Generate a new share link for a project
     */
    public function generate_share_link() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }

        check_ajax_referer('generate_share_link', 'nonce');

        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();
        $permission_level = sanitize_text_field($_POST['permission_level'] ?? 'view');
        $max_uses = !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null;
        $expires_in_days = !empty($_POST['expires_in_days']) ? intval($_POST['expires_in_days']) : null;

        // Check if user can manage project
        if (!$this->user_can_manage_project($user_id, $project_id)) {
            wp_send_json_error('No permission to create share links for this project');
        }

        // Validate permission level
        if (!in_array($permission_level, array('view', 'download', 'upload', 'manage'))) {
            wp_send_json_error('Invalid permission level');
        }

        // Generate unique token
        $share_token = $this->generate_unique_token();

        // Calculate expiration date
        $expires_at = null;
        if ($expires_in_days) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_in_days} days"));
        }

        global $wpdb;
        $share_links_table = $wpdb->prefix . 'project_share_links';

        $result = $wpdb->insert(
            $share_links_table,
            array(
                'project_id' => $project_id,
                'share_token' => $share_token,
                'created_by' => $user_id,
                'permission_level' => $permission_level,
                'max_uses' => $max_uses,
                'expires_at' => $expires_at
            ),
            array('%d', '%s', '%d', '%s', '%d', '%s')
        );

        if ($result) {
            $share_url = home_url('/share-project/' . $share_token);
            
            wp_send_json_success(array(
                'share_link_id' => $wpdb->insert_id,
                'share_url' => $share_url,
                'message' => 'Share link generated successfully'
            ));
        } else {
            wp_send_json_error('Failed to generate share link');
        }
    }

    /**
     * Get all share links for a project
     */
    public function get_project_share_links() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }

        check_ajax_referer('get_project_share_links', 'nonce');

        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();

        if (!$this->user_can_manage_project($user_id, $project_id)) {
            wp_send_json_error('No permission to view share links');
        }

        global $wpdb;
        $share_links_table = $wpdb->prefix . 'project_share_links';

        $links = $wpdb->get_results($wpdb->prepare("
            SELECT sl.*, u.display_name as creator_name
            FROM $share_links_table sl
            JOIN {$wpdb->users} u ON sl.created_by = u.ID
            WHERE sl.project_id = %d AND sl.is_active = 1
            ORDER BY sl.created_at DESC
        ", $project_id));

        $results = array();
        foreach ($links as $link) {
            $results[] = array(
                'id' => $link->id,
                'share_url' => home_url('/share-project/' . $link->share_token),
                'permission_level' => $link->permission_level,
                'max_uses' => $link->max_uses,
                'current_uses' => $link->current_uses,
                'expires_at' => $link->expires_at,
                'created_by' => $link->creator_name,
                'created_at' => $link->created_at,
                'is_expired' => $this->is_link_expired($link)
            );
        }

        wp_send_json_success(array('links' => $results));
    }

    /**
     * Revoke a share link
     */
    public function revoke_share_link() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }

        check_ajax_referer('revoke_share_link', 'nonce');

        $link_id = intval($_POST['link_id']);
        $user_id = get_current_user_id();

        global $wpdb;
        $share_links_table = $wpdb->prefix . 'project_share_links';

        // Get link details
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $share_links_table WHERE id = %d",
            $link_id
        ));

        if (!$link) {
            wp_send_json_error('Share link not found');
        }

        // Check permission
        if (!$this->user_can_manage_project($user_id, $link->project_id)) {
            wp_send_json_error('No permission to revoke this link');
        }

        // Deactivate the link
        $wpdb->update(
            $share_links_table,
            array('is_active' => 0),
            array('id' => $link_id),
            array('%d'),
            array('%d')
        );

        wp_send_json_success('Share link revoked successfully');
    }

    /**
     * Handle share link access from URL
     */
    public function handle_share_link_access() {
        // Check if this is a share link URL
        $request_uri = $_SERVER['REQUEST_URI'];
        if (preg_match('#/share-project/([a-zA-Z0-9]+)#', $request_uri, $matches)) {
            $share_token = $matches[1];
            $this->display_share_link_page($share_token);
        }
    }

    /**
     * Display the share link access page
     */
    private function display_share_link_page($share_token) {
        global $wpdb;
        $share_links_table = $wpdb->prefix . 'project_share_links';

        // Get share link details
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $share_links_table WHERE share_token = %s AND is_active = 1",
            $share_token
        ));

        if (!$link) {
            wp_die('Invalid or expired share link');
        }

        // Check if link is expired
        if ($this->is_link_expired($link)) {
            wp_die('This share link has expired');
        }

        // Check max uses
        if ($link->max_uses && $link->current_uses >= $link->max_uses) {
            wp_die('This share link has reached its maximum usage limit');
        }

        // Get project details
        $projects_table = $wpdb->prefix . 'user_projects';
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $projects_table WHERE id = %d",
            $link->project_id
        ));

        if (!$project) {
            wp_die('Project not found');
        }

        // If user is logged in, grant access and redirect
        if (is_user_logged_in()) {
            $this->grant_project_access(get_current_user_id(), $link, $project);
            exit;
        }

        // Show login/register page
        $this->render_share_access_page($share_token, $project, $link);
        exit;
    }

    /**
     * Render the share link access page for non-logged-in users
     */
    private function render_share_access_page($share_token, $project, $link) {
        get_header();
        ?>
        <div class="share-project-access" style="max-width: 600px; margin: 50px auto; padding: 20px;">
            <div class="project-info" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <h2>🎉 You've been invited to a project!</h2>
                <h3><?php echo esc_html($project->project_name); ?></h3>
                <p><?php echo esc_html($project->project_description); ?></p>
                <p><strong>Permission Level:</strong> <?php echo ucfirst($link->permission_level); ?></p>
            </div>

            <div class="access-options" style="display: flex; gap: 20px; margin-bottom: 30px;">
                <button onclick="showLoginForm()" class="btn-option" style="flex: 1; padding: 15px; background: #0073aa; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                    I have an account - Login
                </button>
                <button onclick="showRegisterForm()" class="btn-option" style="flex: 1; padding: 15px; background: #46b450; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                    Create New Account
                </button>
            </div>

            <!-- Login Form -->
            <div id="login-form" style="display: none;">
                <h3>Login to Access Project</h3>
                <form method="post" action="<?php echo wp_login_url(); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url(home_url('/share-project/' . $share_token)); ?>">
                    <p>
                        <label>Username or Email</label><br>
                        <input type="text" name="log" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </p>
                    <p>
                        <label>Password</label><br>
                        <input type="password" name="pwd" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </p>
                    <p>
                        <button type="submit" style="background: #0073aa; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">Login</button>
                    </p>
                </form>
            </div>

            <!-- Register Form -->
            <div id="register-form" style="display: none;">
                <h3>Create Account to Access Project</h3>
                <form id="share-register-form">
                    <input type="hidden" name="share_token" value="<?php echo esc_attr($share_token); ?>">
                    <p>
                        <label>Email *</label><br>
                        <input type="email" name="user_email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </p>
                    <p>
                        <label>Username *</label><br>
                        <input type="text" name="user_login" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </p>
                    <p>
                        <label>Password *</label><br>
                        <input type="password" name="user_pass" required minlength="6" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </p>
                    <p>
                        <label>Full Name</label><br>
                        <input type="text" name="display_name" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </p>
                    <p>
                        <button type="submit" style="background: #46b450; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">Create Account & Access Project</button>
                    </p>
                    <div id="register-message" style="margin-top: 15px;"></div>
                </form>
            </div>
        </div>

        <script>
        function showLoginForm() {
            document.getElementById('login-form').style.display = 'block';
            document.getElementById('register-form').style.display = 'none';
        }

        function showRegisterForm() {
            document.getElementById('register-form').style.display = 'block';
            document.getElementById('login-form').style.display = 'none';
        }

        // Handle registration via AJAX
        document.getElementById('share-register-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'register_via_share_link');
            
            var messageDiv = document.getElementById('register-message');
            messageDiv.innerHTML = '<p style="color: blue;">Creating your account...</p>';
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.innerHTML = '<p style="color: green;">Account created! Redirecting...</p>';
                    window.location.href = data.data.redirect_url;
                } else {
                    messageDiv.innerHTML = '<p style="color: red;">Error: ' + data.data + '</p>';
                }
            })
            .catch(error => {
                messageDiv.innerHTML = '<p style="color: red;">An error occurred. Please try again.</p>';
            });
        });
        </script>

        <?php
        get_footer();
    }

    /**
     * Handle user registration via share link
     */
    public function register_via_share_link() {
        $share_token = sanitize_text_field($_POST['share_token']);
        $user_login = sanitize_user($_POST['user_login']);
        $user_email = sanitize_email($_POST['user_email']);
        $user_pass = $_POST['user_pass'];
        $display_name = sanitize_text_field($_POST['display_name'] ?? $user_login);

        // Validate share token
        global $wpdb;
        $share_links_table = $wpdb->prefix . 'project_share_links';
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $share_links_table WHERE share_token = %s AND is_active = 1",
            $share_token
        ));

        if (!$link || $this->is_link_expired($link)) {
            wp_send_json_error('Invalid or expired share link');
        }

        // Create user
        $user_id = wp_create_user($user_login, $user_pass, $user_email);

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }

        // Update display name
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name
        ));

        // Log the user in
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        // Grant project access
        $projects_table = $wpdb->prefix . 'user_projects';
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $projects_table WHERE id = %d",
            $link->project_id
        ));

        $this->grant_project_access($user_id, $link, $project, true);

        wp_send_json_success(array(
            'redirect_url' => wc_get_account_endpoint_url('file-storage')
        ));
    }

    /**
     * Grant project access to user
     */
    private function grant_project_access($user_id, $link, $project, $is_new_user = false) {
        global $wpdb;
        $members_table = $wpdb->prefix . 'project_members';
        $share_links_table = $wpdb->prefix . 'project_share_links';
        $usage_table = $wpdb->prefix . 'share_link_usage';

        // Check if user is already a member
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE project_id = %d AND user_id = %d",
            $link->project_id,
            $user_id
        ));

        if (!$existing) {
            // Add user as project member
            $wpdb->insert(
                $members_table,
                array(
                    'project_id' => $link->project_id,
                    'user_id' => $user_id,
                    'role' => 'member',
                    'permission_level' => $link->permission_level,
                    'added_by' => $link->created_by
                ),
                array('%d', '%d', '%s', '%s', '%d')
            );

            // Log activity
            if (class_exists('UFS_Activity_Log')) {
                UFS_Activity_Log::log_activity($user_id, 'project_shared', array(
                    'project_id' => $link->project_id,
                    'details' => array('via_share_link' => true)
                ));
            }
        }

        // Track usage
        $wpdb->insert(
            $usage_table,
            array(
                'share_link_id' => $link->id,
                'user_id' => $user_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'], 0, 255),
                'was_new_user' => $is_new_user ? 1 : 0
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );

        // Update usage count
        $wpdb->query($wpdb->prepare(
            "UPDATE $share_links_table SET current_uses = current_uses + 1 WHERE id = %d",
            $link->id
        ));

        // Redirect to project
        wp_redirect(wc_get_account_endpoint_url('file-storage') . '?project=' . $link->project_id);
        exit;
    }

    /**
     * Redirect after login to handle share link
     */
    public function redirect_after_share_login() {
        if (is_user_logged_in() && isset($_GET['redirect_to'])) {
            $redirect_to = $_GET['redirect_to'];
            if (strpos($redirect_to, '/share-project/') !== false) {
                wp_redirect($redirect_to);
                exit;
            }
        }
    }

    // Helper functions
    private function generate_unique_token() {
        return bin2hex(random_bytes(32));
    }

    private function is_link_expired($link) {
        if (!$link->expires_at) {
            return false;
        }
        return strtotime($link->expires_at) < time();
    }

    private function user_can_manage_project($user_id, $project_id) {
        global $wpdb;
        $projects_table = $wpdb->prefix . 'user_projects';
        $members_table = $wpdb->prefix . 'project_members';

        $can_manage = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $projects_table p
            WHERE p.id = %d AND (
                p.owner_id = %d OR EXISTS (
                    SELECT 1 FROM $members_table pm
                    WHERE pm.project_id = p.id
                    AND pm.user_id = %d
                    AND pm.permission_level = 'manage'
                )
            )",
            $project_id, $user_id, $user_id
        ));

        return $can_manage > 0;
    }
}
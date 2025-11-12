<?php
if (!defined('ABSPATH'))
    exit;

class Hamnaghsheh_Share
{

    public function __construct()
    {
        add_action('wp_ajax_create_share_link', [$this, 'create_share_link']);
        add_action('wp_ajax_nopriv_handle_share_access', [$this, 'handle_share_access']);
        add_action('wp_ajax_handle_share_access', [$this, 'handle_share_access']);

        add_action('admin_post_hamnaghsheh_assign_project', [$this, 'assign_project']);
        add_action('admin_post_nopriv_hamnaghsheh_assign_project', [$this, 'assign_project']);
    }

    public static function generate_token($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    public function create_share_link()
    {
        if (!is_user_logged_in())
            wp_send_json_error('Unauthorized');

        $owner_id = get_current_user_id();
        $project_id = intval($_POST['project_id']);
        $permission = sanitize_text_field($_POST['permission']);
        $token = self::generate_token();

        global $wpdb;
        $table = $wpdb->prefix . 'hamnaghsheh_shares';
        $wpdb->insert($table, [
            'project_id' => $project_id,
            'owner_id' => $owner_id,
            'token' => $token,
            'permission' => $permission
        ]);

        $link = site_url("/share/$token");

        wp_send_json_success(['link' => $link]);
    }

    public static function get_share_links($project_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hamnaghsheh_shares';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE project_id=%d ORDER BY id DESC", $project_id));
    }

    public static function verify_token($token)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hamnaghsheh_shares';
        $share = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token=%s", $token));
        if (!$share)
            return false;       
        return $share;
    }

    public function handle_share_access()
    {
        $token = sanitize_text_field($_GET['token']);
        $share = self::verify_token($token);

        if (!$share)
            wp_die('لینک اشتراک نامعتبر یا منقضی است.');

        // اگر برای مهمان مجاز است
        if (!empty($share->is_guest) && $share->is_guest == 1) {
            $this->render_share_page($share, true);
            return;
        }

        if (!is_user_logged_in()) {
            wp_redirect(home_url('/login/?redirect_to=' . urlencode(site_url('/share/' . $token))));
            exit;
        }

        $this->render_share_page($share, false);
    }

    public function assign_project()
    {
        if (!is_user_logged_in()) {
            wp_redirect(site_url("/login"));
            exit;
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $token = sanitize_text_field($_POST['token']);
        $project_id = intval($_POST['project_id']);

        $share = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hamnaghsheh_shares WHERE token = %s",
            $token
        ));

        if (!$share) {
            wp_die('لینک اشتراک معتبر نیست.');
        }

        Hamnaghsheh_Projects::assign_user_to_project(
            $project_id,
            $user_id,
            $share->permission,
            $share->owner_id,
            $token
        );

        global $wpdb;
        $table = $wpdb->prefix . 'hamnaghsheh_shares';
        $wpdb->query($wpdb->prepare("UPDATE $table SET usage_count = usage_count + 1 WHERE id=%d", $share->id));

        wp_redirect(site_url("/show-project/?id=$project_id"));
        exit;
    }

    public function render_share_page($share, $is_guest = false)
    {
        global $wpdb;
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hamnaghsheh_projects WHERE id = %d",
            $share->project_id
        ));

        if (!$project)
            wp_die('پروژه یافت نشد.');

        $table_files = $wpdb->prefix . 'hamnaghsheh_files';
        $files = $wpdb->get_results($wpdb->prepare("
            SELECT id, file_name, file_size, file_path, uploaded_at 
            FROM $table_files 
            WHERE project_id = %d
            ORDER BY uploaded_at DESC
        ", $share->project_id), ARRAY_A);

        get_header();
        // ob_start();
        include HAMNAGHSHEH_DIR . 'templates/share-access-page.php';
        // return ob_get_clean();
        get_footer();
    }


}
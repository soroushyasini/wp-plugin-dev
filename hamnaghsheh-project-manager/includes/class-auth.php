<?php
if (!defined('ABSPATH'))
    exit;

class Hamnaghsheh_Auth
{

    public function __construct()
    {
        add_shortcode('hamnaghsheh_login_form', [$this, 'render_login_form']);
        add_shortcode('hamnaghsheh_register_form', [$this, 'render_register_form']);

        add_action('admin_post_hamnaghsheh_login', [$this, 'handle_login']);
        add_action('admin_post_nopriv_hamnaghsheh_login', [$this, 'handle_login']);

        add_action('admin_post_hamnaghsheh_register', [$this, 'handle_register']);
        add_action('admin_post_nopriv_hamnaghsheh_register', [$this, 'handle_register']);
    }

    public function render_login_form()
    {
        ob_start();
        include HAMNAGHSHEH_DIR . 'templates/login-form.php';
        return ob_get_clean();
    }

    public function render_register_form()
    {
        ob_start();
        include HAMNAGHSHEH_DIR . 'templates/register-form.php';
        return ob_get_clean();
    }

    public function handle_login()
    {
        $creds = [
            'user_login' => sanitize_text_field($_POST['log']),
            'user_password' => $_POST['pwd'],
            'remember' => true
        ];
        $user = wp_signon($creds, false);

        if (is_wp_error($user)) {
            wp_redirect(add_query_arg('login', 'failed', wp_get_referer()));
            exit;
        }

        $redirect_to = isset($_POST['redirect']) ? esc_url_raw($_POST['redirect']) : home_url('/dashboard/');
        wp_redirect($redirect_to);
        
        exit;
    }

    public function handle_register()
    {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];

        if (username_exists($username) || email_exists($email)) {
            wp_redirect(add_query_arg('register', 'exists', wp_get_referer()));
            exit;
        }

        $user_id = wp_create_user($username, $password, $email);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : home_url('/dashboard/');
        wp_redirect($redirect_to);
        exit;
    }
}

<?php
/**
 * Plugin Name: Hamnaghsheh - File Projects Manager
 * Plugin URI:  https://hamnaghsheh.ir
 * Description: مدیریت پروژه‌ها و فایل‌ها برای کاربران؛ آپلود DWG/DXF/TXT و اشتراک با لینک مهمان.
 * Version:     0.1.7
 * Author:      Milad Karimi
 * Text Domain: hamnaghsheh
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HAMNAGHSHEH_VERSION', '0.2.4');
define('HAMNAGHSHEH_DIR', plugin_dir_path(__FILE__));
define('HAMNAGHSHEH_URL', plugin_dir_url(__FILE__));
define('HAMNAGHSHEH_PREFIX', 'hamnaghsheh_');
define('HAMNAGHSHEH_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/hamnaghsheh/');
define('HAMNAGHSHEH_UPLOAD_URL', wp_upload_dir()['baseurl'] . '/hamnaghsheh/');

/**
 * Autoload / Loader
 */
require_once HAMNAGHSHEH_DIR . 'includes/class-loader.php';
$hamnaghsheh_loader = new Hamnaghsheh_Loader();

/**
 * Activation / Deactivation
 */
register_activation_hook(__FILE__, array('Hamnaghsheh_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Hamnaghsheh_Deactivator', 'deactivate'));

/**
 * Init: load textdomain, enqueue assets, register shortcode & ajax handlers
 */
add_action('init', array($hamnaghsheh_loader, 'init_textdomain'));
add_action('wp_enqueue_scripts', array($hamnaghsheh_loader, 'public_assets'));
add_action('admin_enqueue_scripts', array($hamnaghsheh_loader, 'admin_assets'));

/**
 * Shortcode for dashboard
 */
add_shortcode('hamnaghsheh_dashboard', array('Hamnaghsheh_Dashboard', 'render_shortcode'));
add_shortcode('hamnaghsheh_new-project', array('Hamnaghsheh_New_Project', 'render_shortcode'));
add_shortcode('hamnaghsheh_project_show', array('Hamnaghsheh_Project_Show', 'render_shortcode'));

/**
 * AJAX endpoints (both for logged-in and guest when needed)
 */
add_action('wp_ajax_hamnaghsheh_create_project', array('Hamnaghsheh_Projects', 'ajax_create_project'));
add_action('wp_ajax_hamnaghsheh_upload_file', array('Hamnaghsheh_Files', 'ajax_upload_file'));
add_action('wp_ajax_hamnaghsheh_delete_file', array('Hamnaghsheh_Files', 'ajax_delete_file'));
add_action('wp_ajax_nopriv_hamnaghsheh_guest_view', array('Hamnaghsheh_Share_Links', 'guest_view'));

add_action('plugins_loaded', function () {
    new Hamnaghsheh_Projects();
});


add_action('wp_enqueue_scripts', 'hamnaghsheh_enqueue_fonts', 20);
function hamnaghsheh_enqueue_fonts()
{
    global $wp_styles;
    $has_font = false;

    if (!empty($wp_styles->registered)) {
        foreach ($wp_styles->registered as $style) {
            if (strpos($style->src, 'font') !== false || strpos($style->src, 'fonts') !== false) {
                $has_font = true;
                break;
            }
        }
    }

    // if (!$has_font) {
    wp_enqueue_style(
        'hamnaghsheh-default-font',
        HAMNAGHSHEH_URL . 'assets/fonts/vazir-font.css',
        array(),
        '1.0'
    );
    wp_register_script('hamnaghsheh-uploader', HAMNAGHSHEH_URL . 'assets/js/main.js', array('jquery'), HAMNAGHSHEH_VERSION, true);
    wp_localize_script('hamnaghsheh-uploader', 'hamnaghsheh_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('hamnaghsheh_ajax_nonce'),
    ));
    wp_enqueue_script('hamnaghsheh-uploader');
    // }
}

add_action('init', function () {
    add_rewrite_rule('^share/([^/]+)/?', 'index.php?share_token=$matches[1]', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'share_token';
    return $vars;
});

add_action('template_redirect', function () {
    $token = get_query_var('share_token');
    if ($token) {
        $handler = new Hamnaghsheh_Share();
        $_GET['token'] = $token;
        $handler->handle_share_access();
        exit;
    }
});

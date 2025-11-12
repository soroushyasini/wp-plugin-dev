<?php
if (!defined('ABSPATH'))
  exit;

class Hamnaghsheh_Loader
{

  public function __construct()
  {
    $this->requires();
  }

  private function requires()
  {
    require_once HAMNAGHSHEH_DIR . 'includes/class-activator.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-deactivator.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-users.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-dashboard.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-new-project.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-project-show.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-projects.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-utils.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-upload-file.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-shares.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-auth.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-log-file.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-file-download.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-user-setting.php';
    require_once HAMNAGHSHEH_DIR . 'includes/class-pages.php';

    new Hamnaghsheh_Users();
    new Hamnaghsheh_File_Upload();
    new Hamnaghsheh_Share();
    new Hamnaghsheh_Auth();
    new Hamnaghsheh_Logs();
    new Hamnaghsheh_File_Download();
    new Hamnaghsheh_User_Settings();
    new Hamnaghsheh_Pages();

    add_action('wp_enqueue_scripts', [$this, 'tailwind_assets']);
  }

  public function init_textdomain()
  {
    load_plugin_textdomain('hamnaghsheh', false, dirname(plugin_basename(__FILE__)) . '/../languages/');
  }

  public function public_assets()
  {
    wp_register_style('hamnaghsheh-style', HAMNAGHSHEH_URL . 'assets/css/style.css', array(), HAMNAGHSHEH_VERSION);
    wp_enqueue_style('hamnaghsheh-style');

  }

  public function admin_assets()
  {
    wp_register_style('hamnaghsheh-admin', HAMNAGHSHEH_URL . 'assets/css/dashboard.css', array(), HAMNAGHSHEH_VERSION);
    wp_enqueue_style('hamnaghsheh-admin');
  }

  public function tailwind_assets()
  {
    // ✅ بارگذاری Tailwind از CDN (بدون build)
    wp_enqueue_script(
      'tailwindcdn',
      'https://cdn.tailwindcss.com',
      [],
      null,
      false
    );

    // ✅ پیکربندی اولیه Tailwind (فونت + رنگ‌ها)
    $custom_tailwind = "
            tailwind.config = {
              theme: {
                extend: {
                  fontFamily: {
                    sans: ['Vazirmatn', 'ui-sans-serif', 'system-ui']
                  },
                  colors: {
                    primary: '#2563eb',
                    secondary: '#1e293b'
                  }
                }
              }
            }
        ";
    wp_add_inline_script('tailwindcdn', $custom_tailwind, 'after');
  }
}

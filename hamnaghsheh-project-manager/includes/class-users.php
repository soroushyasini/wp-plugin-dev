<?php
if (!defined('ABSPATH'))
  exit;

class Hamnaghsheh_Users
{

  public function __construct()
  {
    add_action('user_register', [$this, 'on_user_register'], 10, 1);
  }

  public function on_user_register($user_id)
  {
    global $wpdb;

    $user_info = get_userdata($user_id);
    $email = $user_info->user_email;
    $username = $user_info->user_login;
    $name = $user_info->display_name;

    $table_name = $wpdb->prefix . 'hamnaghsheh_users';

    $wpdb->insert(
      $table_name,
      [
        'user_id' => $user_id,
        'username' => $username,
        'email' => $email,
        'display_name' => $name
      ],
      ['%d', '%s', '%s', '%s']
    );
  }

  public static function check_active_user($user_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hamnaghsheh_users';

    $active = $wpdb->get_var($wpdb->prepare(
      "SELECT active FROM $table_name WHERE user_id = %d ORDER BY id DESC LIMIT 1",
      $user_id
    ));

    return $active;
  }

  public static function ensure_user_access()
  {
    if (!is_user_logged_in()) {
      $login_url = wp_redirect(home_url('/login/?redirect_to=' . urlencode(site_url('/dashboard'))));
      return '
          <div class="hamnaghsheh-notice text-red-800 bg-red-100 w-full p-4 rounded-lg text-md text-center">
            <div class="block">لطفاً وارد شوید تا به کارتابل خود دسترسی داشته باشید</div>
            <div><a class="text-blue-900 text-blue mt-2" href="https://hamnaghsheh.ir/login?redirect_to=https://hamnaghsheh.ir/dashboard">ورود به کارتابل</a></div>
          </div>';
    }

    $user_id = get_current_user_id();

    if (!self::check_active_user($user_id)) {
      return '<p class="hamnaghsheh-notice text-red-800 bg-red-100 w-full p-4 rounded-lg text-md text-center">دسترسی شما به سیستم هنوز فعال نشده است لطفاً با مدیر تماس بگیرید</p>';

    }

    return false;
  }

  public static function current_id()
  {
    return get_current_user_id();
  }


}

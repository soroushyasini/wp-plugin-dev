<?php
if (!defined('ABSPATH'))
  exit;

class Hamnaghsheh_Projects
{

  public function __construct()
  {
    add_action('admin_post_hamnaghsheh_create_project', [$this, 'create_project']);
    add_action('admin_post_nopriv_hamnaghsheh_create_project', [$this, 'create_project']);

    add_action('admin_post_hamnaghsheh_update_project', [$this, 'update_project']);
    add_action('admin_post_nopriv_hamnaghsheh_update_project', [$this, 'update_project']);

    add_action('admin_post_hamnaghsheh_archive_project', [$this, 'archive_project']);
    add_action('admin_post_nopriv_hamnaghsheh_archive_project', [$this, 'archive_project']);

    add_action('admin_post_hamnaghsheh_unassigned', [$this, 'unassigned']);
    add_action('admin_post_nopriv_hamnaghsheh_unassigned', [$this, 'unassigned']);
    
    add_action('admin_post_hamnaghsheh_unarchive_project', [$this, 'unarchive_project']);
    add_action('admin_post_nopriv_hamnaghsheh_unarchive_project', [$this, 'unarchive_project']);
    
    add_action('wp_ajax_hamnaghsheh_log_download', [$this, 'hamnaghsheh_log_download']);
    add_action('wp_ajax_nopriv_hamnaghsheh_log_download', [$this,  'hamnaghsheh_log_download']);
    
    add_action('wp_ajax_hamnaghsheh_log_see', [$this, 'hamnaghsheh_log_see']);
    add_action('wp_ajax_nopriv_hamnaghsheh_log_see', [$this,  'hamnaghsheh_log_see']);
    
  }
  
  function hamnaghsheh_log_download() {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'hamnaghsheh_file_logs';
    
        $user_id    = get_current_user_id();
        $file_id    = intval($_POST['file_id']);
        $project_id = intval($_POST['project_id']);
    
        $wpdb->insert($table_logs, [
            'file_id'     => $file_id,
            'project_id'  => $project_id,
            'user_id'     => $user_id,
            'action_type' => 'download',
            'created_at'  => current_time('mysql'),
        ]);
    
        wp_send_json_success();
    }
    
  function hamnaghsheh_log_see() {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'hamnaghsheh_file_logs';
    
        $user_id    = get_current_user_id();
        $file_id    = intval($_POST['file_id']);
        $project_id = intval($_POST['project_id']);
    
        $wpdb->insert($table_logs, [
            'file_id'     => $file_id,
            'project_id'  => $project_id,
            'user_id'     => $user_id,
            'action_type' => 'see',
            'created_at'  => current_time('mysql'),
        ]);
    
        wp_send_json_success();
    }
  public function create_project()
  {

    if (
      !isset($_POST['hamnaghsheh_nonce']) ||
      !wp_verify_nonce($_POST['hamnaghsheh_nonce'], 'hamnaghsheh_create_project')
    ) {
      wp_die('درخواست معتبر نیست.');
    }

    if (!is_user_logged_in()) {
      wp_die('برای ایجاد پروژه باید وارد شوید.');
    }

    $user_id = get_current_user_id();
    $name = sanitize_text_field($_POST['project_name']);
    $desc = sanitize_textarea_field($_POST['project_desc']);
    $type = sanitize_textarea_field($_POST['project_type']);

    if (empty($name)) {
      wp_die('نام پروژه الزامی است.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'hamnaghsheh_projects';
    $wpdb->insert($table_name, [
      'user_id' => $user_id,
      'name' => $name,
      'description' => $desc,
      'type' => $type,
      'created_at' => current_time('mysql')
    ]);

    wp_redirect(home_url('/dashboard'));
    exit;
  }

  public function update_project()
  {
    if (
      !isset($_POST['hamnaghsheh_nonce']) ||
      !wp_verify_nonce($_POST['hamnaghsheh_nonce'], 'hamnaghsheh_update_project')
    ) {
      wp_die('درخواست معتبر نیست.');
    }

    $user_id = get_current_user_id();
    $project_id = intval($_POST['project_id']);
    $name = sanitize_text_field($_POST['project_name']);
    $desc = sanitize_textarea_field($_POST['project_desc']);
    $type = sanitize_text_field($_POST['project_type']);

    if (empty($project_id) || empty($name)) {
      wp_die('شناسه یا نام پروژه معتبر نیست.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'hamnaghsheh_projects';

    $owner_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table_name WHERE id = %d", $project_id));
    if ($owner_id != $user_id) {
      wp_die('شما مجاز به ویرایش این پروژه نیستید.');
    }

    $wpdb->update(
      $table_name,
      [
        'name' => $name,
        'description' => $desc,
        'type' => $type,
        'updated_at' => current_time('mysql'),
      ],
      ['id' => $project_id],
      ['%s', '%s', '%s', '%s'],
      ['%d']
    );

    wp_redirect(home_url('/dashboard'));
    exit;
  }

  public static function get_user_projects($user_id)
  {
    global $wpdb;
    $table_projects = $wpdb->prefix . 'hamnaghsheh_projects';
    $table_assigns = $wpdb->prefix . 'hamnaghsheh_project_assignments';
    $table_users = $wpdb->users;

    return $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT 
            p.*, 
            COALESCE(u.display_name, u.user_nicename) AS owner_name,
            CASE WHEN p.user_id = %d THEN 1 ELSE 0 END AS is_owner
        FROM $table_projects AS p
        LEFT JOIN $table_assigns AS a ON p.id = a.project_id
        LEFT JOIN $table_users AS u ON p.user_id = u.ID 
        WHERE p.archive = 0  AND (p.user_id = %d OR a.user_id = %d)
        ORDER BY p.id DESC
    ", $user_id, $user_id, $user_id));

  }

  public static function get_archived_project($user_id)
  {
    global $wpdb;
    $table_projects = $wpdb->prefix . 'hamnaghsheh_projects';
    return $wpdb->get_results($wpdb->prepare(
      "
      SELECT * FROM $table_projects WHERE archive = %d AND user_id = %d",
      1,
      $user_id
    ));
  }

  public static function assign_user_to_project($project_id, $user_id, $permission = 'view', $assigned_by = null, $token = null)
  {
    global $wpdb;
    $table = $wpdb->prefix . 'hamnaghsheh_project_assignments';

    // چک کنیم اگر قبلاً اضافه شده تکراری ثبت نشه
    $exists = $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $table WHERE project_id = %d AND user_id = %d",
      $project_id,
      $user_id
    ));
    if ($exists)
      return false;

    $wpdb->insert($table, [
      'project_id' => $project_id,
      'user_id' => $user_id,
      'permission' => $permission,
      'assigned_by' => $assigned_by,
      'assigned_via_token' => $token
    ]);
    return true;
  }

  public function archive_project()
  {
    if (!is_user_logged_in()) {
      wp_die('شما دسترسی ندارید');
    }

    global $wpdb;
    $project_id = intval($_POST['project_id']);
    $user_id = get_current_user_id();

    $project = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$wpdb->prefix}hamnaghsheh_projects WHERE id = %d AND user_id = %d",
      $project_id,
      $user_id
    ));

    if (!$project) {
      wp_die('پروژه یافت نشد یا شما مجاز نیستید');
    }

    $wpdb->update(
      "{$wpdb->prefix}hamnaghsheh_projects",
      ['archive' => 1],
      ['id' => $project_id]
    );

    wp_redirect(home_url('/dashboard'));
    exit;
  }
  
    public function unarchive_project()
      {
        if (!is_user_logged_in()) {
          wp_die('شما دسترسی ندارید');
        }
    
        global $wpdb;
        $project_id = intval($_POST['project_id']);
        $user_id = get_current_user_id();
    
        $project = $wpdb->get_row($wpdb->prepare(
          "SELECT * FROM {$wpdb->prefix}hamnaghsheh_projects WHERE id = %d AND user_id = %d",
          $project_id,
          $user_id
        ));
    
        if (!$project) {
          wp_die('پروژه یافت نشد یا شما مجاز نیستید');
        }
    
        $wpdb->update(
          "{$wpdb->prefix}hamnaghsheh_projects",
          ['archive' => 0],
          ['id' => $project_id]
        );
    
        wp_redirect(home_url('/dashboard'));
        exit;
      }

  public function unassigned()
  {
    if (!is_user_logged_in()) {
      wp_die('شما دسترسی ندارید');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'hamnaghsheh_project_assignments';

    $project_id = intval($_POST['project_id']); // آیدی پروژه
    $user_id = intval($_POST['user_id']); // آیدی کاربر

    $wpdb->delete(
      $table,
      [
        'project_id' => $project_id,
        'user_id' => $user_id,
      ],
      [
        '%d',
        '%d',
      ]
    );
    wp_redirect(home_url('/show-project/?id=' . $project_id));
    exit;
  }
}

<?php
if (!defined('ABSPATH'))
    exit;

class Hamnaghsheh_File_Upload
{
    public function __construct()
    {
        if (!session_id())
            session_start();

        add_action('admin_post_hamnaghsheh_upload_file', [$this, 'upload_file']);
        add_action('admin_post_nopriv_hamnaghsheh_upload_file', [$this, 'upload_file']);

        add_action('admin_post_hamnaghsheh_delete_file', [$this, 'delete_file']);
        add_action('admin_post_nopriv_hamnaghsheh_delete_file', [$this, 'delete_file']);

        add_action('admin_post_hamnaghsheh_replace_file', [$this, 'replace_file']);
        add_action('admin_post_nopriv_hamnaghsheh_replace_file', [$this, 'replace_file']);
    }

    public function upload_file()
    {
        if (!is_user_logged_in())
            wp_die('برای آپلود فایل باید وارد شوید.');

        if (empty($_POST['project_id']) || empty($_FILES['file']))
            wp_die('درخواست ناقص است.');

        global $wpdb;
        $user_id = get_current_user_id();
        $project_id = intval($_POST['project_id']);
        $file = $_FILES['file'];

        $table_projects = $wpdb->prefix . 'hamnaghsheh_projects';
        $table_users = $wpdb->prefix . 'hamnaghsheh_users';
        $table_files = $wpdb->prefix . 'hamnaghsheh_files';
        $table_assign = $wpdb->prefix . 'hamnaghsheh_project_assignments';
        $table_file_logs = $wpdb->prefix . 'hamnaghsheh_file_logs';

        // بررسی وجود پروژه
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_projects WHERE id = %d", $project_id));
        if (!$project)
            wp_die('پروژه یافت نشد.');

        // بررسی دسترسی
        $is_owner = ($project->user_id == $user_id);
        $has_permission = false;

        if ($is_owner) {
            $has_permission = true;
        } else {
            // بررسی اگر کاربر از طریق لینک اساین شده باشد
            $assign = $wpdb->get_row($wpdb->prepare("
                SELECT permission FROM $table_assign
                WHERE project_id = %d AND user_id = %d
            ", $project_id, $user_id));

            if ($assign && $assign->permission === 'upload') {
                $has_permission = true;
            }
        }

        if (!$has_permission) {
            $_SESSION['alert'] = ['type' => 'error', 'message' => 'شما مجاز به آپلود در این پروژه نیستید.'];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }

        // محدودیت حجم بر اساس اونر پروژه
        $user_limit = $wpdb->get_var($wpdb->prepare("SELECT storage_limit FROM $table_users WHERE user_id = %d", $project->user_id));
        if (!$user_limit)
            $user_limit = 52428800; // ۵۰ مگابایت پیشفرض

        $used_space = $wpdb->get_var($wpdb->prepare("SELECT SUM(file_size) FROM $table_files WHERE project_id = %d", $project_id));
        if (!$used_space)
            $used_space = 0;

        $new_file_size = intval($file['size']);
        if (($used_space + $new_file_size) > $user_limit) {
            $_SESSION['alert'] = ['type' => 'error', 'message' => 'حجم مجاز ذخیره‌سازی کافی نیست.'];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }

        // مسیر ذخیره
        $upload_dir = wp_upload_dir();
        $project_dir = $upload_dir['basedir'] . '/hamnaghsheh/' . $project_id;
        if (!file_exists($project_dir))
            wp_mkdir_p($project_dir);

        $file_name = sanitize_file_name($file['name']);
        $file_path = $project_dir . '/' . $file_name;

        $allowed_extensions = ['dwg', 'dxf', 'txt'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_extensions)) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'message' => 'فقط فایل‌های با پسوند DWG ،DXF و TXT مجاز به آپلود هستند.'
            ];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }

        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            $_SESSION['alert'] = ['type' => 'error', 'message' => 'آپلود فایل با خطا مواجه شد.'];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }

        $relative_path = str_replace(ABSPATH, '/', $file_path);
        $relative_path = preg_replace('#^/+#', '/', $relative_path);

        $wpdb->insert($table_files, [
            'project_id' => $project_id,
            'user_id' => $user_id,
            'file_name' => $file_name,
            'file_path' => $relative_path,
            'file_size' => $new_file_size,
            'file_type' => $file['type'],
            'uploaded_at' => current_time('mysql')
        ]);

        $file_id = $wpdb->insert_id;

        $wpdb->insert(
            $table_file_logs,
            [
                'file_id' => $file_id,
                'project_id' => $project_id,
                'user_id' => $user_id,
                'action_type' => 'upload',
            ],
            ['%d', '%d', '%d', '%s']
        );

        $_SESSION['alert'] = ['type' => 'success', 'message' => 'فایل با موفقیت آپلود شد.'];
        wp_redirect(home_url('/show-project/?id=' . $project_id));
        exit;
    }

    public function delete_file()
    {
        if (!is_user_logged_in())
            wp_die('برای حذف فایل باید وارد شوید.');
        if (empty($_GET['file_id']) || empty($_GET['project_id']))
            wp_die('درخواست ناقص است.');

        global $wpdb;
        $file_id = intval($_GET['file_id']);
        $project_id = intval($_GET['project_id']);
        $user_id = get_current_user_id();

        $table_files = $wpdb->prefix . 'hamnaghsheh_files';
        $table_projects = $wpdb->prefix . 'hamnaghsheh_projects';
        $table_file_logs = $wpdb->prefix . 'hamnaghsheh_file_logs';

        $file = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_files WHERE id = %d", $file_id));
        if (!$file) {
            $_SESSION['alert'] = ['type' => 'error', 'message' => 'فایل یافت نشد.'];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }

        $project = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM $table_projects WHERE id = %d", $project_id));
        if (!$project || $project->user_id != $user_id) {
            $_SESSION['alert'] = ['type' => 'error', 'message' => 'فقط مالک پروژه مجاز به حذف فایل‌هاست.'];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }

        // حذف از پوشه
        $upload_dir = wp_upload_dir();
        $absolute_path = $upload_dir['basedir'] . $file->file_path;
        if (file_exists($absolute_path))
            unlink($absolute_path);

        $wpdb->delete($table_files, ['id' => $file_id], ['%d']);
        $wpdb->insert(
            $table_file_logs,
            [
                'file_id' => $file_id,
                'project_id' => $project_id,
                'user_id' => $user_id,
                'action_type' => 'delete',
            ],
            ['%d', '%d', '%d', '%s']
        );


        $_SESSION['alert'] = ['type' => 'success', 'message' => 'فایل با موفقیت حذف شد.'];
        wp_redirect(home_url('/show-project/?id=' . $project_id));
        exit;
    }

    public function replace_file()
    {
        if (!is_user_logged_in())
            wp_die('برای جایگزینی فایل باید وارد شوید.');

        if (empty($_POST['project_id']) || empty($_POST['file_id']) || empty($_FILES['file']))
            wp_die('درخواست ناقص است.');

        global $wpdb;
        $user_id = get_current_user_id();
        $project_id = intval($_POST['project_id']);
        $file_id = intval($_POST['file_id']);
        $file = $_FILES['file'];

        $table_projects = $wpdb->prefix . 'hamnaghsheh_projects';
        $table_files = $wpdb->prefix . 'hamnaghsheh_files';
        $table_assign = $wpdb->prefix . 'hamnaghsheh_project_assignments';
        $table_file_logs = $wpdb->prefix . 'hamnaghsheh_file_logs';
        $table_users = $wpdb->prefix . 'hamnaghsheh_users';


        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_projects WHERE id = %d", $project_id));
        if (!$project)
            wp_die('پروژه یافت نشد.');


        $is_owner = ($project->user_id == $user_id);
        $has_permission = false;

        if ($is_owner) {
            $has_permission = true;
        } else {
            $assign = $wpdb->get_row($wpdb->prepare("
            SELECT permission FROM $table_assign
            WHERE project_id = %d AND user_id = %d
        ", $project_id, $user_id));

            if ($assign && $assign->permission === 'upload') {
                $has_permission = true;
            }
        }

        if (!$has_permission) {
            $_SESSION['alert'] = ['type' => 'error', 'message' => 'شما مجاز به جایگزینی فایل در این پروژه نیستید.'];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }


        $old_file = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_files WHERE id = %d", $file_id));
        if (!$old_file) {
            $_SESSION['alert'] = ['type' => 'error', 'message' => 'فایل مورد نظر یافت نشد.'];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }

        // مسیر ذخیره
        $upload_dir = wp_upload_dir();
        $project_dir = $upload_dir['basedir'] . '/hamnaghsheh/' . $project_id;
        if (!file_exists($project_dir))
            wp_mkdir_p($project_dir);

        $file_name = sanitize_file_name($file['name']);
        $file_path = $project_dir . '/' . $file_name;

        $allowed_extensions = ['dwg', 'dxf', 'txt'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_extensions)) {
            $_SESSION['alert'] = ['type' => 'error', 'message' => 'فرمت فایل مجاز نیست.'];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }

        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            $_SESSION['alert'] = ['type' => 'error', 'message' => 'آپلود فایل جدید ناموفق بود.'];
            wp_redirect(home_url('/show-project/?id=' . $project_id));
            exit;
        }


        $old_path = ABSPATH . ltrim($old_file->file_path, '/');
        if (file_exists($old_path)) {
            unlink($old_path);
        }


        $relative_path = str_replace(ABSPATH, '/', $file_path);
        $relative_path = preg_replace('#^/+#', '/', $relative_path);

        $wpdb->update(
            $table_files,
            [
                'file_name' => $file_name,
                'file_path' => $relative_path,
                'file_size' => intval($file['size']),
                'file_type' => $file['type'],
                'uploaded_at' => current_time('mysql')
            ],
            ['id' => $file_id],
            ['%s', '%s', '%d', '%s', '%s'],
            ['%d']
        );


        $wpdb->insert(
            $table_file_logs,
            [
                'file_id' => $file_id,
                'project_id' => $project_id,
                'user_id' => $user_id,
                'action_type' => 'replace'
            ],
            ['%d', '%d', '%d', '%s']
        );

        $_SESSION['alert'] = ['type' => 'success', 'message' => 'فایل با موفقیت جایگزین شد.'];
        wp_redirect(home_url('/show-project/?id=' . $project_id));
        exit;
    }

}

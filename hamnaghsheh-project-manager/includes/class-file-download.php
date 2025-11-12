<?php
if (!defined('ABSPATH'))
    exit;

class Hamnaghsheh_File_Download
{

    public function __construct()
    {
        add_action('wp_ajax_download_project_files', [$this, 'download_project_files']);
    }


    public function download_project_files()
    {
        if (!is_user_logged_in()) {
            wp_die('برای دانلود فایل‌ها باید وارد شوید.');
        }

        if (empty($_GET['project_id'])) {
            wp_die('شناسه پروژه ارسال نشده است.');
        }

        global $wpdb;
        $project_id = intval($_GET['project_id']);
        $user_id = get_current_user_id();

        $table_projects = $wpdb->prefix . 'hamnaghsheh_projects';
        $table_files = $wpdb->prefix . 'hamnaghsheh_files';
        $table_assign = $wpdb->prefix . 'hamnaghsheh_project_assignments';

        // بررسی وجود پروژه
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_projects WHERE id = %d", $project_id));
        if (!$project) {
            wp_die('پروژه یافت نشد.');
        }

        // // بررسی سطح دسترسی
        // $is_owner = ($project->user_id == $user_id);
        // $has_permission = false;

        // if ($is_owner) {
        //     $has_permission = true;
        // }

        // if (!$has_permission) {
        //     wp_die('شما مجاز به مشاهده فایل‌های این پروژه نیستید.');
        // }

        // دریافت مسیر فایل‌ها
        $files = $wpdb->get_results($wpdb->prepare("
        SELECT file_path FROM $table_files WHERE project_id = %d
    ", $project_id));

        if (empty($files)) {
            wp_die('هیچ فایلی برای این پروژه یافت نشد.');
        }

        // مسیر ذخیره زیپ
        $upload_dir = wp_upload_dir();
        $zip_name = 'project-' . $project_id . '-' . time() . '.zip';
        $zip_path = $upload_dir['basedir'] . '/' . $zip_name;

        // استفاده از PclZip (پیشفرض وردپرس)
        require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        $archive = new PclZip($zip_path);

        // آماده‌سازی مسیرها برای فشرده‌سازی
        $file_paths = [];
        foreach ($files as $file) {
            $full_path = ABSPATH . ltrim($file->file_path, '/');
            if (file_exists($full_path)) {
                $file_paths[] = $full_path;
            }
        }

        if (empty($file_paths)) {
            wp_die('هیچ فایلی برای فشرده‌سازی یافت نشد.');
        }

        $v_list = $archive->create($file_paths, PCLZIP_OPT_REMOVE_PATH, ABSPATH);

        if ($v_list == 0) {
            wp_die('خطا در ایجاد فایل ZIP: ' . $archive->errorInfo(true));
        }

        // ارسال فایل برای دانلود
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zip_name) . '"');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);

        // حذف فایل موقت
        unlink($zip_path);
        exit;
    }


}
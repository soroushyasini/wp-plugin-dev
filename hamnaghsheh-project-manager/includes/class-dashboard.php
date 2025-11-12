<?php
if (!defined('ABSPATH'))
    exit;

class Hamnaghsheh_Dashboard
{

    public static function render_shortcode()
    {

        $msg = Hamnaghsheh_Users::ensure_user_access();
        if ($msg !== false) {
            return $msg;
        }

        // دریافت لیست پروژه‌های کاربر برای نمایش در داشبورد
        $projects = Hamnaghsheh_Projects::get_user_projects(Hamnaghsheh_Users::current_id());
        $archived_project = Hamnaghsheh_Projects::get_archived_project(Hamnaghsheh_Users::current_id());

        global $wpdb;
        $current_user_id = Hamnaghsheh_Users::current_id();

        $result = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COALESCE(SUM(f.file_size), 0) AS used_space,
                u.storage_limit AS total_space
            FROM {$wpdb->prefix}hamnaghsheh_users AS u
            LEFT JOIN {$wpdb->prefix}hamnaghsheh_projects AS p 
                ON u.user_id = p.user_id
            LEFT JOIN {$wpdb->prefix}hamnaghsheh_files AS f 
                ON p.id = f.project_id
            WHERE u.user_id = %d
            AND p.user_id = %d
            GROUP BY u.storage_limit
        ", $current_user_id, $current_user_id), ARRAY_A);

        // اگر نتیجه خالی بود مقدار پیش‌فرض بده
        $used_space = isset($result['used_space']) ? intval($result['used_space']) : 0;
        $total_space = isset($result['total_space']) ? intval($result['total_space']) : 52428800;

        // محاسبه درصد مصرف
        $percent = $total_space > 0 ? min(100, round(($used_space / $total_space) * 100)) : 0;

        // فرمت خوانا
        $used_human = size_format($used_space);
        $total_human = size_format($total_space);

        // لود قالب داشبورد
        ob_start();
        include HAMNAGHSHEH_DIR . 'templates/dashboard.php';
        return ob_get_clean();
    }
}

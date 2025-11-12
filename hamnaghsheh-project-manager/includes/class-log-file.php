<?php
if (!defined('ABSPATH'))
    exit;

class Hamnaghsheh_Logs
{
    public function __construct()
    {
        add_action('wp_ajax_get_file_logs', [$this, 'get_file_logs']);
        add_action('wp_ajax_nopriv_get_file_logs', 'get_file_logs');
    }

    public function get_file_logs()
    {
        if (!is_user_logged_in())
            wp_send_json_error('Unauthorized');
        global $wpdb;
        $file_id = intval($_GET['file_id']);


        $table_logs = $wpdb->prefix . 'hamnaghsheh_file_logs';
        $table_users = $wpdb->prefix . 'users';

        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, u.display_name AS user_name
            FROM $table_logs AS l
            LEFT JOIN $table_users AS u ON l.user_id = u.ID
            WHERE l.file_id = %d
            ORDER BY l.created_at DESC
        ", $file_id));

        wp_send_json([
            'success' => true,
            'logs' => $logs
        ]);
    }

}
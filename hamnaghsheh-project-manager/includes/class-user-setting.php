<?php
if (!defined('ABSPATH'))
    exit;

class Hamnaghsheh_User_Settings
{

    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'hamnaghsheh_users';

        add_action('show_user_profile', [$this, 'render_fields']);
        add_action('edit_user_profile', [$this, 'render_fields']);

        add_action('personal_options_update', [$this, 'save_fields']);
        add_action('edit_user_profile_update', [$this, 'save_fields']);
    }

    /**
     * واکشی داده کاربر از جدول سفارشی
     */
    private function get_user_data($user_id)
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE user_id = %d", $user_id),
            ARRAY_A
        );
    }

    /**
     * نمایش فیلدها در پروفایل کاربر
     */
    public function render_fields($user)
    {
        $data = $this->get_user_data($user->ID);
        $active = isset($data['active']) ? (bool) $data['active'] : false;
        $storage_limit = isset($data['storage_limit']) ? esc_attr($data['storage_limit']) : '';
        $access_level = isset($data['access_level']) ? esc_attr($data['access_level']) : 'free';
        ?>
        <hr />
        <h2 style="color: #fff;font-weight: bold;background: rgba(9, 55, 91, 1);padding: 10px;">تنظیمات اختصاصی کاربر در هم نقشه</h2>
        <table class="form-table" role="presentation">

            <tr>
                <th><label for="ham_active">وضعیت فعال</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="ham_active" id="ham_active" value="1" <?php checked($active, true); ?> />
                        فعال
                    </label>
                    <p class="description">اگر غیرفعال باشد، کاربر دسترسی به سیستم نخواهد داشت</p>
                </td>
            </tr>

            <tr>
                <th><label for="ham_storage_limit">سقف فضای ذخیره‌سازی (بایت)</label></th>
                <td>
                    <input type="number" name="ham_storage_limit" id="ham_storage_limit" value="<?php echo $storage_limit; ?>"
                        class="regular-text" min="0" />
                    <p class="description">مقدار فضای ذخیره‌سازی کل برای فایل‌های این کاربر در نظر داشته باشید که مقدار باید به
                        بایت ذخیره گردد</p>
                </td>
            </tr>

            <tr>
                <th><label for="ham_access_level">سطح دسترسی</label></th>
                <td>
                    <select name="ham_access_level" id="ham_access_level">
                        <option value="free" <?php selected($access_level, 'free'); ?>>رایگان</option>
                        <option value="premium" <?php selected($access_level, 'premium'); ?>>پرمیوم</option>
                    </select>
                    <p class="description">سطح دسترسی کاربر به امکانات سیستم</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * ذخیره داده‌ها در جدول سفارشی
     */
    public function save_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        global $wpdb;

        $active = isset($_POST['ham_active']) ? 1 : 0;
        $storage_limit = isset($_POST['ham_storage_limit']) ? intval($_POST['ham_storage_limit']) : 0;
        $access_level = isset($_POST['ham_access_level']) ? sanitize_text_field($_POST['ham_access_level']) : 'free';

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d", $user_id));

        if ($exists) {
            $wpdb->update(
                $this->table,
                [
                    'active' => $active,
                    'storage_limit' => $storage_limit,
                    'access_level' => $access_level
                ],
                ['user_id' => $user_id],
                ['%d', '%d', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $this->table,
                [
                    'user_id' => $user_id,
                    'active' => $active,
                    'storage_limit' => $storage_limit,
                    'access_level' => $access_level
                ],
                ['%d', '%d', '%d', '%s']
            );
        }
    }
}
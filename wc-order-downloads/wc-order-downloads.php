<?php
/**
 * Plugin Name: WooCommerce Order Downloads
 * Plugin URI: https://hamnaghsheh.ir
 * Description: Adds custom download functionality to WooCommerce orders. Allows admins to assign download files to orders and customers to download them securely.
 * Version: 1.0.0
 * Author: Soroush yasini
 * Author URI: https://hamnaghsheh.ir
 * Text Domain: wc-order-downloads
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package WC_Order_Downloads
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

// Declare compatibility with WooCommerce HPOS
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Main Plugin Class
 */
class WC_Order_Downloads {
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain( 'wc-order-downloads', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        
        // Initialize hooks
        $this->init_hooks();
        
        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
    }
    
    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'add_download_filename_field' ) );
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_download_filename_field' ) );
        
        // Customer hooks
        add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_customer_download_button' ), 10, 2 );
        add_action( 'wp_head', array( $this, 'download_button_styles' ) );
        add_action( 'init', array( $this, 'handle_customer_file_download' ) );
        
        // My Account customizations
        add_filter( 'woocommerce_account_menu_items', array( $this, 'customize_my_account_menu' ), 20 );
        add_filter( 'woocommerce_endpoint_orders_title', array( $this, 'change_orders_title' ) );
        add_action( 'woocommerce_account_profile_endpoint', array( $this, 'profile_endpoint_content' ) );
        
        // Register profile endpoint query var
        add_filter( 'woocommerce_get_query_vars', array( $this, 'add_profile_query_var' ) );
        
        // Handle profile endpoint title
        add_filter( 'woocommerce_endpoint_profile_title', array( $this, 'profile_endpoint_title' ) );
        
        // Debug hooks (only for admins)
        add_action( 'init', array( $this, 'debug_functions' ) );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        add_option( 'wc_order_downloads_file_path', ABSPATH . 'wp-content/uploads/order-downloads/' );
        add_option( 'wc_order_downloads_version', self::VERSION );
        
        // Create upload directory if it doesn't exist
        $upload_dir = get_option( 'wc_order_downloads_file_path' );
        if ( ! file_exists( $upload_dir ) ) {
            wp_mkdir_p( $upload_dir );
            
            // Add .htaccess for security
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents( $upload_dir . '.htaccess', $htaccess_content );
        }
        
        // Add custom endpoint for profile
        add_rewrite_endpoint( 'profile', EP_ROOT | EP_PAGES );
        
        // Make sure WooCommerce knows about our endpoint
        if ( ! get_option( 'wc_order_downloads_endpoints_flushed' ) ) {
            flush_rewrite_rules();
            update_option( 'wc_order_downloads_endpoints_flushed', '1' );
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        delete_option( 'wc_order_downloads_endpoints_flushed' );
        flush_rewrite_rules();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Order Downloads Settings', 'wc-order-downloads' ),
            __( 'Order Downloads', 'wc-order-downloads' ),
            'manage_woocommerce',
            'wc-order-downloads',
            array( $this, 'admin_page' )
        );
    }
    
    /**
     * Admin settings page
     */
    public function admin_page() {
        if ( isset( $_POST['save_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wc_order_downloads_settings' ) ) {
            update_option( 'wc_order_downloads_file_path', sanitize_text_field( $_POST['file_path'] ) );
            echo '<div class="notice notice-success"><p>' . __( 'Settings saved!', 'wc-order-downloads' ) . '</p></div>';
        }
        
        $file_path = get_option( 'wc_order_downloads_file_path', ABSPATH . 'wp-content/uploads/order-downloads/' );
        ?>
        <div class="wrap">
            <h1><?php _e( 'WooCommerce Order Downloads Settings', 'wc-order-downloads' ); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field( 'wc_order_downloads_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="file_path"><?php _e( 'Download Files Path', 'wc-order-downloads' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="file_path" name="file_path" value="<?php echo esc_attr( $file_path ); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e( 'The server path where download files are stored. Make sure this directory exists and is readable by PHP.', 'wc-order-downloads' ); ?>
                            </p>
                            <p class="description">
                                <strong><?php _e( 'Current path status:', 'wc-order-downloads' ); ?></strong>
                                <?php if ( file_exists( $file_path ) && is_readable( $file_path ) ) : ?>
                                    <span style="color: green;">✓ <?php _e( 'Directory exists and is readable', 'wc-order-downloads' ); ?></span>
                                <?php else : ?>
                                    <span style="color: red;">✗ <?php _e( 'Directory does not exist or is not readable', 'wc-order-downloads' ); ?></span>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( __( 'Save Settings', 'wc-order-downloads' ), 'primary', 'save_settings' ); ?>
            </form>
            
            <hr>
            
            <h2><?php _e( 'Debug Tools', 'wc-order-downloads' ); ?></h2>
            <p>
                <a href="<?php echo admin_url( 'admin.php?page=wc-order-downloads&test_download=1' ); ?>" class="button">
                    <?php _e( 'Test Download Function', 'wc-order-downloads' ); ?>
                </a>
            </p>
            
            <?php if ( isset( $_GET['test_download'] ) ) : ?>
                <div class="notice notice-info">
                    <p><?php _e( 'Download test completed. Check your browser downloads.', 'wc-order-downloads' ); ?></p>
                </div>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * Add download filename field to admin order page
     */
    public function add_download_filename_field( $order ) {
        $download_filename = $order->get_meta( '_order_download_filename' );
        ?>
        <div class="order_data_column" style="width: 48%;">
            <h4><?php _e( 'Download File Settings', 'wc-order-downloads' ); ?></h4>
            <p class="form-field form-field-wide">
                <label for="order_download_filename"><?php _e( 'Download Filename:', 'wc-order-downloads' ); ?></label>
                <input 
                    type="text" 
                    id="order_download_filename" 
                    name="order_download_filename" 
                    value="<?php echo esc_attr( $download_filename ); ?>" 
                    placeholder="example.zip" 
                    style="width: 100%;"
                />
                <span class="description">
                    <?php _e( 'Enter the filename (including extension) that customers can download for this order.', 'wc-order-downloads' ); ?>
                </span>
            </p>
        </div>
        <?php
    }
    
    /**
     * Save download filename field
     */
    public function save_download_filename_field( $order_id ) {
        if ( isset( $_POST['order_download_filename'] ) ) {
            $order = wc_get_order( $order_id );
            $filename = sanitize_file_name( $_POST['order_download_filename'] );
            
            if ( ! empty( $filename ) ) {
                $order->update_meta_data( '_order_download_filename', $filename );
                $order->add_order_note( sprintf( __( 'Download file set to: %s', 'wc-order-downloads' ), $filename ), false );
            } else {
                $order->delete_meta_data( '_order_download_filename' );
            }
            
            $order->save();
        }
    }
    
    /**
     * Add download button to customer orders
     */
    public function add_customer_download_button( $actions, $order ) {
        $download_filename = $order->get_meta( '_order_download_filename' );
        
        if ( ! empty( $download_filename ) ) {
            // File is set - show active download button
            $nonce = wp_create_nonce( 'download_order_file_' . $order->get_id() );
            
            $download_url = add_query_arg( array(
                'download_order_file' => 1,
                'order_id' => $order->get_id(),
                'nonce' => $nonce
            ), home_url() );

            $actions['download_file'] = array(
                'url' => esc_url( $download_url ),
                'name' => __( 'Download File', 'wc-order-downloads' ),
                'action' => 'download_file',
            );
        } else {
            // No file set - show disabled gray button
            $actions['download_file_disabled'] = array(
                'url' => '#',
                'name' => __( 'Download File', 'wc-order-downloads' ),
                'action' => 'download_file_disabled',
            );
        }
        
        return $actions;
    }
    
    /**
     * Add CSS styles for download buttons
     */
    public function download_button_styles() {
        if ( is_account_page() ) {
            ?>
            <style>
            .woocommerce-orders-table__cell-order-actions .button.download_file {
                background-color: #28a745 !important;
                color: white !important;
                border-color: #28a745 !important;
            }
            .woocommerce-orders-table__cell-order-actions .button.download_file:hover {
                background-color: #218838 !important;
                border-color: #1e7e34 !important;
            }
            .woocommerce-orders-table__cell-order-actions .button.download_file_disabled {
                background-color: #6c757d !important;
                color: #fff !important;
                border-color: #6c757d !important;
                cursor: not-allowed !important;
                opacity: 0.6 !important;
            }
            .woocommerce-orders-table__cell-order-actions .button.download_file_disabled:hover {
                background-color: #6c757d !important;
                border-color: #6c757d !important;
                transform: none !important;
            }
            </style>
            <?php
        }
    }
    
    /**
     * Customize My Account menu items
     */
    public function customize_my_account_menu( $items ) {
        // Remove downloads section since we have download buttons in orders
        unset( $items['downloads'] );
        
        // Change "Orders" to "Projects"
        if ( isset( $items['orders'] ) ) {
            $items['orders'] = __( 'پروژه ها', 'wc-order-downloads' );
        }
        
        // Remove edit-address and edit-account to combine them
        unset( $items['edit-address'] );
        unset( $items['edit-account'] );
        
        // Add combined profile section
        $new_items = array();
        foreach ( $items as $key => $item ) {
            $new_items[$key] = $item;
            
            // Add profile after orders
            if ( $key === 'orders' ) {
                $new_items['profile'] = __( 'پروفایل', 'wc-order-downloads' );
            }
        }
        
        return $new_items;
    }
    
    /**
     * Change orders page title
     */
    public function change_orders_title( $title ) {
        return __( 'پروژه ها', 'wc-order-downloads' );
    }
    
    /**
     * Add profile query var to WooCommerce
     */
    public function add_profile_query_var( $vars ) {
        $vars['profile'] = 'profile';
        return $vars;
    }
    
    /**
     * Profile endpoint title
     */
    public function profile_endpoint_title( $title ) {
        return __( 'پروفایل', 'wc-order-downloads' );
    }
    
    /**
     * Profile endpoint content
     */
    public function profile_endpoint_content() {
        $current_user = wp_get_current_user();
        ?>
        <div class="woocommerce-profile-wrapper">
            <h2><?php _e( 'پروفایل کاربری', 'wc-order-downloads' ); ?></h2>
            
            <div class="profile-tabs">
                <nav class="profile-nav">
                    <button class="profile-tab-btn active" onclick="showProfileTab(event, 'account-details')"><?php _e( 'اطلاعات حساب', 'wc-order-downloads' ); ?></button>
                    <button class="profile-tab-btn" onclick="showProfileTab(event, 'addresses')"><?php _e( 'آدرس ها', 'wc-order-downloads' ); ?></button>
                </nav>
                
                <div id="account-details" class="profile-tab-content active">
                    <?php 
                    // Load WooCommerce edit account form
                    wc_get_template( 'myaccount/form-edit-account.php', array( 'user' => $current_user ) );
                    ?>
                </div>
                
                <div id="addresses" class="profile-tab-content">
                    <?php 
                    // Load WooCommerce edit address form
                    wc_get_template( 'myaccount/my-address.php' );
                    ?>
                </div>
            </div>
        </div>
        
        <style>
        .woocommerce-profile-wrapper {
            max-width: 100%;
        }
        
        .profile-tabs {
            margin-top: 20px;
        }
        
        .profile-nav {
            border-bottom: 2px solid #e1e1e1;
            margin-bottom: 20px;
        }
        
        .profile-tab-btn {
            background: none;
            border: none;
            padding: 15px 20px;
            cursor: pointer;
            font-size: 16px;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        
        .profile-tab-btn:hover,
        .profile-tab-btn.active {
            color: #333;
            border-bottom-color: #007cba;
        }
        
        .profile-tab-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .profile-tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* RTL Support */
        .profile-tab-btn {
            margin-right: 0;
            margin-left: 10px;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .profile-nav {
                display: flex;
                flex-wrap: wrap;
            }
            
            .profile-tab-btn {
                flex: 1;
                text-align: center;
                margin: 0 5px 10px 0;
                padding: 12px 15px;
                font-size: 14px;
            }
        }
        </style>
        
        <script>
        function showProfileTab(evt, tabName) {
            var i, tabcontent, tablinks;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("profile-tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from all buttons
            tablinks = document.getElementsByClassName("profile-tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            
            // Show selected tab and mark button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
        </script>
        <?php
    }
    
    /**
     * Handle customer file downloads
     */
    public function handle_customer_file_download() {
        if ( ! isset( $_GET['download_order_file'] ) || ! isset( $_GET['order_id'] ) || ! isset( $_GET['nonce'] ) ) {
            return;
        }
        
        $order_id = intval( $_GET['order_id'] );
        $nonce = sanitize_text_field( $_GET['nonce'] );
        $current_user_id = get_current_user_id();
        
        // Security checks
        if ( ! wp_verify_nonce( $nonce, 'download_order_file_' . $order_id ) ) {
            wp_die( __( 'Security verification failed.', 'wc-order-downloads' ), __( 'Download Error', 'wc-order-downloads' ), array( 'response' => 403 ) );
        }
        
        if ( ! $current_user_id ) {
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
        
        $order = wc_get_order( $order_id );
        
        if ( ! $order || $order->get_user_id() !== $current_user_id ) {
            wc_add_notice( __( 'You do not have permission to download this file.', 'wc-order-downloads' ), 'error' );
            wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
            exit;
        }
        
        $download_filename = $order->get_meta( '_order_download_filename' );
        
        if ( empty( $download_filename ) ) {
            wc_add_notice( __( 'No download file available for this order.', 'wc-order-downloads' ), 'error' );
            wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
            exit;
        }
        
        $file_path = get_option( 'wc_order_downloads_file_path' ) . basename( $download_filename );
        
        if ( ! file_exists( $file_path ) ) {
            wc_add_notice( sprintf( __( 'Download file "%s" not found.', 'wc-order-downloads' ), $download_filename ), 'error' );
            wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
            exit;
        }
        
        // Log the download
        $order->add_order_note( sprintf( 
            __( 'Customer downloaded file: %s (IP: %s)', 'wc-order-downloads' ), 
            $download_filename,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ), false );
        
        // Send the file
        $this->send_file_download( $file_path, $download_filename );
    }
    
    /**
     * Send file for download
     */
    private function send_file_download( $file_path, $filename ) {
        if ( ob_get_level() ) {
            ob_end_clean();
        }
        
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . filesize( $file_path ) );
        
        if ( ! ini_get( 'safe_mode' ) ) {
            set_time_limit( 0 );
        }
        
        readfile( $file_path );
        exit;
    }
    
    /**
     * Debug functions
     */
    public function debug_functions() {
        // Test download function
        if ( isset( $_GET['test_download'] ) && current_user_can( 'manage_options' ) ) {
            $test_content = "Test download file created on " . date( 'Y-m-d H:i:s' );
            
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Disposition: attachment; filename="test-download.txt"' );
            header( 'Content-Length: ' . strlen( $test_content ) );
            
            echo $test_content;
            exit;
        }
        
        // Check download field debug
        if ( isset( $_GET['check_download_field'] ) && current_user_can( 'manage_options' ) ) {
            $order_id = intval( $_GET['check_download_field'] );
            $order = wc_get_order( $order_id );
            
            if ( $order ) {
                echo "<h2>Download Field Check for Order #$order_id</h2>";
                echo "<p><strong>Filename:</strong> " . $order->get_meta( '_order_download_filename' ) . "</p>";
                
                if ( empty( $order->get_meta( '_order_download_filename' ) ) ) {
                    echo "<p style='color: red;'>No download filename set for this order.</p>";
                } else {
                    echo "<p style='color: green;'>✓ Download filename is set!</p>";
                }
            }
            exit;
        }
    }
}

// Initialize the plugin
WC_Order_Downloads::get_instance();
<?php
/**
 * Remove old file management interface and replace with projects-only
 */

if (!defined('ABSPATH')) {
    exit;
}

class UFS_Remove_Old_Interface {
    
    public function __construct() {
        // Remove the old file_storage_content method
        add_action('woocommerce_account_file-storage_endpoint', array($this, 'redirect_to_projects'), 1);
    }
    
    /**
     * Redirect old interface to projects interface
     */
    public function redirect_to_projects() {
        // Remove default action - we only want projects interface
        remove_all_actions('woocommerce_account_file-storage_endpoint', 10);
    }
}

new UFS_Remove_Old_Interface();
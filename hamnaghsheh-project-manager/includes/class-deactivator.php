<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Hamnaghsheh_Deactivator {
    public static function deactivate() {
        // On deactivate we do not remove data; uninstall.php handles permanent deletion if user removes plugin.
    }
}

<?php

include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

class Wpmc_Empty_Bulk_Upgrader_Skin extends Bulk_Plugin_Upgrader_Skin {

    public function feedback($string) {
        // Leave empty.
    }
    
    public function before($title = '') {
        // Leave empty.
    }

    public function after($title = '') {
        // Leave empty.
    }

}
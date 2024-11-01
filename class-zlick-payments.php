<?php

class Zlick_Payments {

    public static function init() {

    }

    /**
     * Activates plugins.
     */
    public static function plugin_activation() {
        $db_handler = new Zlick_Payments_Db_Handler();
        $db_handler->createDbTables();

        // Create the Apple Pay verification file in the site root.
	    zlickpay_create_apple_verification_file();
    }

    /**
     * Deactivates Plugin.
     */
    public static function plugin_deactivation() {
        $db_handler = new Zlick_Payments_Db_Handler();
        $db_handler->dropTable();
    }
}

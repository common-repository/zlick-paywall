<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_filter( 'plugin_action_links_zlick-paywall/zlick-payments.php', 'add_settings_link', 10, 2 );
/**
 * Applied to the list of links to display on the plugins page (beside the activate/deactivate links).
 * @since 2.7.0
 * @return array
*/
function add_settings_link( $links ) {
    try {
		$url = get_admin_url() . "options-general.php?page=zlick-payments-plugin";
    	$settings_link = '<a href="' . $url . '">' . __('Settings', 'textdomain') . '</a>';
      	$links[] = $settings_link;
    	return $links;
    } catch (Exception $e) {
        return $links;
    }
}

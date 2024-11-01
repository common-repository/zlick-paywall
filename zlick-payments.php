<?php
/**
 * Zlick Payments Plugin
 *
 * @category  Zlick
 * @package   Zlick
 * @author    Zlick <info@zlick.it>
 * @copyright Copyright (c) 2023 Zlick ltd (https://www.zlick.it)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://zlick.it
 *
 * Plugin Name: Zlick Paywall
 * Plugin URI:  https://zlick.it
 * Description: Sell subscriptions and one-off access to your content with industry-leading conversion rates, a simple platform to operate, and no upfront costs.
 * Version:     3.3.4
 * Author:      Zlick
 * Author URI:  info@zlick.it
 * Text Domain: zlick-paywall
 */

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}


$plugin_data = get_file_data(__FILE__, [
    'Version' => 'Version'
], 'plugin');


// define necessary variable for the site.
define( 'ZLICK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZLICK_PLUGIN_NAME', 'zlick-payments' );
define( 'ZLICK_PLUGIN_VERSION', $plugin_data['Version'] );
define( 'ZLICK_PAYMENTS_STATUS_ENABLED', 1 );
define( 'ZLICK_POST_TYPE', "articles" );
define( 'ZLICK_POST_TYPE_POST', "post" );
define( 'ZLICK_PREVIEWABLE_CONTENT_PARA_DEFAULT', 2 );
define( 'ZLICK_URL', plugins_url( '', __FILE__ ) );
define( 'ZLICK_LOCAL', dirname( __FILE__ ) );
define( 'ZLICK_AJAX_URL', admin_url( 'admin-ajax.php' ) );

require_once( ZLICK_PLUGIN_DIR . 'includes/database_handler.php' );
require_once( ZLICK_PLUGIN_DIR . 'includes/search_engine.php' );
require_once( ZLICK_PLUGIN_DIR . 'class-zlick-payments.php' );

/**
 * Gets the configurations.
 *
 * @return array
 */
function zlickpay_get_options_data( $key = 'all') {
	$zlick_options = get_option( 'zlick_payments_settings', [] );

	if(empty($zlick_options)){
		return;
	}

	if ($key == 'all'){
		return $zlick_options;
	}

	if(!isset($zlick_options[$key])){
		return;
	}

	return $zlick_options[$key];
}

require_once( ZLICK_PLUGIN_DIR . 'includes/price_plans.php' );
require_once( ZLICK_PLUGIN_DIR . 'admin/class-zlick-payments-admin.php' );
require_once( ZLICK_PLUGIN_DIR . 'includes/stripe_functions.php' );
require_once( ZLICK_PLUGIN_DIR . 'includes/mixpanel_functions.php' );
require_once( ZLICK_PLUGIN_DIR . 'includes/plugin_settings.php' );
require_once( ZLICK_PLUGIN_DIR . 'includes/plugin_bulk_actions.php' );
require_once( ZLICK_PLUGIN_DIR . 'includes/post_callbacks.php' );

register_activation_hook( __FILE__, array( 'Zlick_Payments', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'Zlick_Payments', 'plugin_deactivation' ) );

add_action( 'init', array( 'Zlick_Payments', 'init' ) );

require_once( ZLICK_PLUGIN_DIR . 'includes/validate_jwt.php');
require_once( ZLICK_PLUGIN_DIR . 'includes/user.php');

/**
 * Register Zlick Payment ShortCodes.
 */
require_once( ZLICK_PLUGIN_DIR . 'public/shortcodes/zlick-widget.php' );

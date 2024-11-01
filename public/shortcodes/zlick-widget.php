<?php

/**
 * Zlick Payments Plugin
 *
 * @category  Zlick
 * @package   Zlick
 * @author    Arsalan Ahmad <arsalan@zlick.it>
 * @copyright Copyright (c) 2018 Zlick ltd (https://www.zlick.it)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.zlick.it
 */

function zlickpay_widget_placeholder()
{
	return '';
}

/**
 * Returns the smart prc widget.
 *
 * @param string $post_id The post id.
 *
 * @return string
 */
function zlickpay_widget_link_shortcode($data)
{
	// return (is_single() || is_page());
	$should_return = is_singular(array('post', 'page'));
	if (!$should_return) {
		return '';
	}

	$post_id = get_the_ID();
	if (!empty($post_id)) {
		$post_title = get_the_title($post_id);

		$zp_price_plans = array();
		$zp_related_categories = array();
		$zp_content_categories = array();
		$zp_custom_fields = get_post_custom($post_id);
		if (!isset($zp_custom_fields['zp_is_paid']) || $zp_custom_fields['zp_is_paid'][0] != 'paid'){
			$arr = get_valid_category_price_plan_ids_and_related_categories_on_post( $post_id );
			$zp_price_plans = $arr["selected_price_plan_ids"];
			$zp_related_categories = $arr["related_category_ids"];
			$zp_content_categories = wp_get_post_categories($post_id, array('fields' => 'ids'));
		}

		$article_price = '';
		if (zlickpay_cookie_is_valid_subscription() or zlickpay_cookie_is_purchased_article(trim($post_id))) {
			return '';
		} else {
			$widget_text = get_option('zp_widget_text');
			$zp_enable_subscription = get_option('zp_enable_subscription', '');
			$zp_article_price = zlickpay_sanitize_price($article_price);
			$zp_widget_text = str_replace('{price}', $article_price, $widget_text);
			// Documented in readme under "Technical" section.
			wp_enqueue_style('zlick-payments-fade-css', esc_url(plugins_url('styles/fade.css', dirname(__FILE__))), false, '1.0', 'all');
			wp_enqueue_script('zlick-payments-fade-js', esc_url(plugins_url('js/fade.js', dirname(__FILE__))), '', '', true);
			wp_enqueue_script('zlick-payments-sdk', 'https://cdn.zlick.it/zlick-paywall-element-2.0.0.js', '', '', true);
			wp_enqueue_script(ZLICK_PLUGIN_NAME, array('jquery', 'zlick-payments-sdk'), '1.0', true);
			wp_localize_script(
				ZLICK_PLUGIN_NAME,
				'zlick_payments_ajax',
				array(
					'ajax_url' => ZLICK_AJAX_URL,
					'client_token' => zlickpay_get_options_data('zp_client_token'),
					'user_id' => zlickpay_get_current_user_id(),
					'article_id' => $post_id,
					'zp_article_name' => $post_title,
					'zp_price_plans' => $zp_price_plans,
					'zp_related_categories' => $zp_related_categories,
					'zp_content_categories' => $zp_content_categories,
					'zp_plugin_version' => ZLICK_PLUGIN_VERSION,
					'zp_ajax_nonce' => wp_create_nonce("zp-ajax-nonce")
				)
			);
			ob_start();
			include ZLICK_PLUGIN_DIR . 'public/templates/zlick-widget.php';
			$widget = ob_get_contents();
			ob_end_clean();

			return $widget;
		}
	}

	return '';
}

/**
 * Register short codes.
 */
add_shortcode('zlick_payment_widget', 'zlickpay_widget_link_shortcode');
add_shortcode('zp_placeholder', 'zlickpay_widget_placeholder');

/**
 * Register Ajax callback for logged in and anonymous users
 */
add_action('wp_ajax_zp_authenticate_article', 'zlickpay_authenticate_article');
add_action('wp_ajax_nopriv_zp_authenticate_article', 'zlickpay_authenticate_article');

/**
 * Register Ajax callback for expired subscription
 */
add_action('wp_ajax_zp_access_expired', 'zlickpay_access_expired');
add_action('wp_ajax_nopriv_zp_access_expired', 'zlickpay_access_expired');



function zlickpay_shortcode_resource()
{
	wp_register_script(ZLICK_PLUGIN_NAME, plugins_url("js/zlick-widget.js", __DIR__), array('jquery'), "1.0", false);
}

add_action('init', 'zlickpay_shortcode_resource');

if (!function_exists('zlickpay_cookie_register_article')) {

	function zlickpay_cookie_register_article($article_id)
	{

		$secret = zlickpay_get_options_data('zp_client_secret');
		// $zp_article_cookie_content signed token
		$zp_article_cookie_content = sanitize_text_field(isset($_COOKIE["zp_articles"]) ? $_COOKIE["zp_articles"] : '');

		if (empty($zp_article_cookie_content) || !isset($zp_article_cookie_content)) {
			$authed_articles = array();
		} else {
			$payload = zlickpay_get_jwt_payload($zp_article_cookie_content);
			$authed_articles = explode(",", $payload);
		}


		$authed_articles[] = $article_id;
		$authed_articles = array_unique($authed_articles);

		$payload = join(",", $authed_articles);

		setcookie("zp_articles", zlickpay_jwt_sign($secret, $payload), time() + 60 * 60 * 24 * 365 * 10, "/");
	}
}

if (!function_exists('zlickpay_cookie_register_subscription')) {
	function zlickpay_cookie_register_subscription($categories_access_map)
	{
		$zp_options = zlickpay_get_options_data();
		$client_token = $zp_options['zp_client_token'];
		$client_secret = $zp_options['zp_client_secret'];

		if(empty($categories_access_map) || !isset($categories_access_map)){
			$cookie_val = zlickpay_jwt_sign($client_secret, $client_token);
		} else {
			$cookie_val = zlickpay_jwt_sign($client_secret, json_encode($categories_access_map));
		}

		setcookie("zp_subscription", $cookie_val, time() + 60 * 60 * 3, "/");
	}
}

if (!function_exists('zlickpay_cookie_unregister_all')) {
	function zlickpay_cookie_unregister_all()
	{
		setcookie("zp_subscription", "",  time() - 10000, "/");
		setcookie("zp_articles", "",  time() - 10000, "/");
	}
}

if(!function_exists('paywall_wordpress_error_dispatch')) {
	function paywall_wordpress_error_dispatch($message)
	{
		$body = array(
			'error_recieved' => $message,
		);
		$zp_options = zlickpay_get_options_data();
		$client_token = $zp_options['zp_client_token'];
		$client_secret = $zp_options['zp_client_secret'];
		$headers = array(
			'client-token' => $client_token,
			'client-secret' => $client_secret,
		);

		$args = array(
			'body' => $body,
			'headers' => $headers,
		);

		wp_remote_retrieve_body( wp_remote_post( 'https://portal.zlickpaywall.com/api/wordpress_error', $args ));
	}
}

if (!function_exists('write_log')) {

	function write_log($log) {
			if (true === WP_DEBUG) {
					if (is_array($log) || is_object($log)) {
							error_log(print_r($log, true));
					} else {
							error_log($log);
					}
			}
	}

}

if (!function_exists('zlickpay_access_expired')) {
	/**
	 * @return false|int|null
	 */
	function zlickpay_access_expired()
	{
		// write_log('Started authentication of article')
		// Check for nonce security
		if (!isset($_POST['zp_nonce']) || !wp_verify_nonce($_POST['zp_nonce'], 'zp-ajax-nonce')) {
			// Replying in code so that we don't reveal exactly what went wrong to the attacker
			// But we can still identify causes of the issues
			write_log('zlickpay_access_expired: Nounce Error on authentication');
			// paywall_wordpress_error_dispatch('Nonce issue Code ZPZW101');
			wp_die('Request could not be verified: Code ZPZW101');
		}

		$zp_options = zlickpay_get_options_data();
		$client_token = $zp_options['zp_client_token'];
		$client_secret = $zp_options['zp_client_secret'];
		$zp_signed_payment_info = sanitize_text_field($_POST['zp_signed_payment_info']);

		if (!zlickpay_validate_sign($client_secret, $zp_signed_payment_info)) {
			write_log('zlickpay_access_expired: Cookies provided were not upto the mark');
			paywall_wordpress_error_dispatch('Access Expired Request could not be verified: Code ZPZW102');
			wp_die('Request could not be verified: Code ZPZW102');
		}

		$signedPaymentInfoPayload = json_decode(zlickpay_get_jwt_payload($zp_signed_payment_info), true);

		$username = $signedPaymentInfoPayload["userId"] . '_' . $signedPaymentInfoPayload["subscribedPricePlan"];

		// clear any user if already logged in
		if (is_user_logged_in()){
			wp_logout();
		}

		$zp_sync_wp_users_with_subscribers = rest_sanitize_boolean($_POST['zp_sync_wp_users_with_subscribers']);

		if ($zp_sync_wp_users_with_subscribers === true) {
			$user = get_user_by('login', $username);
			if (!empty($user)) {
				wp_delete_user($user->id);
			}
		}

		zlickpay_cookie_unregister_all();

		wp_die("reload");

		return 'Done. Reload the page now';
	}
}

if (!function_exists('zlickpay_authenticate_article')) {

	/**
	 * @return false|int|null
	 */
	function zlickpay_authenticate_article()
	{
		// write_log('Started authentication of article')
		// Check for nonce security
		if (!isset($_POST['zp_nonce']) || !wp_verify_nonce($_POST['zp_nonce'], 'zp-ajax-nonce')) {
			// Replying in code so that we don't reveal exactly what went wrong to the attacker
			// But we can still identify causes of the issues
			write_log('Nounce Error on authentication');
			// paywall_wordpress_error_dispatch('Nonce issue Code ZPZW101');
			wp_die('Request could not be verified: Code ZPZW101');
		}

		$zp_options = zlickpay_get_options_data();
		$client_token = $zp_options['zp_client_token'];
		$client_secret = $zp_options['zp_client_secret'];
		$zp_signed = sanitize_text_field($_POST['zp_signed']);
		$zp_signed_payment_info = sanitize_text_field($_POST['zp_signed_payment_info']);

		if (!zlickpay_validate_sign($client_secret, $zp_signed)) {
			write_log('Cookies provided were not upto the mark');
			paywall_wordpress_error_dispatch('Request could not be verified: Code ZPZW102');
			wp_die('Request could not be verified: Code ZPZW102');
		}

		$signedPayload = json_decode(zlickpay_get_jwt_payload($zp_signed), true);
		$signedPaymentInfoPayload = json_decode(zlickpay_get_jwt_payload($zp_signed_payment_info), true);


		if (isset($signedPayload["transaction"]["hasAccess"]) && $signedPayload["transaction"]["hasAccess"] === true) {
			$article_id = intval($signedPayload["transaction"]["productId"]);
			if (!$article_id) {
			write_log('Cookies provided were not upto the mark and could not be verified');
			paywall_wordpress_error_dispatch('Request could not be verified: Code ZPZW102');
				wp_die('Request could not be verified: Code ZPZWA101');
			}
			zlickpay_cookie_register_article($article_id);
		} elseif (isset($signedPayload["subscription"]["hasAccess"]) && $signedPayload["subscription"]["hasAccess"] === true) {
			zlickpay_cookie_register_subscription($signedPayload["subscription"]["categories"]);
			write_log('Cookies registered subscription call');

			$username = $signedPaymentInfoPayload["userId"] . '_' . $signedPaymentInfoPayload["subscribedPricePlan"];
			$password = $signedPaymentInfoPayload["subscriptionId"]; // TODO sign with client secret maybe resuse function zlickpay_jwt_sign

			// clear any user if already logged in
			if (is_user_logged_in()){
				wp_logout();
			}

			$zp_sync_wp_users_with_subscribers = rest_sanitize_boolean($_POST['zp_sync_wp_users_with_subscribers']);
			if ($zp_sync_wp_users_with_subscribers === true) {
				$user = get_user_by('login', $username);
				if ($user){
					wp_signon(array("user_login" => $username, "user_password" => $password ));
				} else {
					wp_create_user($username, $password);
					wp_signon(array("user_login" => $username, "user_password" => $password ));
				}
				write_log('zp user logged in');
				$id = get_current_user_id();
				$u = new WP_User( $id );
				$u->set_role('subscriber');

				write_log('zp user role set');
			}
		} else {
			zlickpay_cookie_unregister_all();
			write_log('Cookies unregister everything');
		}
		wp_die("reload");

		return 'Done. Reload the page now';
	}
}

if (!function_exists('zlickpay_cookie_is_valid_subscription')) {

	function zlickpay_cookie_is_valid_subscription()
	{
		global $post;
		// $cookie_val contains jwt signed string that is validated before further use
		$cookie_val = sanitize_text_field(isset($_COOKIE["zp_subscription"]) ? $_COOKIE["zp_subscription"] : "");
		$secret = zlickpay_get_options_data('zp_client_secret');

		if ($cookie_val === "") {
			return false;
		}
		if (!zlickpay_validate_sign($secret, $cookie_val)) {
			return false;
		}

		$payload = zlickpay_get_jwt_payload($cookie_val);
		try {
			$categories_access_map = json_decode($payload, true);
			$category_ids = wp_get_post_categories($post->ID, array('fields' => 'ids'));
			foreach ($category_ids as $category_id) {
				if (isset($categories_access_map[$category_id]) && $categories_access_map[$category_id] === true) {
					return true;
				}
			}
			return $payload === zlickpay_get_options_data('zp_client_token');
		} catch (Exception $e) {
			return $payload === zlickpay_get_options_data('zp_client_token');
		}
	}
}

if (!function_exists('zlickpay_cookie_is_purchased_article')) {

	function zlickpay_cookie_is_purchased_article($article_id)
	{
		// $cookie_val contains jwt signed string that is validated before further use
		$cookie_val = sanitize_text_field(isset($_COOKIE["zp_articles"]) ? $_COOKIE["zp_articles"] : "");
		$secret = zlickpay_get_options_data('zp_client_secret');

		if ($cookie_val === "") {
			return false;
		}
		if (!zlickpay_validate_sign($secret, $cookie_val)) {
			return false;
		}

		$payload = zlickpay_get_jwt_payload($cookie_val);
		$purchased_articles = explode(",", $payload);

		return in_array($article_id, $purchased_articles);
	}
}

if (!function_exists('zlickpay_find_number_paragraphs')) {
	/**
	 * Calculate previewable paragrpahs
	 *
	 * @param $content
	 * @param $para_count
	 * @param $is_block_editor
	 *
	 * @return false|string
	 */
	function zlickpay_find_number_paragraphs($content, $para_count, $is_block_editor)
	{
		$para_i = 0;
		$char_count = 0;
		$needle = $is_block_editor ? '<!-- /wp:paragraph -->' : '</p>';
		$needle_start = zlickpay_find_starting_paragraph_html($content, $is_block_editor);
		$starting_tag = substr($content, 0, strlen($needle_start));
		if ($starting_tag != $needle_start) {
			$char_count = strpos($content, $needle_start);
		}

		do {
			$para_i++;
			$offset = $char_count + 1;
			if ($offset >= strlen($content)) {
				break;
			}
			$ti = strpos($content, $needle, $offset);
			if ($ti === FALSE) {
				break;
			}
			$char_count = $ti;
		} while ($para_i < $para_count);

		return $char_count;
	}
}

if (!function_exists('zlickpay_find_starting_paragraph_html')) {

	/* Find the starting paragraph from where we need to limit the content */
	function zlickpay_find_starting_paragraph_html($content, $is_block_editor) {
		if($is_block_editor) { return '<!-- wp:paragraph -->'; }

		$para_with_styling = strpos($content, '<p ');
		$para_without_styling = strpos($content, '<p>');

		if ($para_with_styling !== false && $para_without_styling === false) {
			return '<p ';
		} else if ($para_with_styling === false && $para_without_styling !== false) {
			return '<p>';
		} else if ($para_with_styling !== false && $para_without_styling !== false) {
			return ($para_with_styling < $para_without_styling ?  '<p ' : '<p>');
		}

		return '<p ';
	}

}

if (!function_exists('zlickpay_replace_wp_file_tag')) {
	/**
	 * Replaces WordPress file tags in the content.
	 *
	 * @param string $content The content to replace file tags in.
	 * @return string The modified content with replaced file tags.
	 */
	function zlickpay_replace_wp_file_tag($content) {
		return preg_replace("/<div class=\"wp-block-file\">(.+?)<\/div>/is", "<p class='wp-block-file'>$1</p>", $content);
	}

}

if (!function_exists('zlickpay_post_content')) {

	/**
	 * Find a string position.
	 *
	 * @param string $content The original post content.
	 * @param bool $is_block_editor Whether the content is being rendered in the block editor.
	 * @return string The modified post content.
	 */
	function zlickpay_post_content($content, $is_block_editor) {
		$needles = $is_block_editor ? ['<!-- /wp:paragraph -->', '<!-- /wp:file -->' , '</figure>'] : ['</p>', '</div>', '</figure>'];
		$tags = array();

		foreach($needles as $needle) {
			$tag_position = strrpos($content, $needle);
			if($tag_position !== false) $tags[$tag_position] = $needle;
		}

		if (empty($tags)) return '';

		$tag_positions = array_keys($tags);
		$selected_tag_position = max($tag_positions);

		return substr($content, $selected_tag_position + strlen($tags[$selected_tag_position]));
	}
}

function zlickpay_add_noarchive_meta()
{
	$post_id = get_the_ID();
	$zp_custom_fields = get_post_custom($post_id);

	if (!isset($zp_custom_fields['zp_is_paid']) || $zp_custom_fields['zp_is_paid'][0] != 'paid') {
		return;
	}
	// Now it means that the article is paid so it should not be archived
	echo '<meta name="robots" content="noarchive">';
	return;
}

if (!function_exists('zlickpay_limit_content')) {
	/**
	 * Limit Content renerding to previewable_paragraph option
	 *
	 * @param $content
	 *
	 * @return false|string
	 */
	function zlickpay_limit_content($content)
	{
		$post_id = get_the_ID();
		$zp_custom_fields = get_post_custom($post_id);
		$is_monetized_via_category = is_monetized_with_category_pricing($post_id);

		if ((!isset($zp_custom_fields['zp_is_paid']) || $zp_custom_fields['zp_is_paid'][0] != 'paid') && !$is_monetized_via_category) {
			return $content;
		}

		if (empty(zlickpay_get_options_data('zp_client_token'))) {
			echo '<!-- zp client token has not been set -->';
			paywall_wordpress_error_dispatch('Client Token Has not been set');
			return $content;
		}

		if (zlickpay_get_options_data('zp_search_engine_bypass') && zlickpay_is_search_engine()) {
			return $content;
		}

		// gutenberg editor gives content in paragraphs with many newlines. No <p> tags.
		// classic editor gives a mix of html and new line seperated paragraphs

		$content = zlickpay_replace_wp_file_tag($content);

		$is_block_editor = false;

		if (strpos($content, "<!-- /wp:paragraph -->") > 0) {
			$is_block_editor = true;
		}

		if (!$is_block_editor) {
			$content = wpautop($content);
			remove_filter('the_content', 'wpautop');
		}

		$needle_end = $is_block_editor ? '<!-- /wp:paragraph -->' : '</p>';

		$needle = "<!--zlick-paywall-->";
		$other_needle = "&lt;!&#8211;zlick-paywall&#8211;&gt;";
		$content_length = strpos($content, $needle);
		$other_content_length = strpos($content, $other_needle);
		$pre_content = '';

		if (in_array(get_post_type() , [ZLICK_POST_TYPE_POST, 'page'])) {
			if (!zlickpay_cookie_is_valid_subscription() && !zlickpay_cookie_is_purchased_article($post_id)) {
				if ($content_length === false) {
					if($other_content_length === false){
						$para_count = zlickpay_get_options_data('zp_previewable_paras');

						$content_length = zlickpay_find_number_paragraphs($content, $para_count, $is_block_editor);
						$pre_content = substr($content, 0, $content_length + strlen($needle_end) );
					} else {
						$pre_content = substr($content, 0, $other_content_length );
					}
				} else{
					$para_count = zlickpay_get_options_data('zp_previewable_paras');
					$pre_content = substr($content, 0, $content_length );
				}
				$post_content = zlickpay_post_content($content, $is_block_editor);
				$content = $pre_content . do_shortcode("[zlick_payment_widget post_id=" . $post_id . "]") . $post_content;
			}
		}
		return $content;
	}
}

add_filter("the_content", "zlickpay_limit_content", 12);
add_action('wp_head', 'zlickpay_add_noarchive_meta');

if (!function_exists('zlickpay_limit_feed_content')) {
	/**
	 * Limit Content renerding to previewable_paragraph option
	 *
	 * @param $content
	 * @since 2.7.4
	 * @return false|string
	 */
	function zlickpay_limit_feed_content($content)
	{

		$post_id = get_the_ID();
		$zp_custom_fields = get_post_custom($post_id);

		if (!isset($zp_custom_fields['zp_is_paid']) || $zp_custom_fields['zp_is_paid'][0] != 'paid') {
			return $content;
		}

		if (empty(zlickpay_get_options_data('zp_client_token'))) {
			echo '<!-- zp client token has not been set -->';
			return $content;
		}

		// gutenberg editor gives content in paragraphs with many newlines. No <p> tags.
		// classic editor gives a mix of html and new line seperated paragraphs

		$content = zlickpay_replace_wp_file_tag($content);
		$is_block_editor = false;

		if (strpos($content, "<!-- /wp:paragraph -->") > 0) {
			$is_block_editor = true;
		}

		if (!$is_block_editor) {
			$content = wpautop($content);
			remove_filter('the_content', 'wpautop');
		}

		$needle_end = $is_block_editor ? '<!-- /wp:paragraph -->' : '</p>';
		$pre_content = '';

		$needle = "<!--zlick-paywall-->";
		$content_length = strpos($content, $needle);

		if (in_array(get_post_type(), [ZLICK_POST_TYPE_POST, 'page'])) {
			if ($content_length === false) {
				$para_count = zlickpay_get_options_data('zp_previewable_paras');

				$content_length = zlickpay_find_number_paragraphs($content, $para_count, $is_block_editor);
				$pre_content = substr($content, 0, $content_length + strlen($needle_end) );
			} else {
				$pre_content = substr($content, 0, $content_length );
			}
			$post_content = zlickpay_post_content($content, $is_block_editor);
			$content = $pre_content . do_shortcode("[zlick_payment_widget post_id=" . $post_id . "]") . $post_content;
		}

		return $content;
	}
}

add_filter( 'the_content_feed', "zlickpay_limit_feed_content");


if (!function_exists('zlickpay_get_options_data')) {
	/**
	 * Retrieve saved configurations option by key|all
	 *
	 * @return array
	 */
	function zlickpay_get_options_data($key = 'all')
	{
		$zlick_options = get_option('zlick_payments_settings', []);

		if (empty($zlick_options)) {
			return;
		}

		return $key == 'all' ? $zlick_options : $zlick_options[$key];
	}
}

if (!function_exists('zlickpay_sanitize_price')) {
	/**
	 * Round the Price upto 2 decimal values
	 *
	 * @return array
	 */
	function zlickpay_sanitize_price($price)
	{
		$iprice = (float) $price;
		$iprice = round($iprice * 100);
		return $iprice;
	}
}

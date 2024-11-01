<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function in_multidimensional_array($elem, $arr) {
    foreach ($arr as $value) {
        if (is_array($value) && in_multidimensional_array($value, $elem)) {
            return true;
        } elseif ($value === $elem) {
            return true;
        }
    }
    return false;
}

/**
 * Fetches configured price plans from backend
 * @since 3.1.0
 * @return array
*/
function fetch_price_plans() {
	$last_call = get_option('last_successful_call', 0);
	$current_time = time();

	// Check if 5 minutes (300 seconds) have passed since the last successful call
	if (($current_time - $last_call) < 100) {
			// Return the stored result if less than 5 minutes have passed
			return get_option('last_successful_result', "No previous result found.");
	}

	$client_token = zlickpay_get_options_data('zp_client_token');
	$client_secret = zlickpay_get_options_data('zp_client_secret');

	$headers = array(
			'client-token' => $client_token,
			'client-secret' => $client_secret
	);
	$args = array(
			'headers' => $headers,
	);
	$response =  wp_remote_get( 'https://portal.zlickpaywall.com/api/price_plan', $args );

	if (is_wp_error($response)) {
			return $response->get_error_message();
	} else {
			$body = wp_remote_retrieve_body($response);
			$result = json_decode($body);

			// Store the current time and the result of the successful call
			update_option('last_successful_call', $current_time);
			update_option('last_successful_result', $result);

			return $result;
	}
}


function get_valid_category_price_plans_on_post($post_id) {
	$price_plans = fetch_price_plans();
	$categories = wp_get_post_categories( $post_id );
	$selected_price_plans = array();
	foreach ( $categories as $category_id ) {
		$active_price_plans_on_category = get_term_meta( $category_id, '_zp_price_plans' );
		if (count($active_price_plans_on_category) >= 1){
			foreach ( $price_plans as $pp ) {
				$pp_value = strval($pp->id);
				if ( in_array( $pp_value, $active_price_plans_on_category ) && !in_array($pp, $selected_price_plans) ) {
					$selected_price_plans[] = $pp;
				}
			}
		}
	}
	return $selected_price_plans;
}

function get_valid_category_price_plan_ids_and_related_categories_on_post($post_id) {
	$price_plans = fetch_price_plans();
	$categories = wp_get_post_categories( $post_id );
	$selected_price_plan_ids = array();
	$related_category_ids = array();
	foreach ( $categories as $category_id ) {
		$active_price_plans_on_category = get_term_meta( $category_id, '_zp_price_plans' );
		if (count($active_price_plans_on_category) >= 1){
			foreach ( $price_plans as $pp ) {
				$pp_value = strval($pp->id);
				if ( in_array( $pp_value, $active_price_plans_on_category ) && !in_array($pp_value, $selected_price_plan_ids) ) {
					$selected_price_plan_ids[] = $pp_value;
					$related_category_ids[] = $category_id;
				} elseif ( in_array( $pp_value, $active_price_plans_on_category ) && !in_multidimensional_array($category_id, $related_category_ids)) {
					$index = array_search($pp_value, $active_price_plans_on_category);
					if (is_array($related_category_ids[$index])) {
                        $related_category_ids[$index][] = $category_id;
                    } else {
                        $related_category_ids[$index] = array($related_category_ids[$index], $category_id);
                    }
				}
			}
		}
	}

	return array(
        'selected_price_plan_ids' => $selected_price_plan_ids,
        'related_category_ids' => $related_category_ids
    );
}


function get_valid_category_price_plan_ids_on_post($post_id) {
	$price_plans = fetch_price_plans();
	$categories = wp_get_post_categories( $post_id );
	$selected_price_plan_ids = array();
	foreach ( $categories as $category_id ) {
		$active_price_plans_on_category = get_term_meta( $category_id, '_zp_price_plans' );
		if (count($active_price_plans_on_category) >= 1){
			foreach ( $price_plans as $pp ) {
				$pp_value = strval($pp->id);
				if ( in_array( $pp_value, $active_price_plans_on_category ) && !in_array($pp_value, $selected_price_plan_ids) ) {
					$selected_price_plan_ids[] = $pp_value;
				}
			}
		}
	}
	return $selected_price_plan_ids;
}

function is_monetized_with_category_pricing($post_id) {

	$premium = get_post_meta($post_id, 'zp_is_paid', 'paid');
	if ($premium){
		return false;
	}
	$price_plans = fetch_price_plans();
	$categories = wp_get_post_categories( $post_id );
	$selected_price_plan_ids = array();
	foreach ( $categories as $category_id ) {
		$active_price_plans_on_category = get_term_meta( $category_id, '_zp_price_plans' );
		if (count($active_price_plans_on_category) >= 1){
			foreach ( $price_plans as $pp ) {
				$pp_value = strval($pp->id);
				if ( in_array( $pp_value, $active_price_plans_on_category ) ) {
					return true;
				}
			}
		}
	}
	return false;
}

<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers bulk actions on edit post screen
 * @since 2.8.0
*/
add_filter( 'bulk_actions-edit-post', 'zp_register_my_bulk_actions' );
add_filter( 'bulk_actions-edit-page', 'zp_register_my_bulk_actions' );
function zp_register_my_bulk_actions( $bulk_actions ) {
	try {
		$bulk_actions['zp_enable_paywall_bulk_action'] = __( 'Enable Monetization', 'zp_enable_paywall_bulk_action');
		$bulk_actions['zp_disable_paywall_bulk_action'] = __( 'Disable Monetization', 'zp_disable_paywall_bulk_action');
		return $bulk_actions;
	} catch (Exception $e) {
		return $bulk_actions;
	}
}

/**
 * Registers premium on post index
 * @since 2.8.0
*/
add_filter('manage_post_posts_columns', 'zp_bulk_premium_filter');
add_filter('manage_page_posts_columns', 'zp_bulk_premium_filter');
function zp_bulk_premium_filter( $columns ){
	return array_merge($columns, ['premium' => __('Premium', 'textdomain')]);
}

/**
 * Implementation of the bulk actions
 * @since 2.8.0
*/
add_filter( 'handle_bulk_actions-edit-post', 'zp_custom_bulk_actions_handler', 10, 3 );
add_filter( 'handle_bulk_actions-edit-page', 'zp_custom_bulk_actions_handler', 10, 3 );
function zp_custom_bulk_actions_handler( $redirect_to, $doaction, $post_ids ) {
	try {
		if ( $doaction !== 'zp_enable_paywall_bulk_action' && $doaction !== 'zp_disable_paywall_bulk_action' ) {
			return $redirect_to;
		}

		foreach ( $post_ids as $post_id ) {
			if ( $doaction === 'zp_enable_paywall_bulk_action' ){
				update_post_meta($post_id, 'zp_is_paid', 'paid');
			} else {
				update_post_meta($post_id, 'zp_is_paid', false);
				delete_post_meta( $post_id, '_zp_price_plans' );
			}

		}

		$redirect_to = remove_query_arg( 'zp_bulk_zp_enable_paywall_bulk_action', $redirect_to );
		$redirect_to = remove_query_arg( 'zp_bulk_zp_disable_paywall_bulk_action', $redirect_to );
		$redirect_to = add_query_arg( 'zp_bulk_' . $doaction , count( $post_ids ), $redirect_to );
		return $redirect_to;
	} catch (Exception $e) {
		return $redirect_to;
	}
}

/**
 * Action for showing premium on posts table
 * @since 2.8.0
*/
add_action('manage_post_posts_custom_column', 'zp_manage_post_columns_premium', 10, 2);
add_action('manage_page_posts_custom_column', 'zp_manage_post_columns_premium', 10, 2);
function zp_manage_post_columns_premium($column_key, $post_id) {
	if ($column_key == 'premium') {
		$premium = get_post_meta($post_id, 'zp_is_paid', 'paid');
		if ($premium) {
			echo '<span style="color:green;">'; _e('Yes - Full site plan', 'textdomain'); echo '</span>';
		} else {
			$selected_price_plans_via_category = get_valid_category_price_plan_ids_on_post($post_id);
			if (count($selected_price_plans_via_category) >= 1) {
				echo '<span style="color:green;">'; _e('Yes - Category plan', 'textdomain'); echo '</span>';
			} else {
				echo '<span style="color:red;">'; _e('No - Free to read', 'textdomain'); echo '</span>';
			}
		}
	}
}

/**
 * Notices to let the user know what happened, depending on the state we set in the URL
 * @since 2.8.0
*/
add_action( 'admin_notices', 'zp_bulk_actions_admin_notice' );
function zp_bulk_actions_admin_notice() {
  if ( ! empty( $_REQUEST['zp_bulk_zp_enable_paywall_bulk_action'] ) ) {
    printf( '<div id="message" class="updated notice is-dismissible">' .
    	'<p>Monetization enabled for selected posts.</p>' . '</div>' );
  } else if ( ! empty( $_REQUEST['zp_bulk_zp_disable_paywall_bulk_action'] ) ) {
	printf( '<div id="message" class="updated notice is-dismissible">' .
		'<p>Monetization disabled for selected posts.</p>' . '</div>' );
  }
}



/**
 * Registers price plans columns
 * @since 3.1.0
*/
// add_filter('manage_post_posts_columns', 'zp_bulk_edit_price_plans_column');
// add_filter('manage_page_posts_columns', 'zp_bulk_edit_price_plans_column');
// function zp_bulk_edit_price_plans_column( $columns ){
// 	$price_plans = fetch_price_plans();
// 	if (count($price_plans) > 0){
// 		return array_merge($columns, ['price_plans' => __('Active Price Plans', 'textdomain')]);
// 	} else {
// 		return $columns;
// 	}

// }

/**
 * Action for showing price plan names on column
 * @since 3.1.0
*/
// add_action('manage_post_posts_custom_column', 'zp_manage_post_columns_price_plan_names', 10, 2);
// add_action('manage_page_posts_custom_column', 'zp_manage_post_columns_price_plan_names', 10, 2);
// function zp_manage_post_columns_price_plan_names($column_key, $post_id) {
// 	if ($column_key == 'price_plans') {
// 		$price_plans = fetch_price_plans();
// 		$meta_values = get_post_meta( $post_id, '_zp_price_plans' );
// 		$premium = get_post_meta($post_id, 'zp_is_paid', 'paid');
// 		$pp_valid = false;
// 		if (count($meta_values) >= 1){

// 			echo '<ul>';
// 			foreach ( $price_plans as $pp ) {
// 				$pp_value = strval($pp->id);
// 				if ( in_array( $pp_value, $meta_values ) ) {
// 					$pp_valid = true;
// 					echo '<li class="row-title">'; _e($pp->name, 'textdomain'); echo '</li>';
// 				}
// 			}
// 			echo '</ul>';
// 		}

// 		if (!$pp_valid && $premium) {
// 			echo '<span class="row-title"> Default Plan </span>';
// 		}
// 	}
// }

add_filter('manage_edit-category_columns', 'zp_add_active_price_plans_column_on_category_list');
function zp_add_active_price_plans_column_on_category_list( $columns ){
	$price_plans = fetch_price_plans();
	if (count($price_plans) > 0){
		return array_merge($columns, ['category_price_plans' => __('Active Price Plans', 'textdomain')]);
	} else {
		return $columns;
	}
}


add_action('manage_category_custom_column', 'zp_add_values_to_custom_category_column', 10, 3);
function zp_add_values_to_custom_category_column($value, $column_key, $term_id) {
	if ($column_key == 'category_price_plans') {
		$price_plans = fetch_price_plans();
		$meta_values = get_term_meta( $term_id, '_zp_price_plans' );
		$has_selection = false;
		if (count($meta_values) >= 1){

			echo '<ul>';
			foreach ( $price_plans as $pp ) {
				$pp_value = strval($pp->id);
				if ( in_array( $pp_value, $meta_values ) ) {
					$has_selection = true;
					echo '<li class="row-title">'; _e($pp->name, 'textdomain'); echo '</li>';
				}
			}
			echo '</ul>';
		}

		if (!$has_selection) {
			echo '<span class="zp-free-category"> Free to read </span>';
		}
	}
}



/**
 * Make the Price Plan selection Appear in Bulk Edit
 * @since 3.1.0
*/
/*
add_action( 'bulk_edit_custom_box',  'zp_bulk_edit_price_plans_box', 10, 2 );
function zp_bulk_edit_price_plans_box( $column_name, $post_type ) {

	switch( $column_name ) {
		case 'price_plans': {
			$price_plans = fetch_price_plans();

			if (count($price_plans) > 0){
				?>
				<?php wp_nonce_field( 'zp_bulk_edit', 'zp_bulk_edit_nonce' ); ?>

				<fieldset class="inline-edit-col-left">
					<div class="inline-edit-col">
					<span class="title">Select Price Plans to apply</span>
					<ul class="cat-checklist category-checklist">
				<?php

				foreach ($price_plans as $price_plan) {
					$pp_value = strval($price_plan->id);
					?>
						<li class="inline-edit-col">
							<label class="selectit">
								<input type="checkbox" name="zp_price_plans[]" value="<?php echo $pp_value; ?>" />
								<?php _e( strval($price_plan->name), $pp_value ); ?>
							</label>
						</li>
					<?php
				}

				?>
					</ul>
					</div>
				</fieldset>
				<?php
			}
			break;
		}
	}
}
*/

/**
 * Save the Price Plan selection via Bulk Edit
 * @since 3.1.0
*/
/*
add_action( 'save_post', 'zp_bulk_edit_price_plans_save' );
function zp_bulk_edit_price_plans_save( $post_id ){
	// check bulk edit nonce
	if ( ! (isset( $_REQUEST['zp_bulk_edit_nonce'] ) && wp_verify_nonce( $_REQUEST['zp_bulk_edit_nonce'], 'zp_bulk_edit' )) ) {
		return;
	}

	$price_plans_options = fetch_price_plans();
	// update checkbox
	if ( isset( $_REQUEST['zp_price_plans'] ) && is_array( $_REQUEST['zp_price_plans'] ) ) {
		// reset selection
		delete_post_meta( $post_id, '_zp_price_plans' );
		// add selection
		foreach ( $_REQUEST['zp_price_plans'] as $pp ) {
			add_post_meta( $post_id, '_zp_price_plans', $pp, false );
	   	}
		update_post_meta($post_id, 'zp_is_paid', 'paid');
	}
	else {
		// reset selection if no plan selected
		delete_post_meta( $post_id, '_zp_price_plans' );
		if (count($price_plans_options) > 0){
			// mark monetization off if price plan are more than 1
			update_post_meta($post_id, 'zp_is_paid', false);
		}
	}
}
*/

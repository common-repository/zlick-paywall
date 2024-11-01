<?php

if ( !function_exists( 'zlickpay_get_current_user_id' ) ) {
	/**
	 * Get user ID of logged in user
	 *
	 * @return array
	 */
     function zlickpay_get_current_user_id() {
         if ( ! function_exists( 'wp_get_current_user' ) ) {
             return null;
            }
        $user = wp_get_current_user();
        return ( isset( $user->ID ) ? (int) $user->ID : null );
    }
}
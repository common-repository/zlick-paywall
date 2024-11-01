<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends api keys options saved event information to external endpoint
 * @since 2.7.0
 * @return bool
*/
function post_saving_keys_event($client_token, $client_secret) {
    try {
		$event_information = array(
			'Save API Keys Date' => current_time('mysql'),
		);
        $body = array(
            'event_triggered' => sanitize_text_field( 'API Keys Saved' ),
			'event_information' => $event_information,
        );
        $headers = array(
            'client-token' => $client_token,
			'client-secret' => $client_secret,
        );
        $args = array(
            'body' => $body,
            'headers' => $headers,
        );
        $response =  wp_remote_post( 'https://portal.zlickpaywall.com/api/mixpanel_record', $args );
        if ( is_wp_error( $response ) ) {
            error_log( 'wp_remote_get failed: ' . $response->get_error_message() );
        } else {
            $body = wp_remote_retrieve_body( $response );
            error_log( 'wp_remote_retrieve_body: ' . $body );
        }
        return true;

    } catch (Exception $e) {
        return false;
    }
}

/**
 * Sends post meta set as paid event information to external endpoint
 * @since 2.7.0
 * @return bool
*/
function post_article_paid_event($post_id) {
	try {
		$post_title = get_the_title($post_id);

		$event_information = array(
			'Article Name' => $post_title,
			'Set Article As Paid Date' => current_time('mysql'),
		);

        $body = array(
            'event_triggered' => sanitize_text_field( 'Article Set As Paid' ),
			'event_information' => $event_information,
        );

		$client_token = zlickpay_get_options_data('zp_client_token');
		$client_secret = zlickpay_get_options_data('zp_client_secret');

		if (!isset($client_token) || !isset($client_secret)){
			return false;
		}

        $headers = array(
            'client-token' => $client_token,
			'client-secret' => $client_secret,
        );
        $args = array(
            'body' => $body,
            'headers' => $headers,
        );
        $response =  wp_remote_post( 'https://portal.zlickpaywall.com/api/mixpanel_record', $args );
        if ( is_wp_error( $response ) ) {
            error_log( 'wp_remote_get failed: ' . $response->get_error_message() );
        } else {
            $body = wp_remote_retrieve_body( $response );
            error_log( 'wp_remote_retrieve_body: ' . $body );
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

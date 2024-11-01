<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create the Apple Pay verification file in the site root
 * @since  2.6.0
 * @return bool
*/
function zlickpay_create_apple_verification_file() {
    try {
        global $wp_filesystem;

        $url = admin_url();
        if ( false === ( $creds = request_filesystem_credentials( $url, '', false, false, null ) ) ) { // phpcs:ignore
            return false;
        }

        if ( ! WP_Filesystem( $creds ) ) {
            request_filesystem_credentials( null, '', true, false, null );
            return;
        }

        $home_path       = dirname( $wp_filesystem->wp_content_dir() );
        $well_known_path = $home_path . '/.well-known/';
        $apple_ver_path  = $well_known_path . 'apple-developer-merchantid-domain-association';

        if ( $wp_filesystem->exists( $apple_ver_path ) ) {
            return true;
        }

        // If there's no well known directory...
        if ( ! $wp_filesystem->exists( $well_known_path ) ) {
            $wp_filesystem->mkdir( $well_known_path, FS_CHMOD_DIR );
        }

        // If we were unable to create it...
        if ( ! $wp_filesystem->exists( $well_known_path ) ) {
            return false;
        }

        // Grab a fresh copy of the Apple Pay Verification file from Stripe.
        $apple_certificate_content = sanitize_text_field( wp_remote_retrieve_body( wp_remote_get( 'https://stripe.com/files/apple-pay/apple-developer-merchantid-domain-association' ) ) );

        $apple_ver_file_created = $wp_filesystem->put_contents(
            $apple_ver_path,
            $apple_certificate_content,
            FS_CHMOD_FILE
        );

        if ( false !== $apple_ver_file_created && ! is_wp_error( $apple_ver_file_created ) ) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        return false;
    }
}

add_action( 'admin_init', 'zlickpay_create_apple_verification_file' );


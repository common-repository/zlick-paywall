<?php

if ( !function_exists( 'zlickpay_base64_url_encode' ) ) {
	/**
	 * @param $text
	 *
	 * @return string|string[]
	 */
	function zlickpay_base64_url_encode($text)
	{
		return str_replace(
			['+', '/', '='],
			['-', '_', ''],
			base64_encode($text)
		);
	}
}

if ( !function_exists( 'zlickpay_validate_sign' ) ) {
	/**
	 * @param $secret
	 * @param $jwt
	 *
	 * @return bool
	 */
	function zlickpay_validate_sign($secret, $signed)
	{
		$tokenParts        = explode( '.', $signed );
		$header            = base64_decode( $tokenParts[0] );
		$payload           = base64_decode( $tokenParts[1] );
		$signatureProvided = $tokenParts[2];

	// build a signature based on the header and payload using the secret
		$base64UrlHeader    = zlickpay_base64_url_encode( $header );
		$base64UrlPayload   = zlickpay_base64_url_encode( $payload );
		$signature          = hash_hmac( 'sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true );
		$base64UrlSignature = zlickpay_base64_url_encode( $signature );

	// verify it matches the signature provided in the token
		$signatureValid = ( $base64UrlSignature === $signatureProvided );

		if ( $signatureValid ) {
			return true;
		} else {
			return false;
		}
	}
}

if ( !function_exists( 'zlickpay_get_jwt_payload' ) ) {
	/**
	 * @param $signed
	 * 
	 * @return string
	 */
	function zlickpay_get_jwt_payload($signed) {
		$tokenParts = explode( '.', $signed );
		$payload = base64_decode( $tokenParts[1] );
		return $payload;
	}
}

if ( !function_exists( 'zlickpay_jwt_sign' ) ) {
	function zlickpay_jwt_sign($secret, $payload) {
		
		$header = json_encode([ "alg" => "HS256" ]);
		$base64UrlHeader    = zlickpay_base64_url_encode( $header );
		$base64UrlPayload   = zlickpay_base64_url_encode( $payload );
		$signature          = zlickpay_base64_url_encode(hash_hmac( 'sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true ));
		
		$token = join(".", [$base64UrlHeader, $base64UrlPayload, $signature]);

		return $token;
	}
}
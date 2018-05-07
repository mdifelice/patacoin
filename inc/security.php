<?php
function hash_text( $text ) {
	return hash( 'sha256', $text );
}

function get_public_key_from_address( $address ) {
	$public_key    = false;
	$temporal_file = tempnam( sys_get_temp_dir(), 'key' );

	$public_key_string = sprintf( "-----BEGIN PUBLIC KEY-----\n%s-----END PUBLIC KEY-----", chunk_split( $address, 64 ) );

	if ( false !== file_put_contents( $temporal_file, $public_key_string ) ) {
		$public_key = openssl_pkey_get_public( sprintf( 'file://%s', $temporal_file ) );

		unlink( $temporal_file );
	}

	return $public_key;
}

function is_valid_address( $address ) {
	$is_valid_address = false;

	$key = get_public_key_from_address( $address );

	if ( false !== $key ) {
		$is_valid_address = true;

		openssl_free_key( $key );
	}

	return $is_valid_address;
}

function verify_address( $address, $signature, $signed_text ) {
	$is_valid_signature = false;

	$key = get_public_key_from_address( $address );

	if ( false !== $key ) {
		$binary_signature = hex2bin( $signature );

		$is_valid_signature = 1 === openssl_verify( $signed_text, $binary_signature, $key, OPENSSL_ALGO_SHA256 );

		openssl_free_key( $key );
	}

	return $is_valid_signature;
}

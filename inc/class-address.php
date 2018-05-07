<?php
require_once __DIR__ . '/security.php';

class Address {
	private $folder,
			$passphrase;

	public function __construct( $folder, $passphrase ) {
		$this->folder     = $folder;
		$this->passphrase = $passphrase;

		if ( ! file_exists( $this->folder ) ) {
			mkdir( $this->folder, 0777, true );
		}
	}

	public function get_id() {
		if ( ! $this->exists() ) {
			 throw new Exception( 'Address does not exist' );
		}

		$lines = file( $this->get_public_key_file(), FILE_IGNORE_NEW_LINES );

		if ( false === $lines ) {
			throw new Exception( 'Cannot retrieve public key' );
		}

		$id = '';

		for ( $i = 1; $i < count( $lines ) - 1; $i++ ) {
			$id .= $lines[ $i ];
		}

		return $id;
	}

	public function exists() {
		return file_exists( $this->get_public_key_file() ) &&
			   file_exists( $this->get_private_key_file() );
	}

	public function check_passphrase() {
		$checked = false;

		try {
			$this->sign( '' );

			$checked = true;
		} catch( Exception $e ) {
		}

		return $checked;
	}

	public function sign( $text_to_sign ) {
		$key = openssl_pkey_get_private( sprintf( 'file://%s', $this->get_private_key_file() ), $this->passphrase );

		if ( false === $key ) {
			throw new Exception( 'Cannot retrieve private key' );
		}

		if ( ! openssl_sign( $text_to_sign, $binary_signature, $key ) ) {
			throw new Exception( 'Cannot sign text' );
		}

		openssl_free_key( $key );

		$signature = bin2hex( $binary_signature );

		return $signature;
	}

	public function generate_keys() {
		$key = openssl_pkey_new( array(
			'digest_alg'       => OPENSSL_ALGO_SHA256,
			'private_key_bits' => 384,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		) );

		if ( false === $key ) {
			throw new Exception( 'Cannot create new key' );
		}

		$details = openssl_pkey_get_details( $key );

		if ( false === $details ) {
			throw new Exception( 'Cannot retrieve public key' );
		}

		if ( false === openssl_pkey_export( $key, $private_key, $this->passphrase ) ) {
			throw new Exception( 'Cannot retrieve private key' );
		}

		openssl_pkey_free( $key );

		$public_key_file = $this->get_public_key_file();
		$public_key      = $details['key'];

		if ( false === file_put_contents( $public_key_file, $public_key ) ) {
			throw new Exception( 'Cannot write public key file' );
		}

		$private_key_file = $this->get_private_key_file();

		if ( false === file_put_contents( $private_key_file, $private_key ) ) {
			throw new Exception( 'Cannot write private key file' );
		}
	}

	private function get_public_key_file() {
		return $this->folder . '/key.pub';
	}

	private function get_private_key_file() {
		return $this->folder . '/key.pem';
	}
}

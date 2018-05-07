<?php
require_once __DIR__ . '/class-address.php';
require_once __DIR__ . '/security.php';

class Transaction implements JsonSerializable {
	public $timestamp,
		   $receiver,
		   $amount,
		   $payload,
		   $sender,
		   $signature,
		   $hash;

	public function __construct() {
		$this->timestamp = null;
		$this->receiver  = null;
		$this->amount    = null;
		$this->payload   = null;
		$this->sender    = null;
		$this->signature = null;
		$this->hash      = null;
	}

	public function jsonSerialize() {
		return sanitize_object( $this );
	}

	public function fromJSON( $json ) {
		$object = json_decode( $encoded_transaction );

		if ( null === $object ) {
			throw new Exception( 'Invalid JSON data' );
		}

		$this->fromObject( $object );
	}

	public function fromObject( $object ) {
		if ( ! is_object( $object ) ) {
			throw new Exception( 'Invalid object' );
		}

		$properties = get_object_public_property_keys( $this );

		foreach ( $properties as $property ) {
			if ( ! property_exists( $object, $property ) ) {
				throw new Exception( sprintf( "Missing property '%s'", $property ) );
			}

			$this->{ $property } = $object->{ $property };
		}
	}

	public function sign( $address ) {
		$this->sender    = $address->get_id();
		$this->timestamp = null;
		$this->signature = null;
		$this->hash      = null;

		$this->signature = $address->sign( json_encode( $this ) );
	}

	public function hash() {
		$this->timestamp = time();
		$this->hash      = null;

		$this->hash = hash_text( json_encode( $this ) );
	}

	public function verify_signature() {
		$transaction = clone( $this );

		$transaction->timestamp = null;
		$transaction->signature = null;
		$transaction->hash      = null;

		return verify_address( $transaction->sender, $this->signature, json_encode( $transaction ) );
	}

	public function verify_hash() {
		$transaction = clone( $this );

		$transaction->hash = null;

		return hash_text( json_encode( $transaction ) ) === $this->hash;
	}
}

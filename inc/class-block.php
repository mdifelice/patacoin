<?php
require_once __DIR__ . '/class-address.php';
require_once __DIR__ . '/class-transaction.php';
require_once __DIR__ . '/sanitization.php';
require_once __DIR__ . '/security.php';

class Block implements JsonSerializable {
	public $difficulty,
		   $payload,
		   $timestamp,
		   $transactions,
		   $previous_hash,
		   $miner,
		   $signature,
		   $nonce,
		   $hash;

	private $find_nonce_cancelled;

	public function __construct() {
		$this->difficulty           = null;
		$this->timestamp            = null;
		$this->payload              = null;
		$this->previous_hash        = null;
		$this->miner                = null;
		$this->signature            = null;
		$this->nonce                = null;
		$this->hash                 = null;
		$this->transactions         = array();
		$this->find_nonce_cancelled = false;
	}

	public function jsonSerialize() {
		return sanitize_object( $this );
	}

	public function fromJSON( $json ) {
		$object = json_decode( $json );

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

			$value = $object->{ $property };

			if ( 'transactions' === $property ) {
				if ( ! is_array( $value ) ) {
					throw new Exception( 'Invalid transactions' );
				}

				foreach ( $value as $index => $transaction_object ) {
					try {
						$transaction = new Transaction();

						$transaction->fromObject( $transaction_object );

						$this->transactions[] = $transaction;
					} catch ( Exception $e ) {
						throw new Exception( sprintf( 'Invalid transaction #%s (%s)', $index, $e->getMessage() ) );
					}
				}
			} else {
				$this->{ $property } = $value;
			}
		}
	}

	public function sign( $address ) {
		$this->miner     = $address->get_id();
		$this->signature = null;
		$this->timestamp = null;
		$this->nonce     = null;
		$this->hash      = null;

		$this->signature = $address->sign( json_encode( $this ) );
	}

	public function hash() {
		$this->timestamp = time();
		$this->hash      = null;

		$this->hash = hash_text( json_encode( $this ) );
	}

	public function verify_signature() {
		$block = clone( $this );

		$block->signature = null;
		$block->timestamp = null;
		$block->nonce     = null;
		$block->hash      = null;

		return verify_address( $block->miner, $this->signature, json_encode( $block ) );
	}

	public function verify_hash() {
		$block = clone( $this );

		$block->hash = null;

		return hash_text( json_encode( $block ) ) === $this->hash;
	}

	public function find_nonce() {
		$found = false;

		$this->find_nonce_cancelled = false;

		for ( $i = 0; $i < PHP_INT_MAX && ! $this->find_nonce_cancelled; $i++ ) {
			pcntl_signal_dispatch();

			$this->nonce = $i;

			$this->hash();

			if ( $this->does_hash_meets_difficulty() ) {
				$found = true;

				break;
			}
		}

		return $found;
	}

	public function cancel_find_nonce() {
		$this->find_nonce_cancelled = true;
	}

	public function does_hash_meets_difficulty() {
		$difficulty = absint( $this->difficulty );

		$decimal_hash = hex2bin( $this->hash );
		$binary_hash  = '';

		for ( $i = 0; $i < strlen( $decimal_hash ); $i++ ) {
			$binary_hash .= sprintf( '%08b', ord( $decimal_hash[ $i ] ) );
		}

		return $difficulty ? 0 === strpos( $binary_hash, str_repeat( '0', $this->difficulty ) ) : true;
	}
}

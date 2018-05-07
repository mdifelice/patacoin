<?php
require_once __DIR__ . '/class-block.php';
require_once __DIR__ . '/class-transaction.php';
require_once __DIR__ . '/sanitization.php';

define( 'ALLOWED_MINERS',
	'MEwwDQYJKoZIhvcNAQEBBQADOwAwOAIxANRESWBG25cpHh6Eh5vpIqr7C7pbq4YUXGx0zxsql2Gy6nrxoaaiJjtTQpqsxB6bzwIDAQAB,' .
	'MEwwDQYJKoZIhvcNAQEBBQADOwAwOAIxANzW7slKn83F+En7zRH2uNT0oD6KUXCbV64nNQocgbUFT8Ul5doKdl8TGPQKtHmgUQIDAQAB,' .
	//'MEwwDQYJKoZIhvcNAQEBBQADOwAwOAIxAL51SLjzfZDKHqo8yucRocGsRDTvv/5OAh0yrQ0BBduOluJ2U+Xmdnxkh99hvx/scQIDAQAB,' .
	''
);
define( 'AWARD_AMOUNT',                  100 );
define( 'BASE_DIFFICULTY',               1 );
define( 'DESIRED_MINING_FREQUENCY',      30 );
define( 'DIFFICULTY_CALCULATION_BLOCKS', 100 );
define( 'MINIMUM_TIMESTAMP',            strtotime( '2018-04-02' ) );

class Blockchain implements JsonSerializable {
	public $blocks;

	private $addresses,
			$block_hashes,
			$transactions,
			$invalid_transactions;

	public function __construct() {
		$this->blocks      	        = array();
		$this->block_hashes         = array();
		$this->addresses            = array();
		$this->transactions         = array();
		$this->invalid_transactions = array();
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

			if ( 'blocks' === $property ) {
				if ( ! is_array( $value ) ) {
					throw new Exception( 'Invalid blocks' );
				}

				foreach ( $value as $index => $block_object ) {
					try {
						$block = new Block();
			
						$block->fromObject( $block_object );

						$this->blocks[] = $block;
					} catch ( Exception $e ) {
						throw new Exception( sprintf( 'Invalid block #%s (%s)', $index, $e->getMessage() ) );
					}
				}
			} else {
				$this->{ $property } = $value;
			}
		}

		$this->validate();
	}

	public function add_block( $block ) {
		$this->blocks[] = $block;

		$this->validate( count( $this->blocks ) - 1 );
	}

	public function get_block( $hash ) {
		if ( ! isset( $this->block_hashes[ $hash ] ) ) {
			throw new Exception( 'Unknown block' );
		}

		return $this->get_block_by_index( $this->block_hashes[ $hash ] );
	}

	public function get_block_by_index( $index ) {
		if ( ! isset( $this->blocks[ $index ] ) ) {
			throw new Exception( 'Missing block' );
		}

		return $this->blocks[ $index ];
	}

	public function get_block_count() {
		return count( $this->blocks );
	}

	public function get_latest_block() {
		$block = null;

		$block_count = count( $this->blocks );
		
		if ( $block_count ) {
			$block = $this->blocks[ $block_count - 1 ];
		}

		return $block;
	}

	public function get_transaction( $hash ) {
		if ( ! isset( $this->transactions[ $hash ] ) ) {
			throw new Exception( 'Unknown transaction' );
		}

		return $this->transactions[ $hash ];
	}

	public function exists_transaction( $hash ) {
		$exists = false;

		try {
			$this->get_transaction( $hash );

			$exists = true;
		} catch ( Exception $e ) {
		}

		return $exists;
	}

	public function get_balance( $address ) {
		if ( ! isset( $this->addresses[ $address ] ) ) {
			throw new Exception( 'Unknown address' );
		}

		return $this->addresses[ $address ];
	}

	public function generate_genesis_block() {
		$block = new Block();

		$block->difficulty = $this->get_difficulty();
		$block->payload    = new StdClass();

		$block->payload->allowed_miners = $this->get_allowed_miners();

		$block->find_nonce();

		return $block;
	}

	public function get_allowed_miners() {
		return array_filter( explode( ',', ALLOWED_MINERS ) );
	}

	public function get_award_amount() {
		return AWARD_AMOUNT;
	}

	public function get_base_difficulty() {
		return BASE_DIFFICULTY;
	}

	public function get_desired_mining_frequency() {
		return DESIRED_MINING_FREQUENCY;
	}

	public function get_difficulty_calculation_blocks() {
		return DIFFICULTY_CALCULATION_BLOCKS;
	}

	public function get_difficulty( $block_index = null ) {
		$analyzed_blocks   = 0;
		$total_difference  = 0;
		$difficulty        = $this->get_base_difficulty();
		$desired_frequency = $this->get_desired_mining_frequency();
		$blocks_to_check   = $this->get_difficulty_calculation_blocks();

		if ( null === $block_index ) {
			$block_index = count( $this->blocks );
		}

		for ( $i = 0; $i < $blocks_to_check; $i++ ) {
			$current_position = $block_index - $i - 1;

			if ( $current_position > 0 ) {
				$block = $this->blocks[ $current_position ];

				if ( count( $block->transactions ) <= 10 ) {
					$total_difference += $block->timestamp - $this->blocks[ $current_position - 1 ]->timestamp;

					$analyzed_blocks++;
				}
			} else {
				break;
			}
		}

		if ( $analyzed_blocks ) {
			$average_frequency = round( $total_difference / $analyzed_blocks );

			$difficulty = $this->blocks[ $block_index - 1 ]->difficulty;

			if ( $average_frequency > $desired_frequency ) {
				$difficulty = max( 0, $difficulty - 1 );
			} else if ( $average_frequency < $desired_frequency ) {
				$difficulty = min( 255, $difficulty + 1 );
			}
		}

		return $difficulty;
	}

	public function validate( $start_index = 0 ) {
		$blocks         = $this->blocks;
		$allowed_miners = $this->get_allowed_miners();
		$award_amount   = $this->get_award_amount();

		$this->invalid_transactions = array();

		for ( $block_index = $start_index; $block_index < count( $blocks ); $block_index++ ) {
			$block = $this->blocks[ $block_index ];

			try {
				$previous_block = 0 === $block_index ? null : $this->blocks[ $block_index - 1 ];

				if ( isset( $this->block_hashes[ $block->hash ] ) ) {
					throw new Exception( 'Duplicated hash' );
				}

				if ( ! $block->verify_hash() ) {
					throw new Exception( 'Incorrect hash' );
				}

				$desired_difficulty = $this->get_difficulty( $block_index );

				if ( $block->difficulty < $desired_difficulty ) {
					throw new Exception( 'Lower difficulty than the desired one' );
				}

				if ( ! $block->does_hash_meets_difficulty() ) {
					throw new Exception( 'Hash does not meet difficulty' );
				}

				if ( $block->timestamp > time() ) {
					throw new Exception( 'Timestamp is in the future' );
				}

				if ( $previous_block ) {
					if ( $block->previous_hash !== $previous_block->hash ) {
						throw new Exception( 'Previous hash does not match previous block hash' );
					}

					if ( $block->timestamp < $previous_block->timestamp ) {
						throw new Exception( 'Timestamp is older than previous block timestamp' );
					}

					if ( $block->verify_signature() ) {
						throw new Exception( 'Unauthenticated miner' );
					}

					if ( ! in_array( $block->miner, $allowed_miners ) ) {
						throw new Exception( 'Unauthorized miner' );
					}

					if ( ! count( $block->transactions ) ) {
						throw new Exception( 'Has no transactions' );
					}

					$is_miner_award = false;

					foreach ( $block->transactions as $transaction ) {
						try {
							if ( isset( $this->transactions[ $transaction->hash ] ) ) {
								throw new Exception( 'Duplicated hash' );
							}

							$amount = absint( $transaction->amount );

							if ( empty( $transaction->sender ) ) {
								if ( $is_miner_award ) {
									throw new Exception( 'Duplicated miner award' );
								}

								if ( $transaction->receiver !== $block->miner ) {
									throw new Exception( 'Invalid sender for award transaction' );
								}

								if ( $amount !== $award_amount ) {
									throw new Exception( 'Invalid amount for award' );
								}

								$is_miner_award = true;
							} else {
								$this->validate_transaction( $transaction );

								$this->addresses[ $transaction->sender ] -= $amount;
							}

							if ( ! isset( $this->addresses[ $transaction->receiver ] ) ) {
								$this->addresses[ $transaction->receiver ] = 0;
							}

							$this->addresses[ $transaction->receiver ] += $amount;

							$this->transactions[ $transaction->hash ] = $transaction;
						} catch ( Exception $e ) {
							$this->invalid_transactions[ $transaction->hash ] = $e->getMessage();
						}
					}

					if ( count( $this->invalid_transactions ) ) {
						$errors = '';

						foreach ( $this->invalid_transactions as $hash => $error ) {
							$errors .= sprintf( "%sTransaction '%s' (%s)", empty( $errors ) ? '' : ', ', $hash, $error );
						}

						throw new Exception( $errors );
					}

					if ( ! $is_miner_award ) {
						throw new Exception( 'Missing miner award' );
					}
				} else {
					if ( ! isset( $block->payload->allowed_miners ) ) {
						throw new Exception( 'Missing allowed miners' );
					}

					if ( $allowed_miners !== $block->payload->allowed_miners ) {
						throw new Exception( 'Invalid allowed miners' );
					}

					if ( $block->timestamp < MINIMUM_TIMESTAMP ) {
						throw new Exception( 'Timestamp is lower than minimum' );
					}
				}

				$this->block_hashes[ $block->hash ] = $block_index;
			} catch ( Exception $e ) {
				throw new Exception( sprintf( "Block #%s: '%s' (%s)", $block_index, $block->hash, $e->getMessage() ) );
			}
		}
	}

	public function validate_transaction( $transaction ) {
		if ( ! $transaction->verify_hash() ) {
			throw new Exception( 'Incorrect hash' );
		}

		if ( empty( $transaction->sender ) ) {
			throw new Exception( 'Empty sender' );
		}

		if ( empty( $transaction->receiver ) ) {
			throw new Exception( 'Empty receiver' );
		}

		if ( ! is_valid_address( $transaction->sender ) ) {
			throw new Exception( 'Invalid sender' );
		}

		if ( ! is_valid_address( $transaction->receiver ) ) {
			throw new Exception( 'Invalid receiver' );
		}

		if ( ! isset( $this->addresses[ $transaction->sender ] ) ) {
			throw new Exception( 'Unknown sender' );
		}

		if ( $transaction->verify_signature() ) {
			throw new Exception( 'Unauthenticated sender' );
		}

		if ( $transaction->sender === $transaction->receiver ) {
			throw new Exception( 'Receiver equal to sender' );
		}

		$amount = absint( $transaction->amount );

		if ( 0 === $amount ) {
			throw new Exception( 'Invalid amount' );
		}

		if ( $amount > $this->addresses[ $transaction->sender ] ) {
			throw new Exception( 'Not enough funds' );
		}
	}

	public function get_invalid_transactions() {
		return array_keys( $this->invalid_transactions );
	}
}

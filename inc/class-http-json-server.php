<?php
class HTTP_JSON_Server {
	private $port,
			$callback,
			$after_callback,
			$child_process,
			$listener_socket;

	public function __construct( $port, $callback ) {
		$this->port            = $port;
		$this->callback        = $callback;
		$this->after_callback  = null;
		$this->listener_socket = null;
	}

	public function on_after_callback( $callback ) {
		$this->after_callback = $callback;
	}

	public function stop() {
		if ( $this->listener_socket ) {
			socket_close( $this->listener_socket );

			$this->listener_socket = null;
		}
	}

	public function start() {
		$this->listener_socket = @socket_create_listen( $this->port );

		if ( false !== $this->listener_socket ) {
			$started = true;

			socket_set_option( $this->listener_socket, SOL_SOCKET, SO_LINGER, array(
				'l_linger' => 0,
				'l_onoff'  => 1,
			) );

			while ( $client = @socket_accept( $this->listener_socket ) ) {
				$method             = null;
				$request            = null;
				$headers            = array();
				$body               = '';
				$consecutive_breaks = 0;

				$address = null;

				if ( ! socket_getpeername( $client, $host, $port ) ) {
					$host = null;
					$port = null;
				}

				socket_set_option( $client, SOL_SOCKET, SO_RCVTIMEO, array(
					'sec'  => 5,
					'usec' => 0,
				) );

				$contents = '';
				$header   = '';

				while ( $buffer = @socket_read( $client, 8192 ) ) {
					$contents .= $buffer;

					if ( false !== strpos( $contents, "\r\n\r\n" ) ) {
						$aux = explode( "\r\n\r\n", $contents );

						$header = $aux[0];
						$body   = $aux[1];

						break;
					}
				}

				$lines = array_filter( explode( "\n", $header ) );

				foreach ( $lines as $line ) {
					$line = trim( $line );

					if ( ! empty( $line ) ) {
						if ( preg_match( '/^(\w+)\s([^\s]+)\sHTTP\/1\.[0-1]$/', $line, $matches ) ) {
							$method  = strtoupper( $matches[1] );
							$request = $matches[2];
						} else if ( preg_match( '/^([^:]+):(.+)$/', $line, $matches ) ) {
							$headers[ strtolower( $matches[1] ) ] = $matches[2];
						}
					}
				}

				if ( isset( $headers['content-length'] ) ) {
					$remaining_data = $headers['content-length'] - strlen( $body );

					if ( $remaining_data ) {
						do {
							$body .= @socket_read( $client, 8192 );
						} while ( strlen( $body ) < $headers['content-length'] );
					}
				} else {
					$body = '';
				}

				$url = parse_url( $request );

				if ( isset( $url['path'] ) ) {
					$path = $url['path'];
				} else {
					$path = '';
				}

				if ( empty( $body ) ) {
					if ( isset( $url['query'] ) ) {
						parse_str( $url['query'], $query );

						$data = @json_decode( json_encode( $query ) );
					} else {
						$data = null;
					}
				} else {
					$data = @json_decode( $body );
				}

				$response = call_user_func( $this->callback, $method, $path, $data, $headers, $host, $port );

				$text = $this->response_to_text( $response );

				@socket_write( $client, $text );

				@socket_close( $client );

				if ( $this->after_callback ) {
					call_user_func( $this->after_callback );
				}
			}
		} else {
			$started = false;
		}

		return $started;
	}

	private function response_to_text( $response ) {
		if ( null === $response ) {
			$status = '400 Bad Request';
		} else {
			$status = '200 OK';
		}

		$encoded_response = json_encode( $response );

		$text = sprintf( "HTTP/1.1 %s\r\n", $status );
		$text .= sprintf( "Connection: close\r\n" );
		$text .= sprintf( "Content-type: application/json\r\n" );
		$text .= sprintf( "Content-length: %s\r\n", strlen( $encoded_response ) );
		$text .= "\r\n";
		$text .= $encoded_response;

		return $text;
	}
}

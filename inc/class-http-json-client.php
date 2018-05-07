<?php
class HTTP_JSON_Client {
	public $endpoint,
		   $headers;

	public function __construct( $endpoint, $headers = null ) {
		$this->endpoint = $endpoint;
		$this->headers  = $headers;
	}

	public function call( $method, $path, $data = null ) {
		$context_arguments = array(
			'http' => array(
				'method'  => $method,
				'timeout' => 5,
			),
		);

		$headers = array();

		if ( ! empty( $this->headers ) ) {
			foreach ( $this->headers as $key => $value ) {
				$headers[ $key ] = $value;
			}
		}

		if ( ! empty( $data ) ) {
			if ( ! is_array( $data ) || 'POST' === $method ) {
				$encoded_data = json_encode( $data );

				$headers['Content-type']   = 'application/json';
				$headers['Content-length'] = strlen( $encoded_data );

				$context_arguments['http']['content'] = $encoded_data;
			} else {
				$path = sprintf( '%s?%s', $path, http_build_query( $data ) );
			}
		}

		if ( ! empty( $headers ) ) {
			$header = '';

			foreach ( $headers as $key => $value ) {
				$header .= sprintf( "%s:%s\r\n", $key, is_scalar( $value ) ? $value : json_encode( $value ) );
			}

			$context_arguments['http']['header'] = $header;
		}

		$context = stream_context_create( $context_arguments );

		$contents = @file_get_contents(
			sprintf( 'http://%s%s', $this->endpoint, $path ),
			false,
			$context
		);

		if ( false === $contents ) {
			throw new Exception( 'Cannot connect to server' );
		}

		$response = json_decode( $contents );

		if ( null === $response ) {
			throw new Exception( 'Invalid response' );
		}

		return $response;
	}

	public function get( $path, $data = null ) {
		return $this->call( 'GET', $path, $data );
	}

	public function post( $path, $data = null ) {
		return $this->call( 'POST', $path, $data );
	}
}

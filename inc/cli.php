<?php
function prompt( $prefix = null, $mask = null ) {
	static $history = array();

	$input = '';

	if ( null !== $prefix ) {
		fwrite( STDOUT, $prefix );
	}

	$stty  = shell_exec( 'stty -g' );

	shell_exec( 'stty -icanon -echo min 1 time 0' );

	$read = true;

	while ( $read ) {
		$character = fgetc( STDIN );

		if ( "\n" === $character ) {
			$read = false;

			fwrite( STDOUT, "\n" );
		} elseif ( 127 === ord( $character ) ) {
			if ( strlen( $input ) > 0 ) {
				fwrite( STDOUT, "\x08 \x08" );

				$input = substr( $input, 0, -1 );
			}
		} elseif ( 24 === ord( $character ) ) {
		} elseif ( 25 === ord( $character ) ) {
		} elseif ( 26 === ord( $character ) ) {
		} elseif ( 27 === ord( $character ) ) {
		} else {
			fwrite( STDOUT, null !== $mask ? $mask : $character );

			$input .= $character;
		}
	}

	shell_exec( sprintf( 'stty %s', $stty ) );

	return $input;
}

function print_error( $message ) {
	print_message( sprintf( 'Error: %s', $message ), STDERR );
}

function print_message( $message, $output = STDOUT ) {
	fprintf( $output, "[%s] %s\n", date( 'r' ), $message );
}

function parse_arguments( $rules ) {
	global $argv;

	$arguments = array();

	for ( $i = 0; $i < count( $argv ); $i++ ) {
		if ( preg_match( '/^--(.+)$/', $argv[ $i ], $matches ) ) {
		   	$key = $matches[1];

			if ( isset( $rules[ $key ] ) && isset( $argv[ $i + 1 ] ) ) {
				$arguments[ $key ] = $argv[ $i + 1 ];

				$i++;
			}
		}
	}

	$missing_arguments = array();

	foreach ( $rules as $key => $rule ) {
		if ( isset( $rule['default'] ) ) {
			$default = $rule['default'];
		} else {
			$default = '';
		}

		if ( ! isset( $arguments[ $key ] ) ) {
			$arguments[ $key ] = $default;
		}

		if ( ! empty( $rule['required'] ) ) {
			if ( empty( $arguments[ $key ] ) ) {
				$missing_arguments[] = $key;
			}
		}
	}

	if ( ! empty( $missing_arguments ) ) {
		fprintf(
			STDERR,
			"Missing required arguments: '%s'\n\nUsage: php %s %s\n",
			implode( "', '", $missing_arguments ),
			$_SERVER['SCRIPT_FILENAME'],
			implode( ' ', array_map(
				function( $value ) {
					return sprintf(
						'--%s <%s>',
						$value,
						strtoupper( $value )
					);
				}, 
				array_keys( $arguments )
			) )
		);

		exit( 1 );
	}

	return $arguments;
}

<?php
function absint( $value ) {
	return abs( intval( $value ) );
}

function get_object_public_property_keys( $object ) {
	$reflection = new ReflectionClass( get_class( $object ) );
	$keys       = array();
	$properties = $reflection->getProperties( ReflectionProperty::IS_PUBLIC );

	foreach ( $properties as $property ) {
		$keys[] = $property->name;
	}

	return $keys;
}

function sanitize_object( $object ) {
	$sanitized_object = new StdClass();

	$properties = get_object_public_property_keys( $object );

	foreach ( $properties as $property ) {
		$sanitized_object->{ $property } = $object->{ $property };
	}

	return $sanitized_object;
}

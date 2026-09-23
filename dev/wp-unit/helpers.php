<?php
declare( strict_types=1 );

use Lipe\WP_Unit\Exceptions\TestHelperException;
use Lipe\WP_Unit\Utils\PrivateAccess;

/**
 * Version 5.0.0
 *
 * @requires wp-unit 4.8.0
 */

/**
 * Call a protected / private method of a class.
 *
 * @deprecated 4.0.0 Use `PrivateAccess::in()->call_private_method()` instead.
 *
 * @param class-string|object $object      An instantiated object or class name that we will run the method on.
 * @param string              $method_name Method name to call.
 * @param array               $parameters  Array of parameters to pass into method.
 *
 * @throws TestHelperException
 * @return mixed Method return.
 */
function call_private_method( string|object $object, string $method_name, array $parameters = [] ): mixed {
	return PrivateAccess::in()->call_private_method( $object, $method_name, $parameters );
}

/**
 * Get the value of a private constant or property from an object.
 *
 * @deprecated 4.0.0 Use `PrivateAccess::in()->get_private_property()` instead.
 *
 * @param class-string|object $object   An instantiated object or class name that we will run the method on.
 * @param string              $property Property name or constant name to get.
 *
 * @throws TestHelperException
 * @return mixed
 */
function get_private_property( string|object $object, string $property ): mixed {
	return PrivateAccess::in()->get_private_property( $object, $property );
}

/**
 * Set the value of a private property on an object.
 *
 * @deprecated 4.0.0 Use `PrivateAccess::in()->set_private_property()` instead.
 *
 * @param class-string|object $object   An instantiated object to set property on.
 * @param string              $property Property name to set.
 * @param mixed               $value    Value to set.
 *
 * @throws TestHelperException
 * @return void
 */
function set_private_property( string|object $object, string $property, mixed $value ): void {
	PrivateAccess::in()->set_private_property( $object, $property, $value );
}

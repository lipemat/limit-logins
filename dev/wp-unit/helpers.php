<?php
/** @noinspection PhpExpressionResultUnusedInspection, PhpUnhandledExceptionInspection */
declare( strict_types=1 );

use Lipe\Limit_Logins\Container;
use function Lipe\Limit_Logins\container;

/**
 * Version 3.1.0
 */

/**
 * A special exception class for the test helpers.
 *
 * - Allows us to know if an exception was specific to testing internals.
 * - Ignornable exception for PHPStorm.
 *
 * @since 2.9.0
 *
 */
class TestHelperException extends \Exception {
}

/**
 * Call a protected / private method of a class.
 *
 * @param class-string|object $object      An instantiated object or class name that we will run the method on.
 * @param string              $method_name Method name to call.
 * @param array               $parameters  Array of parameters to pass into method.
 *
 * @return mixed Method return.
 */
function call_private_method( string|object $object, string $method_name, array $parameters = [] ): mixed {
	$reflection = new \ReflectionClass( \is_string( $object ) ? $object : \get_class( $object ) );
	if ( \is_string( $object ) ) {
		$object = $reflection->newInstanceWithoutConstructor();
	}
	$method = $reflection->getMethod( $method_name );

	return $method->invokeArgs( $object, $parameters );
}

/**
 * Get the value of a private constant or property from an object.
 *
 * @param class-string|object $object   An instantiated object or class name that we will run the method on.
 * @param string              $property Property name or constant name to get.
 *
 * @return mixed
 */
function get_private_property( string|object $object, string $property ): mixed {
	$reflection = new \ReflectionClass( \is_string( $object ) ? $object : \get_class( $object ) );
	if ( $reflection->hasProperty( $property ) ) {
		$reflection_property = $reflection->getProperty( $property );
		if ( $reflection_property->isStatic() ) {
			return $reflection_property->getValue();
		}
		if ( \is_string( $object ) ) {
			throw new \TestHelperException( 'Getting a non-static value from a non-instantiated object is useless.', E_USER_ERROR );
		}
		return $reflection_property->getValue( $object );
	}
	return $reflection->getConstant( $property );
}

/**
 * Set the value of a private property on an object.
 *
 * @param class-string|object $object   An instantiated object to set property on.
 * @param string              $property Property name to set.
 * @param mixed               $value    Value to set.
 *
 * @return void
 */
function set_private_property( string|object $object, string $property, mixed $value ): void {
	$reflection = new \ReflectionClass( \is_string( $object ) ? $object : \get_class( $object ) );
	$reflection_property = $reflection->getProperty( $property );
	if ( $reflection_property->isStatic() ) {
		$reflection_property->setValue( null, $value );
	} else {
		if ( \is_string( $object ) ) {
			throw new \TestHelperException( 'Setting a non-static value on a non-instantiated object is useless.', E_USER_ERROR );
		}
		$reflection_property->setValue( $object, $value );
	}
}

/**
 * Change any object within the container to another object.
 *
 * @example      works well with Php 7 anonymous classes
 *          $mock = new class extends \Lipe\Project\Runner\Tasks\Email {
 *              public function run_task(){
 *                  $emails = call_private_method($this, 'get_existing_emails');
 *              }
 *          }
 *
 * @example      change_container_object('cron.tasks.email', new Timeout_Email());.
 *
 * @note         Will override final classed due to `BypassFinals::enable();`.
 *
 * @param string $key        - The container key.
 * @param object $object     - object instantiated with new just like within the container.
 *                           Done this way to allow passing whatever we want to the constructor of said object.
 * @param bool   $is_factory - Does this object use a factory method such as $container->factory() or $container->protect().
 *                           If it does and this is not set to true it will Error : Function name must be a string.
 *
 * @return void
 */
function change_container_object( string $key, object $object, bool $is_factory = false ): void {
	$container = container()->container();
	unset( $container[ $key ] );
	if ( $is_factory ) {
		$container[ $key ] = $container->protect( function() use ( $object ) {
			return $object;
		} );
	} else {
		$container[ $key ] = function() use ( $object ) {
			return $object;
		};
	}
}

/**
 * Reset any changes made to the container during testing.
 *
 * @see \WP_UnitTestCase_Base::tear_down
 */
function tests_reset_container(): void {
	set_private_property( Container::instance(), 'core_instance', null );
	Container::instance();
}


/**
 * `allow_extending_final` has been moved to the wp-unit library.
 */

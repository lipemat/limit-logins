<?php

use Lipe\Limit_Logins\Attempts;
use Lipe\Limit_Logins\Attempts\Attempt;
use Lipe\Limit_Logins\Authenticate;

/** @var \WP_UnitTestCase_Base $this */

$password = wp_generate_password();
$user = $this->factory()->user->create_and_get( [
	'user_pass' => $password,
] );

for ( $i = 0; $i < Attempts::ALLOWED_ATTEMPTS; $i ++ ) {
	$this->assertWPError( wp_authenticate( $user->user_login, 'NOT A PASSWORD' ) );
}

$result = wp_authenticate( $user->user_login, $password );
$this->assertWPError( $result );
$this->assertSame( 'blocked', $result->get_error_code() );
$this->assertSame( call_private_method( Authenticate::in(), 'get_error' ), $result->get_error_message() );

$attempt = Attempts::in()->get_existing( $user->user_login );
$this->assertTrue( $attempt->is_blocked() );
$this->assertGreaterThanOrEqual( 1, Attempts::in()->get_all() );
$this->assertSame( Attempts::ALLOWED_ATTEMPTS, $attempt->get_count() );

if ( ! class_exists( 'Fixture_Blocked_User' ) ) {
	readonly class Fixture_Blocked_User {
		public function __construct(
			public WP_User $user,
			public string $password,
			public Attempt $attempt,
		) {
		}
	}
}

return new Fixture_Blocked_User( $user, $password, $attempt );
